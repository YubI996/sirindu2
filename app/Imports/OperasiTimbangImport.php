<?php

namespace App\Imports;

use App\Models\DataAnak;
use App\Services\OperasiTimbangMatcher;
use App\Traits\ResolvesAnakByTwoOfThree;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import hasil "Operasi Timbang" (ekspor e-PPGBM/Sigizi) ke data_anak.
 *
 * NIK e-PPGBM disensor → diabaikan. Pencocokan via OperasiTimbangMatcher
 * (tgl_lahir + jk + fuzzy nama + tie-break Nama Ortu). Baris tak cocok/ambigu
 * TIDAK membuat anak baru — hanya dilaporkan. Data dasar anak tidak ditimpa.
 */
class OperasiTimbangImport implements ToCollection, WithStartRow, WithChunkReading, WithMultipleSheets
{
    use ResolvesAnakByTwoOfThree;

    protected OperasiTimbangMatcher $matcher;

    protected int $matched = 0;
    protected int $skipped = 0;
    protected array $ambiguous = [];
    protected array $unmatched = [];

    protected ?array $columnMap = null;
    protected int $headerRowIdx = 0;
    protected int $rowOffset = 0;

    public function __construct(
        protected int $userId,
        protected bool $commit = false,
        protected int $minNama = 88,
        protected int|string $sheet = 0,
    ) {
        $this->matcher = new OperasiTimbangMatcher($minNama);
    }

    public function sheets(): array { return [$this->sheet => $this]; }
    public function startRow(): int { return 1; }
    public function chunkSize(): int { return 300; }

    public function collection(Collection $rows): void
    {
        $isFirstChunk = $this->rowOffset === 0;
        $originalSize = count($rows);

        if ($isFirstChunk) {
            $detected = $this->detectImportHeader($rows);
            if ($detected === null) {
                $this->unmatched[] = ['baris' => 0, 'nama' => '', 'tgl_lahir' => null, 'alasan' => 'Header tidak ditemukan.', 'kandidat' => ''];
                $this->rowOffset += $originalSize;
                return;
            }
            [$this->headerRowIdx, $this->columnMap] = $detected;
            $rows = $rows->slice($this->headerRowIdx + 1)->values();
        }

        $map = $this->columnMap ?? [];
        $baseOffset = $isFirstChunk ? ($this->headerRowIdx + 1) : 0;

        foreach ($rows as $index => $row) {
            $rowNum = $this->rowOffset + $index + 1 + ($isFirstChunk ? $baseOffset : 0);

            $nama = trim((string) ($this->colVal($row, $map, 'nama') ?? ''));
            if ($nama === '') continue;

            $tglLahir = $this->parseDate($this->colVal($row, $map, 'tgl lahir'));
            $jk        = (string) ($this->colVal($row, $map, 'jk') ?? '');
            $namaOrtu  = $this->colVal($row, $map, 'nama ortu');
            $kelurahan = $this->colVal($row, $map, 'desa/kel');
            $tglUkur   = $this->parseDate($this->colVal($row, $map, 'tanggal pengukuran'));

            if (!$tglUkur) {
                $this->skipped++;
                $this->unmatched[] = ['baris' => $rowNum, 'nama' => $nama, 'tgl_lahir' => $tglLahir, 'alasan' => 'Tanggal Pengukuran kosong/tidak valid.', 'kandidat' => ''];
                continue;
            }

            try {
                $res = $this->matcher->match(
                    $nama,
                    $tglLahir,
                    $jk,
                    $namaOrtu ? (string) $namaOrtu : null,
                    $kelurahan ? (string) $kelurahan : null,
                );

                if ($res['status'] === 'COCOK') {
                    $this->matched++;
                    if ($this->commit) {
                        $this->tulis($res['anak'], $row, $map, $tglUkur);
                    }
                    continue;
                }

                $catatan = [
                    'baris'     => $rowNum,
                    'nama'      => $nama,
                    'tgl_lahir' => $tglLahir,
                    'alasan'    => (string) $res['alasan'],
                    'kandidat'  => collect($res['kandidat'])->map(fn ($a) => "#{$a->id} {$a->nama} (ibu: {$a->nama_ibu})")->implode('; '),
                ];
                if ($res['status'] === 'AMBIGU') {
                    $this->ambiguous[] = $catatan;
                } else {
                    $this->unmatched[] = $catatan;
                }
            } catch (\Throwable $e) {
                $this->unmatched[] = ['baris' => $rowNum, 'nama' => $nama, 'tgl_lahir' => $tglLahir, 'alasan' => mb_substr($e->getMessage(), 0, 120), 'kandidat' => ''];
                Log::warning("OperasiTimbangImport skip baris {$rowNum}: " . $e->getMessage());
            }
        }

        $this->rowOffset += $originalSize;
    }

    protected function tulis($anak, $row, array $map, string $tglUkur): void
    {
        $bln = usia_bulan($anak->tgl_lahir, $tglUkur) ?? 0;

        DataAnak::updateOrCreate(
            ['id_anak' => $anak->id, 'tgl_kunjungan' => $tglUkur],
            [
                'bln'              => $bln,
                'posisi'           => normalisasi_posisi($this->colVal($row, $map, 'cara ukur')),
                'bb'               => $this->parseDecimal($this->colVal($row, $map, 'berat')) ?? 0,
                'tb'               => $this->parseDecimal($this->colVal($row, $map, 'tinggi')) ?? 0,
                'lla'              => $this->parseDecimal($this->colVal($row, $map, 'lila')) ?? 0,
                'lk'               => 0,
                'zscore_bb_u'      => $this->parseDecimal($this->colVal($row, $map, 'zs bb/u')),
                'zscore_pb_u'      => $this->parseDecimal($this->colVal($row, $map, 'zs tb/u')),
                'zscore_bb_pb'     => $this->parseDecimal($this->colVal($row, $map, 'zs bb/tb')),
                'ntob'             => $this->trimOrNull($this->colVal($row, $map, 'naik berat badan')),
                'vit_a'            => $this->parseIntOrNull($this->colVal($row, $map, 'jml vit a')),
                'kelas_ibu_balita' => $this->parseBoolean($this->colVal($row, $map, 'kelas ibu balita')),
                'mbg'              => $this->parseBoolean($this->colVal($row, $map, 'mbg')),
                'id_user'          => $this->userId,
            ]
        );
    }

    // --- parse helpers (pola sama seperti UkurImport) ---

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

    protected function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (float) $value : null;
    }

    protected function parseIntOrNull($value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function parseBoolean($value): ?bool
    {
        if ($value === null || $value === '') return null;
        return in_array(strtolower(trim((string) $value)), ['ya', 'y', 'yes', 'true', '1'], true);
    }

    protected function trimOrNull($value): ?string
    {
        if ($value === null) return null;
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    public function getResults(): array
    {
        return [
            'matched'   => $this->matched,
            'ambiguous' => $this->ambiguous,
            'unmatched' => $this->unmatched,
            'skipped'   => $this->skipped,
        ];
    }
}
