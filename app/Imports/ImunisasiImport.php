<?php

namespace App\Imports;

use App\Models\Anak;
use App\Models\DataAnak;
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
 * Import data imunisasi dari CSV. Dua format dideteksi otomatis dari header:
 *  - LONG : ada kolom `kode_vaksin` → 1 baris = 1 vaksin per anak (format lama).
 *  - WIDE : tiap vaksin jadi kolom berisi tanggal pemberian; kolom opsional
 *           `alasan_tidak_imunisasi` ditulis ke data_anak.
 *
 * Identifikasi anak: logika 2-dari-3 (NIK, nama, tgl_lahir).
 * Upsert Imunisasi by (id_anak, id_jenis_vaksin).
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

    /** Mode wide (true) / long (false). Ditentukan sekali di chunk pertama, persist antar-chunk. */
    protected bool $wideMode = false;

    /** Mode wide: peta kolom vaksin -> header_lowercase => id_jenis_vaksin. */
    protected array $vaksinColumns = [];

    /** Cache kode_vaksin (UPPERCASE) → id_jenis_vaksin */
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
    // Main processor — deteksi format & dispatch
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
            // Header dengan kolom kode_vaksin = format long lama; selain itu wide.
            $this->wideMode = !array_key_exists('kode_vaksin', $this->columnMap);
            if ($this->wideMode) {
                $this->vaksinColumns = $this->detectVaksinColumns($this->columnMap);
            }
            $rows = $rows->slice($this->headerRowIdx + 1)->values();
        }

        $map        = $this->columnMap ?? [];
        $baseOffset = $isFirstChunk ? ($this->headerRowIdx + 1) : 0;

        foreach ($rows as $index => $row) {
            $rowNum = $this->rowOffset + $index + 1 + ($isFirstChunk ? $baseOffset : 0);
            if ($this->wideMode) {
                $this->processWideRow($row, $map, $rowNum);
            } else {
                $this->processLongRow($row, $map, $rowNum);
            }
        }

        $this->rowOffset += $originalSize;
    }

    // =========================================================================
    // LONG — format lama (dipindah dari collection, logika tak berubah)
    // =========================================================================

    protected function processLongRow($row, array $map, int $rowNum): void
    {
        $nikAnakRaw      = (string) ($this->colVal($row, $map, 'nik_anak') ?? '');
        $namaAnakRaw     = (string) ($this->colVal($row, $map, 'nama_anak') ?? '');
        $tglLahirAnakRaw = $this->colVal($row, $map, 'tgl_lahir_anak');
        $tglLahirAnak    = $this->parseDate($tglLahirAnakRaw);

        $idCount = (int)(!empty($nikAnakRaw)) + (int)(!empty($namaAnakRaw)) + (int)($tglLahirAnak !== null);
        if ($idCount < 2) {
            if ($idCount > 0) {
                $this->failures[] = "[PERINGATAN] Baris {$rowNum}: Kurang dari 2 identifier — dilewati.";
            }
            return;
        }

        $kodeVaksin = strtoupper(trim((string) ($this->colVal($row, $map, 'kode_vaksin') ?? '')));
        if (empty($kodeVaksin)) {
            $this->failures[] = "[PERINGATAN] Baris {$rowNum}: kode_vaksin kosong — dilewati.";
            return;
        }

        try {
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
                return;
            }

            $anak = $result['anak'];

            $idVaksin = $this->vaksinCache[$kodeVaksin] ?? null;
            if (!$idVaksin) {
                $label = $namaAnakRaw ?: $nikAnakRaw;
                $this->failures[] = "[PERINGATAN] Baris {$rowNum} ({$label}): Kode vaksin '{$kodeVaksin}' tidak ditemukan di master data — dilewati.";
                return;
            }

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

    // =========================================================================
    // WIDE — format baru
    // =========================================================================

    /** Dari columnMap, ambil kolom yang headernya cocok kode vaksin master. */
    protected function detectVaksinColumns(array $map): array
    {
        $cols = [];
        foreach ($map as $key => $idx) {
            $kode = strtoupper(trim((string) $key));
            if (isset($this->vaksinCache[$kode])) {
                $cols[$key] = $this->vaksinCache[$kode]; // key = header lowercase (key di columnMap)
            }
        }
        return $cols;
    }

    protected function processWideRow($row, array $map, int $rowNum): void
    {
        $nikAnakRaw      = (string) ($this->colVal($row, $map, 'nik_anak') ?? '');
        $namaAnakRaw     = (string) ($this->colVal($row, $map, 'nama_anak') ?? '');
        $tglLahirAnakRaw = $this->colVal($row, $map, 'tgl_lahir_anak');
        $tglLahirAnak    = $this->parseDate($tglLahirAnakRaw);

        $idCount = (int)(!empty($nikAnakRaw)) + (int)(!empty($namaAnakRaw)) + (int)($tglLahirAnak !== null);
        if ($idCount < 2) {
            if ($idCount > 0) {
                $this->failures[] = "[PERINGATAN] Baris {$rowNum}: Kurang dari 2 identifier — dilewati.";
            }
            return;
        }

        try {
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
                return;
            }

            $anak = $result['anak'];

            // Upsert tiap vaksin yang selnya berisi tanggal valid. Sel kosong dilewati.
            foreach ($this->vaksinColumns as $key => $idVaksin) {
                $tgl = $this->parseDate($this->colVal($row, $map, $key));
                if ($tgl === null) continue;
                Imunisasi::updateOrCreate(
                    ['id_anak' => $anak->id, 'id_jenis_vaksin' => $idVaksin],
                    [
                        'tanggal_pemberian' => $tgl,
                        'status'            => 'sudah',
                        'id_petugas'        => $this->userId,
                    ]
                );
                $this->successCount++;
            }

            // Kolom trailing alasan_tidak_imunisasi → tulis ke data_anak.
            $alasan = $this->colVal($row, $map, 'alasan_tidak_imunisasi');
            if (!empty($alasan)) {
                $this->writeAlasanTidakImunisasi($anak, trim((string) $alasan));
            }

        } catch (\Exception $e) {
            $label = $namaAnakRaw ?: $nikAnakRaw;
            $this->failures[] = "[ERROR] Baris {$rowNum} ({$label}): " . $this->simplifyError($e->getMessage());
            $this->errorCount++;
            Log::warning("ImunisasiImport wide skip baris {$rowNum}: " . $e->getMessage());
        }
    }

    /**
     * Tulis alasan ke data_anak kunjungan terakhir anak. Bila anak belum punya
     * data_anak, buat baris minimal (bb/tb/lla/lk = 0) — query stunting memfilter
     * bb>0 AND tb>0 sehingga baris ini tidak mencemari prevalensi.
     */
    protected function writeAlasanTidakImunisasi(Anak $anak, string $alasan): void
    {
        $latest = DataAnak::where('id_anak', $anak->id)
            ->orderByDesc('tgl_kunjungan')
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $latest->update(['alasan_tidak_imunisasi' => $alasan]);
            return;
        }

        $today = Carbon::today();
        $bln   = $anak->tgl_lahir
            ? (int) abs(Carbon::parse($anak->tgl_lahir)->diffInMonths($today))
            : 0;

        DataAnak::create([
            'id_anak'                => $anak->id,
            'tgl_kunjungan'          => $today->format('Y-m-d'),
            'bln'                    => $bln,
            'posisi'                 => $bln < 24 ? 'terlentang' : 'berdiri',
            'tb'                     => 0,
            'bb'                     => 0,
            'lla'                    => 0,
            'lk'                     => 0,
            'id_user'                => $this->userId,
            'alasan_tidak_imunisasi' => $alasan,
        ]);
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
