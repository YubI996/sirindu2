<?php

namespace App\Imports;

use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Traits\ResolvesAnakByTwoOfThree;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data imunisasi dari CSV template_imunisasi.csv.
 *
 * Identifikasi anak menggunakan logika 2-dari-3: NIK, nama, tgl_lahir.
 * Upsert Imunisasi by (id_anak, id_jenis_vaksin).
 * kode_vaksin harus cocok dengan kolom 'kode' di tabel jenis_vaksin.
 */
class ImunisasiImport implements ToCollection, WithStartRow, WithChunkReading
{
    use ResolvesAnakByTwoOfThree;

    protected int $userId;
    protected int $successCount = 0;
    protected int $errorCount   = 0;
    protected array $failures   = [];
    protected int $rowOffset    = 0;

    protected ?array $columnMap    = null;
    protected int    $headerRowIdx = 0;

    /** Cache kode_vaksin → id_jenis_vaksin */
    protected array $vaksinCache = [];

    protected array $validStatuses = ['belum', 'sudah', 'terlambat'];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->vaksinCache = JenisVaksin::pluck('id', 'kode')->toArray();
    }

    public function startRow(): int { return 1; }
    public function chunkSize(): int { return 500; }

    // =========================================================================
    // Parse helpers
    // =========================================================================

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) {
            try { return Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('Y-m-d'); }
            catch (\Exception $e) { return null; }
        }
        try { return Carbon::parse((string) $value)->format('Y-m-d'); }
        catch (\Exception $e) { return null; }
    }

    protected function parseStatus($value): string
    {
        $val = strtolower(trim((string) ($value ?? '')));
        return in_array($val, $this->validStatuses) ? $val : 'belum';
    }

    protected function parseIntVal($value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    // =========================================================================
    // Main processor
    // =========================================================================

    public function collection(Collection $rows)
    {
        $isFirstChunk = $this->rowOffset === 0;
        $originalSize = count($rows);

        if ($isFirstChunk) {
            $detected = $this->detectImportHeader($rows);
            if ($detected === null) {
                $this->failures[] = '[ERROR] Header tidak ditemukan. Pastikan file memiliki baris header (non-#).';
                $this->rowOffset += $originalSize;
                return;
            }
            [$this->headerRowIdx, $this->columnMap] = $detected;
            $rows = $rows->slice($this->headerRowIdx + 1)->values();
        }

        $map        = $this->columnMap ?? [];
        $baseOffset = $isFirstChunk ? ($this->headerRowIdx + 1) : 0;

        foreach ($rows as $index => $row) {
            $rowNum = $this->rowOffset + $index + 1 + ($isFirstChunk ? $baseOffset : 0);

            // Ambil identifier anak
            $nikAnakRaw      = (string) ($this->colVal($row, $map, 'nik_anak') ?? '');
            $namaAnakRaw     = (string) ($this->colVal($row, $map, 'nama_anak') ?? '');
            $tglLahirAnakRaw = $this->colVal($row, $map, 'tgl_lahir_anak');
            $tglLahirAnak    = $this->parseDate($tglLahirAnakRaw);

            // Skip baris tanpa minimal 2 identifier
            $idCount = (int)(!empty($nikAnakRaw)) + (int)(!empty($namaAnakRaw)) + (int)($tglLahirAnak !== null);
            if ($idCount < 2) {
                if ($idCount > 0) {
                    $this->failures[] = "[PERINGATAN] Baris {$rowNum}: Kurang dari 2 identifier — dilewati.";
                }
                continue;
            }

            $kodeVaksin = strtoupper(trim((string) ($this->colVal($row, $map, 'kode_vaksin') ?? '')));
            if (empty($kodeVaksin)) {
                $this->failures[] = "[PERINGATAN] Baris {$rowNum}: kode_vaksin kosong — dilewati.";
                continue;
            }

            try {
                // Resolve anak via 2-of-3 matching
                $result = $this->resolveAnakByTwoOfThree($nikAnakRaw, $namaAnakRaw, $tglLahirAnak);

                if ($result['warning']) {
                    $this->failures[] = "[INFO] Baris {$rowNum}: " . $result['warning'];
                }

                if ($result['anak'] === null) {
                    $label = $namaAnakRaw ?: $nikAnakRaw;
                    $this->failures[] = match ($result['match']) {
                        'ambigu'          => "[ERROR] Baris {$rowNum} ({$label}): " . $result['warning'],
                        'tidak_ditemukan' => "[ERROR] Baris {$rowNum} ({$label}): Anak tidak ditemukan. Pastikan data anak sudah diimport terlebih dahulu.",
                        default           => "[ERROR] Baris {$rowNum} ({$label}): Anak tidak ditemukan.",
                    };
                    $this->errorCount++;
                    continue;
                }

                $anak = $result['anak'];

                // Resolve jenis vaksin
                $idVaksin = $this->vaksinCache[$kodeVaksin] ?? null;
                if (!$idVaksin) {
                    $label = $namaAnakRaw ?: $nikAnakRaw;
                    $this->failures[] = "[PERINGATAN] Baris {$rowNum} ({$label}): Kode vaksin '{$kodeVaksin}' tidak ditemukan di master data — dilewati.";
                    continue;
                }

                // Upsert imunisasi
                Imunisasi::updateOrCreate(
                    ['id_anak' => $anak->id, 'id_jenis_vaksin' => $idVaksin],
                    [
                        'dosis'               => $this->parseIntVal($this->colVal($row, $map, 'dosis')) ?? 1,
                        'tanggal_pemberian'   => $this->parseDate($this->colVal($row, $map, 'tanggal_pemberian')),
                        'tanggal_selanjutnya' => $this->parseDate($this->colVal($row, $map, 'tanggal_selanjutnya')),
                        'batch_number'        => $this->colVal($row, $map, 'batch_number'),
                        'lokasi_pemberian'    => $this->colVal($row, $map, 'lokasi_pemberian'),
                        'status'              => $this->parseStatus($this->colVal($row, $map, 'status')),
                        'reaksi_kipi'         => $this->colVal($row, $map, 'reaksi_kipi'),
                        'catatan'             => $this->colVal($row, $map, 'catatan'),
                        'id_petugas'          => $this->userId,
                    ]
                );

                $this->successCount++;

            } catch (\Exception $e) {
                $label = $namaAnakRaw ?: $nikAnakRaw;
                $this->failures[] = "[ERROR] Baris {$rowNum} ({$label}, {$kodeVaksin}): " . $this->simplifyError($e->getMessage());
                $this->errorCount++;
                Log::warning("ImunisasiImport skip baris {$rowNum}: " . $e->getMessage());
            }
        }

        $this->rowOffset += $originalSize;
    }

    protected function simplifyError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Data too long')        => 'Data terlalu panjang untuk salah satu kolom.',
            str_contains($message, 'Incorrect date value') => 'Format tanggal tidak valid.',
            str_contains($message, 'Incorrect integer')    => 'Format angka tidak valid.',
            str_contains($message, 'Integrity constraint') => 'Data referensi tidak ditemukan.',
            default => mb_substr($message, 0, 120),
        };
    }

    public function getResults(): array
    {
        return [
            'success'     => $this->successCount,
            'error_count' => $this->errorCount,
            'failures'    => $this->failures,
        ];
    }
}
