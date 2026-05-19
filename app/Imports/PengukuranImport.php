<?php

namespace App\Imports;

use App\Models\DataAnak;
use App\Traits\ResolvesAnakByTwoOfThree;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data pengukuran berkala (DataAnak) dari CSV template_pengukuran_berkala.csv.
 *
 * Identifikasi anak menggunakan logika 2-dari-3: NIK, nama, tgl_lahir.
 * Upsert DataAnak by (id_anak, tgl_kunjungan).
 * Kolom zscore/hasil bersifat opsional — dikosongkan jika tidak diisi di CSV.
 */
class PengukuranImport implements ToCollection, WithStartRow, WithChunkReading
{
    use ResolvesAnakByTwoOfThree;

    protected int $userId;
    protected int $successCount = 0;
    protected int $errorCount   = 0;
    protected array $failures   = [];
    protected int $rowOffset    = 0;

    protected ?array $columnMap    = null;
    protected int    $headerRowIdx = 0;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function startRow(): int { return 1; }
    public function chunkSize(): int { return 200; }

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

    protected function parseBoolean($value): ?bool
    {
        if ($value === null || $value === '') return null;
        return in_array(strtolower(trim((string) $value)), ['ya', 'y', 'yes', 'true', '1']);
    }

    protected function parseBoolInt($value): ?int
    {
        $b = $this->parseBoolean($value);
        return $b === null ? null : ($b ? 1 : 0);
    }

    protected function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (float) $value : null;
    }

    protected function parseIntVal($value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function parseShortStr($value, int $max = 100): ?string
    {
        if ($value === null || $value === '') return null;
        $str = trim((string) $value);
        return $str === '' ? null : mb_substr($str, 0, $max);
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
            $nikAnakRaw   = (string) ($this->colVal($row, $map, 'nik_anak') ?? '');
            $namaAnakRaw  = (string) ($this->colVal($row, $map, 'nama_anak') ?? '');
            $tglLahirAnakRaw = $this->colVal($row, $map, 'tgl_lahir_anak');
            $tglLahirAnak = $this->parseDate($tglLahirAnakRaw);

            // Skip baris tanpa minimal 2 identifier
            $idCount = (int)(!empty($nikAnakRaw)) + (int)(!empty($namaAnakRaw)) + (int)($tglLahirAnak !== null);
            if ($idCount < 2) {
                if ($idCount > 0) {
                    $this->failures[] = "[PERINGATAN] Baris {$rowNum}: Kurang dari 2 identifier (nik_anak/nama_anak/tgl_lahir_anak) — dilewati.";
                }
                continue;
            }

            $tglKunjungan = $this->parseDate($this->colVal($row, $map, 'tgl_kunjungan'));
            if (!$tglKunjungan) {
                $this->failures[] = "[PERINGATAN] Baris {$rowNum} ({$namaAnakRaw}): tgl_kunjungan kosong atau tidak valid — dilewati.";
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

                // Bangun data DataAnak
                DataAnak::updateOrCreate(
                    ['id_anak' => $anak->id, 'tgl_kunjungan' => $tglKunjungan],
                    [
                        'bln'            => $this->parseIntVal($this->colVal($row, $map, 'bln')) ?? 0,
                        'posisi'         => $this->parseShortStr($this->colVal($row, $map, 'posisi'), 10) ?? 'L',
                        'bb'             => $this->parseDecimal($this->colVal($row, $map, 'bb_kg')) ?? 0,
                        'tb'             => $this->parseDecimal($this->colVal($row, $map, 'tb_cm')) ?? 0,
                        'lla'            => $this->parseDecimal($this->colVal($row, $map, 'lla_cm')) ?? 0,
                        'lk'             => $this->parseDecimal($this->colVal($row, $map, 'lk_cm')) ?? 0,
                        'ntob'           => $this->parseShortStr($this->colVal($row, $map, 'ntob'), 255),
                        'ddtka'          => $this->parseShortStr($this->colVal($row, $map, 'ddtka'), 255),
                        'asi'            => $this->parseBoolInt($this->colVal($row, $map, 'asi')) ?? 0,
                        'vit_a'          => $this->parseBoolInt($this->colVal($row, $map, 'vit_a')),
                        'obat_cacing'    => $this->parseBoolInt($this->colVal($row, $map, 'obat_cacing')),
                        'rujuk'          => $this->parseBoolean($this->colVal($row, $map, 'rujuk')),
                        'taburia'        => $this->parseBoolean($this->colVal($row, $map, 'taburia')),
                        'popm'           => $this->parseBoolean($this->colVal($row, $map, 'popm')),
                        'garam_yodium'   => $this->parseBoolean($this->colVal($row, $map, 'garam_yodium')),
                        'makanan_pokok'  => $this->parseBoolean($this->colVal($row, $map, 'makanan_pokok')),
                        'mkn_kacang'     => $this->parseBoolean($this->colVal($row, $map, 'mkn_kacang')),
                        'mkn_susu'       => $this->parseBoolean($this->colVal($row, $map, 'mkn_susu')),
                        'mkn_daging'     => $this->parseBoolean($this->colVal($row, $map, 'mkn_daging')),
                        'mkn_telur'      => $this->parseBoolean($this->colVal($row, $map, 'mkn_telur')),
                        'mkn_buah_vita'  => $this->parseBoolean($this->colVal($row, $map, 'mkn_buah_vita')),
                        'mkn_buah_lain'  => $this->parseBoolean($this->colVal($row, $map, 'mkn_buah_lain')),
                        'hasil_lk'       => $this->parseShortStr($this->colVal($row, $map, 'hasil_lk'), 30),
                        'hasil_lila'     => $this->parseShortStr($this->colVal($row, $map, 'hasil_lila'), 100),
                        'zscore_bb_u'    => $this->parseDecimal($this->colVal($row, $map, 'zscore_bb_u')),
                        'zscore_pb_u'    => $this->parseDecimal($this->colVal($row, $map, 'zscore_pb_u')),
                        'zscore_bb_pb'   => $this->parseDecimal($this->colVal($row, $map, 'zscore_bb_pb')),
                        'imt'            => $this->parseDecimal($this->colVal($row, $map, 'imt')),
                        'imt_u'          => $this->parseDecimal($this->colVal($row, $map, 'imt_u')),
                        'id_user'        => $this->userId,
                    ]
                );

                $this->successCount++;

            } catch (\Exception $e) {
                $label = $namaAnakRaw ?: $nikAnakRaw;
                $this->failures[] = "[ERROR] Baris {$rowNum} ({$label}): " . $this->simplifyError($e->getMessage());
                $this->errorCount++;
                Log::warning("PengukuranImport skip baris {$rowNum}: " . $e->getMessage());
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
