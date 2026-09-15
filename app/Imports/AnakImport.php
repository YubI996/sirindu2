<?php

namespace App\Imports;

use App\Models\Anak;
use App\Services\FaskesMatcher;
use App\Services\NikDummyService;
use App\Support\ImportError;
use App\Traits\ResolvesAnakByTwoOfThree;
use App\Traits\ResolvesWilayah;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import data identitas anak dari CSV template_anak.csv.
 *
 * Upsert Anak:
 *   - Jika NIK valid → updateOrCreate by NIK.
 *   - Jika NIK tidak ada/invalid → cari existing by 2-of-3 (nama+tgl_lahir),
 *     update jika ditemukan; create baru dengan NIK dummy jika tidak ditemukan.
 * Kolom lookup wilayah: nama_kecamatan, nama_kelurahan, nama_rt (via ResolvesWilayah).
 * Kolom lookup: nama_posyandu, nama_puskesmas (cache internal).
 */
class AnakImport implements ToCollection, WithStartRow, WithChunkReading
{
    use ResolvesWilayah, ResolvesAnakByTwoOfThree;

    protected int $userId;
    protected int $successCount = 0;
    protected int $errorCount   = 0;
    protected array $failures   = [];
    protected int $rowOffset    = 0;

    protected ?array $columnMap    = null;
    protected int    $headerRowIdx = 0;

    protected FaskesMatcher $faskesMatcher;

    protected NikDummyService $nikService;

    public function __construct(int $userId)
    {
        $this->userId     = $userId;
        $this->nikService = new NikDummyService();
        $this->initWilayahCache();
        $this->faskesMatcher = new FaskesMatcher();
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

    protected function parseJk($value): int
    {
        $val = strtoupper(trim((string) ($value ?? '')));
        if (in_array($val, ['L', 'LAKI', 'LAKI-LAKI', '1'])) return 1;
        return 2; // Default perempuan
    }

    // =========================================================================
    // Cache faskes
    // =========================================================================

    // Pencocokan faskes dipindah ke App\Services\FaskesMatcher — lihat
    // $this->faskesMatcher. resolveFaskes() lama mencocokkan nama apa adanya
    // lalu jatuh ke LIKE '%nama%' GLOBAL; pola itu menaruh 23,8% baris berkas
    // Operasi Timbang Juni 2026 di id_posyandu NULL (master pakai angka Romawi,
    // berkas pakai Arab) dan menempelkan 6,5% lainnya ke posyandu milik
    // puskesmas lain tanpa error apa pun.

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

        $map = $this->columnMap ?? [];
        $baseOffset = $isFirstChunk ? ($this->headerRowIdx + 1) : 0;

        foreach ($rows as $index => $row) {
            $rowNum   = $this->rowOffset + $index + 1 + ($isFirstChunk ? $baseOffset : 0);
            $namaRaw  = trim((string) ($this->colVal($row, $map, 'nama') ?? ''));
            $nikRaw   = trim((string) ($this->colVal($row, $map, 'nik') ?? ''));
            $tglLahir = $this->parseDate($this->colVal($row, $map, 'tgl_lahir'));
            $jkInt    = $this->parseJk($this->colVal($row, $map, 'jk'));
            $jkChar   = $jkInt === 1 ? 'L' : 'P';

            if (empty($namaRaw)) continue;

            try {
                // Resolve NIK -----------------------------------------------
                $nikValid = $nikRaw !== '' && ctype_digit($nikRaw) && strlen($nikRaw) >= 15;
                $nikKey   = $nikValid ? substr($nikRaw, 0, 16) : null;

                if (!$nikValid) {
                    // Pakai jenis kelamin sebenarnya — kalau di-hardcode 'L', anak
                    // perempuan (jk=2) lolos dari dedup findExisting & NIK dummy-nya
                    // salah encode (harusnya DD+40).
                    $noKkRaw = trim((string) ($this->colVal($row, $map, 'no_kk') ?? ''));
                    $nikKey  = $this->nikService->findExisting($namaRaw, $tglLahir ?? date('Y-m-d'), $jkChar, $noKkRaw)
                        ?? $this->nikService->generate(NikDummyService::DEFAULT_KODE_WILAYAH, $tglLahir ?? date('Y-m-d'), $jkChar);

                    $msg = empty($nikRaw)
                        ? "[INFO] Baris {$rowNum} ({$namaRaw}): NIK kosong — NIK dummy {$nikKey} digenerate."
                        : "[PERINGATAN] Baris {$rowNum} ({$namaRaw}): NIK '{$nikRaw}' tidak valid — NIK dummy {$nikKey} digunakan.";
                    $this->failures[] = $msg;
                }

                // Resolve wilayah -------------------------------------------
                $kecNama = (string) ($this->colVal($row, $map, 'nama_kecamatan') ?? '');
                $kelNama = (string) ($this->colVal($row, $map, 'nama_kelurahan') ?? '');
                $rtNama  = (string) ($this->colVal($row, $map, 'nama_rt') ?? '');

                $idKec = $kecNama ? $this->resolveKecamatan($kecNama) : null;
                $idKel = $kelNama ? $this->resolveKelurahan($kelNama, $idKec) : null;
                $idRt  = $rtNama  ? $this->resolveRt($rtNama, $idKel) : null;

                $posyanduNama  = (string) ($this->colVal($row, $map, 'nama_posyandu') ?? '');
                $puskesmasNama = (string) ($this->colVal($row, $map, 'nama_puskesmas') ?? '');
                $idPosyandu    = $this->faskesMatcher->cocokkan($posyanduNama, $puskesmasNama)['id'];
                $idPuskesmas   = $this->faskesMatcher->cocokkanPuskesmas($puskesmasNama)['id'];

                // No registrasi (NOT NULL) -----------------------------------
                $noReg = (string) ($this->colVal($row, $map, 'no_registrasi') ?? '');
                if (empty($noReg)) {
                    $noReg = 'IMP-' . date('Ym') . '-' . str_pad($rowNum, 4, '0', STR_PAD_LEFT);
                }

                // Status (default 1 = aktif) ---------------------------------
                $status = $this->parseIntVal($this->colVal($row, $map, 'status')) ?? 1;

                $data = [
                    'nama'                 => $namaRaw,
                    'tgl_lahir'            => $tglLahir,
                    'jk'                   => $jkInt,
                    'no_kk'                => $this->colVal($row, $map, 'no_kk'),
                    'nik_ibu'              => $this->colVal($row, $map, 'nik_ibu'),
                    'nik_ayah'             => $this->colVal($row, $map, 'nik_ayah'),
                    'nik_ortu'             => $this->colVal($row, $map, 'nik_ibu'),
                    'nama_ibu'             => $this->colVal($row, $map, 'nama_ibu'),
                    'nama_ayah'            => $this->colVal($row, $map, 'nama_ayah'),
                    'tgl_lahir_ibu'        => $this->parseDate($this->colVal($row, $map, 'tgl_lahir_ibu')),
                    'no_hp'                => $this->colVal($row, $map, 'no_hp'),
                    'alamat'               => $this->colVal($row, $map, 'alamat'),
                    'tempat_lahir'         => $this->colVal($row, $map, 'tempat_lahir'),
                    'golda'                => $this->colVal($row, $map, 'golda'),
                    'anak'                 => $this->parseIntVal($this->colVal($row, $map, 'anak_ke')),
                    'no'                   => $noReg,
                    'status'               => $status,
                    'id_kec'               => $idKec,
                    'id_kel'               => $idKel,
                    'id_rt'                => $idRt,
                    'id_posyandu'          => $idPosyandu,
                    'id_puskesmas'         => $idPuskesmas,
                    'bbl'                  => $this->parseDecimal($this->colVal($row, $map, 'bbl_kg')),
                    'pbl'                  => $this->parseDecimal($this->colVal($row, $map, 'pbl_cm')),
                    'lk_lahir'             => $this->parseDecimal($this->colVal($row, $map, 'lk_lahir_cm')),
                    'imd'                  => $this->parseBoolean($this->colVal($row, $map, 'imd')),
                    'usia_kehamilan_lahir'  => $this->parseIntVal($this->colVal($row, $map, 'usia_kehamilan_minggu')),
                    'penolong_lahir'       => $this->colVal($row, $map, 'penolong_lahir'),
                    'komplikasi_persalinan' => $this->colVal($row, $map, 'komplikasi_persalinan'),
                    'catatan'              => $this->colVal($row, $map, 'catatan'),
                ];

                Anak::updateOrCreate(['nik' => $nikKey], $data);
                $this->successCount++;

            } catch (\Exception $e) {
                $this->failures[] = "[ERROR] Baris {$rowNum} ({$namaRaw}): " . $this->simplifyError($e->getMessage());
                $this->errorCount++;
                Log::warning("AnakImport skip baris {$rowNum}: " . $e->getMessage());
            }
        }

        $this->rowOffset += $originalSize;
    }

    protected function simplifyError(string $message): string
    {
        return ImportError::message($message);
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
