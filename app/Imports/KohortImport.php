<?php

namespace App\Imports;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data anak, kunjungan posyandu, dan imunisasi dari file Excel kohort puskesmas.
 *
 * Struktur file: sheet "balita", header di baris 1–4, data mulai baris 5.
 * Upsert anak by NIK; data_anak by (id_anak, tgl_kunjungan); imunisasi by (id_anak, id_jenis_vaksin).
 */
class KohortImport implements ToCollection, WithStartRow, WithChunkReading
{
    protected int $userId;
    protected int $successCount = 0;
    protected array $failures = [];
    protected int $rowOffset = 0;

    /** Cache wilayah — dimuat sekali di konstruktor */
    protected array $kecamatanCache = [];
    protected array $kelurahanCache = [];
    protected array $rtCache = [];

    /** Cache jenis vaksin: kode → id */
    protected array $vaksinCache = [];

    /** Column map dari baris header (baris 4) — diisi saat chunk pertama, dipakai di chunk berikutnya */
    protected ?array $columnMap = null;

    /**
     * Mapping nama header Excel → kode jenis_vaksin (VACCINE_MAP).
     * Gunakan lowercase key untuk case-insensitive matching.
     */
    const VACCINE_MAP = [
        'hb 0'           => 'HB0',
        'bcg'            => 'BCG',
        'polio 1'        => 'POLIO1',
        'dpt 1'          => 'DPT-HB-HIB1',
        'polio 2'        => 'POLIO2',
        'pcv 1'          => 'PCV1',
        'rotavirus 1'    => 'RV1',
        'dpt 2'          => 'DPT-HB-HIB2',
        'polio 3'        => 'POLIO3',
        'pcv 2'          => 'PCV2',
        'rotavirus 2'    => 'RV2',
        'dpt 3'          => 'DPT-HB-HIB3',
        'polio 4'        => 'POLIO4',
        'ipv 1'          => 'IPV',
        'rotavirus 3'    => 'RV3',
        'campak'         => 'CAMPAK',
        'ipv 2'          => 'IPV2',
        'pcv 3'          => 'PCV3',
        'dpt booster'    => 'DPT-HB-HIB4',
        'campak booster' => 'MR2',
    ];

    public function __construct(int $userId)
    {
        $this->userId = $userId;

        // Pra-muat cache wilayah
        $this->kecamatanCache = Kecamatan::pluck('id', 'name')->mapWithKeys(
            fn($id, $name) => [strtoupper($name) => $id]
        )->toArray();

        $this->kelurahanCache = Kelurahan::pluck('id', 'name')->mapWithKeys(
            fn($id, $name) => [strtoupper($name) => $id]
        )->toArray();

        // Pra-muat cache vaksin: kode → id
        $this->vaksinCache = JenisVaksin::pluck('id', 'kode')->toArray();
    }

    public function startRow(): int
    {
        // Baris 4 adalah header, data mulai baris 5.
        // WithStartRow(4) agar chunk pertama berisi baris header (index 0) + data (index 1+).
        return 4;
    }

    public function chunkSize(): int
    {
        return 200;
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (is_string($value) && str_starts_with($value, '#')) return null;
        if (is_numeric($value)) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject((float) $value))->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseBoolean($value): ?bool
    {
        if ($value === null || $value === '') return null;
        return in_array(strtolower(trim((string) $value)), ['ya', 'y', 'yes', 'true', '1']);
    }

    protected function parseDecimalOrNull($value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_string($value) && str_contains($value, '#')) return null;
        return is_numeric($value) ? (float) $value : null;
    }

    protected function parseIntOrNull($value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function resolveKecamatan(string $name): ?int
    {
        $key = strtoupper(trim($name));
        if (empty($key)) return null;

        if (!isset($this->kecamatanCache[$key])) {
            $kec = Kecamatan::firstOrCreate(['name' => ucwords(strtolower(trim($name)))]);
            $this->kecamatanCache[$key] = $kec->id;
        }

        return $this->kecamatanCache[$key];
    }

    protected function resolveKelurahan(string $name, ?int $idKec): ?int
    {
        $key = strtoupper(trim($name));
        if (empty($key)) return null;

        if (!isset($this->kelurahanCache[$key])) {
            $attrs = ['name' => ucwords(strtolower(trim($name)))];
            if ($idKec) $attrs['id_kecamatan'] = $idKec;

            $kel = Kelurahan::firstOrCreate($attrs);
            $this->kelurahanCache[$key] = $kel->id;
        }

        return $this->kelurahanCache[$key];
    }

    protected function resolveRt(string $name, ?int $idKel): ?int
    {
        $key = strtoupper(trim($name));
        if (empty($key) || !$idKel) return null;

        $cacheKey = $key . '_' . $idKel;
        if (!array_key_exists($cacheKey, $this->rtCache)) {
            $rt = Rt::where('id_kelurahan', $idKel)
                ->where('name', 'like', '%' . trim($name) . '%')
                ->first();
            $this->rtCache[$cacheKey] = $rt?->id;
        }

        return $this->rtCache[$cacheKey];
    }

    /**
     * Deteksi kolom dari baris header (baris 4 Excel = index 0 dalam chunk pertama).
     *
     * Mengembalikan:
     * - vaccine_columns: array [colIndex => kodeVaksin]
     * - alasan_col: int|null (index kolom "Alasan tidak imunisasi")
     * - month_cols: array [monthIndex => startColIndex] (12 bulan, tiap bulan offset dari Tgl posy)
     * - month_extra: array [monthIndex => [colName => colIndex]] (Vit A, POPM, Taburia, makanan)
     */
    protected function detectColumns(Collection $headerRow): array
    {
        $vaccineColumns = [];
        $alasanCol = null;
        $monthTglCols = [];   // indeks kolom "Tgl posy" untuk tiap bulan
        $monthExtra = [];     // kolom ekstra per bulan (Vit A, POPM, Taburia, makanan)
        $tglPosyCount = 0;    // counter berapa kali kita temui "Tgl posy"

        foreach ($headerRow as $idx => $header) {
            $headerLower = strtolower(trim((string) $header));

            // Deteksi kolom bulan: "tgl posy" atau "tanggal posyandu"
            if (in_array($headerLower, ['tgl posy', 'tanggal posyandu', 'tgl. posy'])) {
                $monthTglCols[$tglPosyCount] = $idx;
                $monthExtra[$tglPosyCount] = [];
                $tglPosyCount++;
                continue;
            }

            // Deteksi kolom vaksin dari VACCINE_MAP
            if (isset(self::VACCINE_MAP[$headerLower])) {
                $vaccineColumns[$idx] = self::VACCINE_MAP[$headerLower];
                continue;
            }

            // Deteksi "Alasan tidak imunisasi"
            if (str_contains($headerLower, 'alasan')) {
                $alasanCol = $idx;
                continue;
            }

            // Kolom ekstra per bulan (Vit A, POPM, Taburia, makanan pokok, dll)
            if ($tglPosyCount > 0) {
                $monthIdx = $tglPosyCount - 1;
                if (in_array($headerLower, ['vit a', 'vitamin a', 'vit. a'])) {
                    $monthExtra[$monthIdx]['vit_a'] = $idx;
                } elseif (in_array($headerLower, ['popm', 'obat pencegahan massal'])) {
                    $monthExtra[$monthIdx]['popm'] = $idx;
                } elseif (in_array($headerLower, ['taburia', 'taburi'])) {
                    $monthExtra[$monthIdx]['taburia'] = $idx;
                } elseif (in_array($headerLower, ['makanan pokok', 'mkn pokok'])) {
                    $monthExtra[$monthIdx]['makanan_pokok'] = $idx;
                } elseif (in_array($headerLower, ['kacang', 'mkn kacang', 'kacang-kacangan'])) {
                    $monthExtra[$monthIdx]['mkn_kacang'] = $idx;
                } elseif (in_array($headerLower, ['susu', 'mkn susu'])) {
                    $monthExtra[$monthIdx]['mkn_susu'] = $idx;
                } elseif (in_array($headerLower, ['daging', 'mkn daging', 'daging/unggas'])) {
                    $monthExtra[$monthIdx]['mkn_daging'] = $idx;
                } elseif (in_array($headerLower, ['telur', 'mkn telur'])) {
                    $monthExtra[$monthIdx]['mkn_telur'] = $idx;
                } elseif (str_contains($headerLower, 'vit a') && str_contains($headerLower, 'buah')) {
                    $monthExtra[$monthIdx]['mkn_buah_vita'] = $idx;
                } elseif (str_contains($headerLower, 'buah') && str_contains($headerLower, 'lain')) {
                    $monthExtra[$monthIdx]['mkn_buah_lain'] = $idx;
                }
            }
        }

        return [
            'vaccine_columns' => $vaccineColumns,
            'alasan_col'      => $alasanCol,
            'month_tgl_cols'  => $monthTglCols,
            'month_extra'     => $monthExtra,
        ];
    }

    // =========================================================================
    // Main collection processor
    // =========================================================================

    public function collection(Collection $rows)
    {
        // Baris pertama chunk pertama adalah header (baris 4 Excel)
        $isFirstChunk = $this->rowOffset === 0;
        $columnMap = null;

        if ($isFirstChunk) {
            // Baris pertama = header, detect columns dari sini
            $headerRow = $rows->first();
            $columnMap = $this->detectColumns($headerRow);

            // Simpan column map di instance untuk chunk berikutnya
            $this->columnMap = $columnMap;

            // Hapus baris header dari rows yang akan diproses
            $rows = $rows->slice(1)->values();
        } else {
            $columnMap = $this->columnMap ?? ['vaccine_columns' => [], 'alasan_col' => null, 'month_tgl_cols' => [], 'month_extra' => []];
        }

        $vaccineColumns = $columnMap['vaccine_columns'];
        $alasanCol      = $columnMap['alasan_col'];
        $monthTglCols   = $columnMap['month_tgl_cols'];
        $monthExtra     = $columnMap['month_extra'];

        $chunkSize = count($rows);

        foreach ($rows as $index => $row) {
            // Skip baris jika NIK (index 1) DAN nama (index 2) keduanya kosong
            $nik  = trim((string) ($row[1] ?? ''));
            $nama = trim((string) ($row[2] ?? ''));

            if (empty($nik) && empty($nama)) {
                continue;
            }

            // rowNum: offset + posisi dalam chunk + startRow (4 header) + 1 baris header dalam chunk pertama
            $rowNum = $this->rowOffset + $index + $this->startRow() + ($isFirstChunk ? 1 : 0);

            try {
                // =============================================================
                // US1: Upsert Anak (identitas)
                // =============================================================
                $nikKey = !empty($nik) ? $nik : ('TEMP-' . uniqid());

                if (empty($nik)) {
                    $this->failures[] = "Peringatan baris {$rowNum} (Nama: {$nama}): NIK kosong — data disimpan dengan kunci sementara, tidak bisa diperbarui via import ulang.";
                }

                $jk = strtoupper(trim((string) ($row[4] ?? '')));
                // jk kolom INT: 1 = Laki-laki, 0 = Perempuan (sesuai z-score lookup)
                $jkValue = in_array($jk, ['L', 'LAKI', 'LAKI-LAKI']) ? 1 : 0;

                $imd = $this->parseBoolean($row[18] ?? null);

                // Resolve wilayah (hanya RT — lookup, tidak auto-create)
                // Kecamatan/Kelurahan tidak ada di kohort, jadi null
                $rtName = trim((string) ($row[13] ?? ''));
                $idRt = null;
                if (!empty($rtName)) {
                    // Cari RT tanpa filter kelurahan (kohort tidak punya kolom kelurahan)
                    $cacheKey = strtoupper($rtName);
                    if (!array_key_exists($cacheKey, $this->rtCache)) {
                        $rt = Rt::where('name', 'like', '%' . $rtName . '%')->first();
                        $this->rtCache[$cacheKey] = $rt?->id;
                    }
                    $idRt = $this->rtCache[$cacheKey];
                }

                $anak = Anak::updateOrCreate(
                    ['nik' => $nikKey],
                    [
                        'nama'                   => $nama ?: null,
                        'tgl_lahir'              => $this->parseDate($row[3] ?? null),
                        'jk'                     => $jkValue,
                        'no_kk'                  => !empty($row[5]) ? (string) $row[5] : null,
                        'nik_ayah'               => !empty($row[6]) ? (string) $row[6] : null,
                        'nama_ayah'              => !empty($row[7]) ? (string) $row[7] : null,
                        'nik_ibu'                => !empty($row[8]) ? (string) $row[8] : null,
                        'nik_ortu'               => !empty($row[8]) ? (string) $row[8] : null,
                        'nama_ibu'               => !empty($row[9]) ? (string) $row[9] : null,
                        'tgl_lahir_ibu'          => $this->parseDate($row[10] ?? null),
                        'no_hp'                  => !empty($row[11]) ? (string) $row[11] : null,
                        'alamat'                 => !empty($row[12]) ? (string) $row[12] : null,
                        'id_rt'                  => $idRt,
                        'anak'                   => $this->parseIntOrNull($row[14] ?? null),
                        'bbl'                    => $this->parseDecimalOrNull($row[15] ?? null),
                        'pbl'                    => $this->parseDecimalOrNull($row[16] ?? null),
                        'lk_lahir'               => $this->parseDecimalOrNull($row[17] ?? null),
                        'imd'                    => $imd,
                        'usia_kehamilan_lahir'   => $this->parseIntOrNull($row[19] ?? null),
                        'tempat_lahir'           => !empty($row[20]) ? (string) $row[20] : null,
                        'penolong_lahir'         => !empty($row[21]) ? (string) $row[21] : null,
                        'komplikasi_persalinan'  => !empty($row[22]) ? (string) $row[22] : null,
                        'status'                 => 1,
                    ]
                );

                // =============================================================
                // US2: Upsert DataAnak (kunjungan posyandu per bulan)
                // =============================================================
                foreach ($monthTglCols as $monthIdx => $tglCol) {
                    $tglPosy = $this->parseDate($row[$tglCol] ?? null);
                    if (!$tglPosy) continue;  // Skip bulan tanpa tanggal posyandu

                    // Offset kolom dalam grup bulan (relatif dari Tgl posy)
                    $base = $tglCol;
                    $extra = $monthExtra[$monthIdx] ?? [];

                    DataAnak::updateOrCreate(
                        ['id_anak' => $anak->id, 'tgl_kunjungan' => $tglPosy],
                        [
                            // NOT NULL tanpa default — fallback ke 0/'L' jika kosong
                            'bln'         => $this->parseIntOrNull($row[$base + 1] ?? null) ?? 0,
                            'lk'          => $this->parseDecimalOrNull($row[$base + 2] ?? null) ?? 0,
                            'lla'         => $this->parseDecimalOrNull($row[$base + 4] ?? null) ?? 0,
                            'bb'          => $this->parseDecimalOrNull($row[$base + 6] ?? null) ?? 0,
                            'tb'          => $this->parseDecimalOrNull($row[$base + 7] ?? null) ?? 0,
                            'posisi'      => !empty($row[$base + 10]) ? (string) $row[$base + 10] : 'L',
                            'id_user'     => $this->userId,
                            // Nullable fields
                            'hasil_lk'    => !empty($row[$base + 3]) ? (string) $row[$base + 3] : null,
                            'hasil_lila'  => !empty($row[$base + 5]) ? (string) $row[$base + 5] : null,
                            'zscore_bb_u' => $this->parseDecimalOrNull($row[$base + 8] ?? null),
                            'zscore_pb_u' => $this->parseDecimalOrNull($row[$base + 9] ?? null),
                            'zscore_bb_pb'=> $this->parseDecimalOrNull($row[$base + 11] ?? null),
                            'pb_meter'    => $this->parseDecimalOrNull($row[$base + 12] ?? null),
                            'imt'         => $this->parseDecimalOrNull($row[$base + 13] ?? null),
                            'imt_u'       => $this->parseDecimalOrNull($row[$base + 14] ?? null),
                            'asi'         => $this->parseBoolean($row[$base + 15] ?? null) ? 1 : 0,
                            'rujuk'       => $this->parseBoolean($row[$base + 16] ?? null),
                            // Kolom ekstra yang ada di bulan tertentu (detected by header)
                            'vit_a'       => isset($extra['vit_a']) ? ($this->parseBoolean($row[$extra['vit_a']] ?? null) ? 1 : 0) : null,
                            'popm'        => isset($extra['popm']) ? $this->parseBoolean($row[$extra['popm']] ?? null) : null,
                            'taburia'     => isset($extra['taburia']) ? $this->parseBoolean($row[$extra['taburia']] ?? null) : null,
                            'makanan_pokok' => isset($extra['makanan_pokok']) ? $this->parseBoolean($row[$extra['makanan_pokok']] ?? null) : null,
                            'mkn_kacang'  => isset($extra['mkn_kacang']) ? $this->parseBoolean($row[$extra['mkn_kacang']] ?? null) : null,
                            'mkn_susu'    => isset($extra['mkn_susu']) ? $this->parseBoolean($row[$extra['mkn_susu']] ?? null) : null,
                            'mkn_daging'  => isset($extra['mkn_daging']) ? $this->parseBoolean($row[$extra['mkn_daging']] ?? null) : null,
                            'mkn_telur'   => isset($extra['mkn_telur']) ? $this->parseBoolean($row[$extra['mkn_telur']] ?? null) : null,
                            'mkn_buah_vita' => isset($extra['mkn_buah_vita']) ? $this->parseBoolean($row[$extra['mkn_buah_vita']] ?? null) : null,
                            'mkn_buah_lain' => isset($extra['mkn_buah_lain']) ? $this->parseBoolean($row[$extra['mkn_buah_lain']] ?? null) : null,
                        ]
                    );
                }

                // =============================================================
                // US3: Upsert Imunisasi (per vaksin)
                // =============================================================
                $alasanTidakImunisasi = ($alasanCol !== null && !empty($row[$alasanCol]))
                    ? (string) $row[$alasanCol]
                    : null;

                foreach ($vaccineColumns as $colIdx => $kodeVaksin) {
                    $tglImunisasi = $this->parseDate($row[$colIdx] ?? null);
                    if (!$tglImunisasi) continue;  // Vaksin kosong → tidak buat record

                    $idVaksin = $this->vaksinCache[$kodeVaksin] ?? null;
                    if (!$idVaksin) {
                        $this->failures[] = "Peringatan baris {$rowNum}: Kode vaksin '{$kodeVaksin}' tidak ditemukan di database — imunisasi ini dilewati.";
                        continue;
                    }

                    Imunisasi::updateOrCreate(
                        ['id_anak' => $anak->id, 'id_jenis_vaksin' => $idVaksin],
                        [
                            'status'             => 'sudah',
                            'tanggal_pemberian'  => $tglImunisasi,
                            'catatan'            => null,
                        ]
                    );
                }

                // Simpan alasan tidak imunisasi ke catatan anak jika ada
                if ($alasanTidakImunisasi) {
                    $anak->update(['catatan' => $alasanTidakImunisasi]);
                }

                $this->successCount++;

            } catch (\Exception $e) {
                $errMsg = $this->simplifyError($e->getMessage());
                $nikLabel  = $nik ?: '(kosong)';
                $namaLabel = $nama ?: '(kosong)';
                $this->failures[] = "Baris {$rowNum} (NIK: {$nikLabel}, Nama: {$namaLabel}): {$errMsg}";
                Log::warning("KohortImport skip baris {$rowNum}: " . $e->getMessage());
            }
        }

        $this->rowOffset += $chunkSize + ($isFirstChunk ? 1 : 0);
    }

    protected function simplifyError(string $message): string
    {
        // Coba ekstrak nama kolom dan nilai dari pesan MySQL (Incorrect integer/date/Data too long)
        // Contoh: "Incorrect integer value: 'xyz' for column 'anak' at row 1"
        // Contoh: "Data too long for column 'komplikasi_persalinan' at row 1"
        $extractColumn = function (string $msg): string {
            if (preg_match("/for column '([^']+)'/i", $msg, $m)) {
                return " (kolom: {$m[1]})";
            }
            return '';
        };

        $extractValue = function (string $msg): string {
            if (preg_match("/value:\s*'([^']*)'/i", $msg, $m)) {
                $val = mb_substr($m[1], 0, 30);
                return " — nilai: '{$val}'";
            }
            return '';
        };

        return match (true) {
            str_contains($message, 'Data too long') =>
                'Data terlalu panjang' . $extractColumn($message) . '.',

            str_contains($message, 'Incorrect date value') =>
                'Format tanggal tidak valid' . $extractColumn($message) . $extractValue($message) . '.',

            str_contains($message, 'Incorrect integer') || str_contains($message, 'Incorrect decimal') =>
                'Format angka tidak valid' . $extractColumn($message) . $extractValue($message) . '.',

            str_contains($message, 'Integrity constraint') =>
                'Data referensi tidak ditemukan di sistem' . $extractColumn($message) . '.',

            str_contains($message, 'ENUM') =>
                'Nilai pilihan tidak valid' . $extractColumn($message) . $extractValue($message) . '.',

            default => 'Gagal menyimpan' . $extractColumn($message) . ' — ' . mb_substr($message, 0, 120),
        };
    }

    public function getResults(): array
    {
        return [
            'success'  => $this->successCount,
            'failures' => $this->failures,
        ];
    }
}
