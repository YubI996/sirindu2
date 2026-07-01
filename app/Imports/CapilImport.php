<?php

namespace App\Imports;

use App\Models\Anak;
use App\Services\NikDummyService;
use App\Traits\ResolvesAnakByTwoOfThree;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Import data kependudukan dari Dukcapil (Capil) — format Excel (.xlsx).
 *
 * Merge: kependudukan ikut Capil (bila sel terisi), kesehatan & domisili ikut
 * sigizi. Matching via ResolvesAnakByTwoOfThree (NIK-exact dulu, lalu nama+tgl).
 * Baris tanpa padanan → anak baru dengan domisili kosong + penanda di catatan.
 */
class CapilImport implements ToCollection, WithStartRow, WithChunkReading, WithMultipleSheets
{
    use ResolvesAnakByTwoOfThree;

    protected int $userId;
    protected string $importDate;            // 'Y-m-d' untuk penanda catatan
    protected int|string $sheet;             // indeks/nama sheet yang diproses
    protected int $successCount = 0;
    protected int $createdCount = 0;
    protected int $updatedCount = 0;
    protected int $errorCount   = 0;
    protected array $failures   = [];
    protected int $rowOffset    = 0;

    protected ?array $columnMap = null;
    protected int $headerRowIdx = 0;
    protected ?string $usiaAcuan = null;     // 'Y-m-d' dari "USIA BLN PER dd-mm-yyyy"

    protected NikDummyService $nikService;

    public function __construct(int $userId, ?string $importDate = null, int|string $sheet = 0)
    {
        $this->userId     = $userId;
        $this->importDate = $importDate ?? Carbon::today()->format('Y-m-d');
        $this->sheet      = $sheet;
        $this->nikService = new NikDummyService();
    }

    /**
     * Hanya proses SATU sheet. Tanpa ini, Maatwebsite memberi makan SEMUA sheet
     * (termasuk yang tersembunyi) ke import yang sama — menggandakan baris.
     * Default sheet pertama; bisa di-override dengan indeks atau nama sheet.
     */
    public function sheets(): array { return [$this->sheet => $this]; }

    public function startRow(): int { return 1; }
    public function chunkSize(): int { return 500; }

    // =========================================================================
    // Penjaga sheet — cegah sheet tersembunyi merusak impor (lihat insiden 2026-06-26)
    // =========================================================================

    /**
     * Daftar sheet (nama + visibilitas) tanpa memuat sel data — murah.
     * Read filter menolak semua sel; visibilitas tetap terbaca dari workbook.xml.
     *
     * @return array<int, array{index:int, name:string, visibility:string}>
     */
    public static function inspectSheets(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        $reader->setReadFilter(new class implements IReadFilter {
            public function readCell($columnAddress, $row, $worksheetName = ''): bool { return false; }
        });

        $ss = $reader->load($path);
        $sheets = [];
        foreach ($ss->getAllSheets() as $i => $s) {
            $sheets[] = [
                'index'      => $i,
                'name'       => $s->getTitle(),
                'visibility' => $s->getSheetState(),
            ];
        }
        $ss->disconnectWorksheets();

        return $sheets;
    }

    /** Nama sheet pertama yang terlihat; fallback ke sheet pertama bila semua tersembunyi. */
    public static function firstVisibleSheet(array $sheets): int|string
    {
        foreach ($sheets as $s) {
            if (($s['visibility'] ?? '') === Worksheet::SHEETSTATE_VISIBLE) {
                return $s['name'];
            }
        }
        return $sheets[0]['name'] ?? 0;
    }

    /** Peringatan bila file punya >1 sheet; null bila tunggal. */
    public static function sheetWarning(array $sheets, int|string $target): ?string
    {
        if (count($sheets) <= 1) return null;

        $list = implode(', ', array_map(
            fn ($s) => "{$s['name']} [{$s['visibility']}]",
            $sheets
        ));

        return "[PERINGATAN] File berisi " . count($sheets)
            . " sheet ({$list}). Hanya sheet \"{$target}\" yang diimpor.";
    }

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

    /** "L"/"LAKI"/"LAKI-LAKI"/"1" → 1; "P"/lainnya → 2; kosong → null (jangan timpa). */
    protected function parseJkOrNull($value): ?int
    {
        $val = strtoupper(trim((string) ($value ?? '')));
        if ($val === '') return null;
        return in_array($val, ['L', 'LAKI', 'LAKI-LAKI', '1']) ? 1 : 2;
    }

    protected function parseIntOrNull($value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    /** Gabung alamat KTP lengkap; bagian kosong dilewati. */
    protected function buildAlamatKtp($row, array $map): ?string
    {
        $parts  = [];
        $alamat = trim((string) ($this->colVal($row, $map, 'alamat') ?? ''));
        $rt     = trim((string) ($this->colVal($row, $map, 'no rt') ?? ''));
        $kel    = trim((string) ($this->colVal($row, $map, 'nama kelurahan') ?? ''));
        $kec    = trim((string) ($this->colVal($row, $map, 'nama kecamatan') ?? ''));

        if ($alamat !== '') $parts[] = $alamat;
        if ($rt !== '')     $parts[] = 'RT ' . $rt;
        if ($kel !== '')    $parts[] = 'Kel. ' . $kel;
        if ($kec !== '')    $parts[] = 'Kec. ' . $kec;

        return empty($parts) ? null : implode(', ', $parts);
    }

    /** Tanggal acuan usia, di-parse dari nama header "USIA BLN PER dd-mm-yyyy". */
    protected function findUsiaAcuan(array $map): string
    {
        foreach (array_keys($map) as $name) {
            if (str_starts_with($name, 'usia')) {
                if (preg_match('/(\d{2})-(\d{2})-(\d{4})/', $name, $m)) {
                    try { return Carbon::createFromFormat('d-m-Y', "{$m[1]}-{$m[2]}-{$m[3]}")->format('Y-m-d'); }
                    catch (\Exception $e) {}
                }
                break;
            }
        }
        return Carbon::today()->format('Y-m-d');
    }

    /** Ambil nilai kolom pertama yang namanya diawali $prefix (lowercase). */
    protected function colByPrefix($row, array $map, string $prefix): mixed
    {
        foreach ($map as $name => $idx) {
            if (str_starts_with($name, $prefix)) {
                $val = $row[$idx] ?? null;
                return ($val === '' || $val === null) ? null : $val;
            }
        }
        return null;
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
                $this->failures[] = '[ERROR] Header tidak ditemukan. Pastikan baris pertama berisi nama kolom.';
                $this->rowOffset += $originalSize;
                return;
            }
            [$this->headerRowIdx, $this->columnMap] = $detected;
            $this->usiaAcuan = $this->findUsiaAcuan($this->columnMap);
            $rows = $rows->slice($this->headerRowIdx + 1)->values();
        }

        $map        = $this->columnMap ?? [];
        $baseOffset = $isFirstChunk ? ($this->headerRowIdx + 1) : 0;

        foreach ($rows as $index => $row) {
            $rowNum = $this->rowOffset + $index + 1 + ($isFirstChunk ? $baseOffset : 0);

            $nama = trim((string) ($this->colVal($row, $map, 'nama lengkap') ?? ''));
            if ($nama === '') continue;

            try {
                $nikRaw   = trim((string) ($this->colVal($row, $map, 'nik') ?? ''));
                $nikValid = ctype_digit($nikRaw) && strlen($nikRaw) >= 15;
                $nik      = $nikValid ? substr($nikRaw, 0, 16) : null;

                // tgl_lahir: TGL LHR, fallback estimasi dari USIA BLN
                $tglLahir = $this->parseDate($this->colVal($row, $map, 'tgl lhr'));
                if ($tglLahir === null) {
                    $usia = $this->parseIntOrNull($this->colByPrefix($row, $map, 'usia'));
                    if ($usia !== null && $usia > 0) {
                        $tglLahir = Carbon::parse($this->usiaAcuan)->subMonths($usia)->startOfMonth()->format('Y-m-d');
                        $this->failures[] = "[INFO] Baris {$rowNum} ({$nama}): TGL LHR kosong — diestimasi dari usia ({$usia} bln) menjadi {$tglLahir}.";
                    }
                }
                if ($tglLahir === null) {
                    $this->failures[] = "[ERROR] Baris {$rowNum} ({$nama}): tanggal lahir kosong & usia tidak tersedia — dilewati.";
                    $this->errorCount++;
                    continue;
                }

                $jk        = $this->parseJkOrNull($this->colVal($row, $map, 'jenis klmin'));
                $noKk      = $this->colVal($row, $map, 'no kk');
                $namaIbu   = $this->colVal($row, $map, 'nama lengkap ibu');
                $namaAyah  = $this->colVal($row, $map, 'nama lengkap ayah');
                $alamatKtp = $this->buildAlamatKtp($row, $map);

                $match = $this->resolveAnakByTwoOfThree($nik, $nama, $tglLahir);

                if ($match['match'] === 'ambigu') {
                    $this->failures[] = "[PERINGATAN] Baris {$rowNum} ({$nama}): {$match['warning']} — dilewati.";
                    $this->errorCount++;
                    continue;
                }

                // Field kependudukan — hanya yang Capil isi (sel kosong tak menimpa)
                $attrs = [];
                $attrs['nama']      = $nama;          // selalu ada (sudah dicek)
                $attrs['tgl_lahir'] = $tglLahir;      // selalu ada (sudah dicek)
                if ($jk !== null)        $attrs['jk']         = $jk;
                if (!empty($noKk))       $attrs['no_kk']      = $noKk;
                if (!empty($namaIbu))    $attrs['nama_ibu']   = $namaIbu;
                if (!empty($namaAyah))   $attrs['nama_ayah']  = $namaAyah;
                if (!empty($alamatKtp))  $attrs['alamat_ktp'] = $alamatKtp;

                $anak = $match['anak'];

                if ($anak) {
                    // UPDATE — NIK Capil otoritatif (NIK-first matching mencegah bentrok)
                    if ($nikValid && $nik !== $anak->nik) {
                        $attrs['nik'] = $nik;
                    }
                    $anak->update($attrs);
                    $this->updatedCount++;
                    $this->successCount++;
                } else {
                    // CREATE — baris baru, domisili kosong
                    if (!$nikValid) {
                        $jkForNik = ($jk === 1) ? 'L' : 'P';
                        $nik = $this->nikService->findExisting($nama, $tglLahir, $jkForNik)
                            ?? $this->nikService->generate(NikDummyService::DEFAULT_KODE_WILAYAH, $tglLahir, $jkForNik);
                        $this->failures[] = "[PERINGATAN] Baris {$rowNum} ({$nama}): NIK Capil kosong/invalid — NIK dummy {$nik} digunakan.";
                    }
                    $attrs['nik']     = $nik;
                    $attrs['jk']      = $jk ?? 2;     // NOT NULL — default perempuan
                    $attrs['no']      = 'CAPIL-' . date('Ym') . '-' . str_pad((string) $rowNum, 4, '0', STR_PAD_LEFT);
                    $attrs['status']  = 1;
                    $attrs['catatan'] = "Impor Capil {$this->importDate} — domisili belum diisi";
                    Anak::create($attrs);
                    $this->createdCount++;
                    $this->successCount++;
                }

            } catch (\Throwable $e) {
                $this->failures[] = "[ERROR] Baris {$rowNum} ({$nama}): " . $this->simplifyError($e->getMessage());
                $this->errorCount++;
                Log::warning("CapilImport skip baris {$rowNum}: " . $e->getMessage());
            }
        }

        $this->rowOffset += $originalSize;
    }

    protected function simplifyError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Data too long')        => 'Data terlalu panjang untuk salah satu kolom.',
            str_contains($message, 'Incorrect date value') => 'Format tanggal tidak valid.',
            str_contains($message, 'Duplicate')            => 'NIK bentrok dengan data lain.',
            str_contains($message, 'Integrity constraint') => 'Data wajib tidak lengkap atau bentrok.',
            default => mb_substr($message, 0, 120),
        };
    }

    public function getResults(): array
    {
        $summary = "[INFO] {$this->createdCount} baris baru dibuat, {$this->updatedCount} baris diperbarui, {$this->errorCount} dilewati.";

        return [
            'success'     => $this->successCount,
            'error_count' => $this->errorCount,
            'created'     => $this->createdCount,
            'updated'     => $this->updatedCount,
            'failures'    => array_merge([$summary], $this->failures),
        ];
    }
}
