<?php

namespace App\Imports;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Services\NikDummyService;
use App\Services\OperasiTimbangMatcher;
use App\Traits\ResolvesAnakByTwoOfThree;
use App\Traits\ResolvesWilayah;
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
 * Kecuali: dengan $buatTakCocok, baris TAK_COCOK dibuatkan Anak baru ber-NIK
 * dummy (NikDummyService) + measurement-nya (specs/013-publikasi-ot-prod).
 */
class OperasiTimbangImport implements ToCollection, WithStartRow, WithChunkReading, WithMultipleSheets
{
    use ResolvesAnakByTwoOfThree, ResolvesWilayah;

    protected OperasiTimbangMatcher $matcher;
    protected NikDummyService $nikService;

    protected int $matched = 0;
    protected int $skipped = 0;
    protected int $resolved = 0;       // baris ambigu diselesaikan via keputusan → ditulis
    protected int $resolvedSkip = 0;   // baris ambigu di-skip via keputusan
    protected int $dibuat = 0;         // baris TAK_COCOK → anak baru NIK dummy (--buat-tak-cocok)
    protected array $dibuatList = [];
    protected array $ambiguous = [];
    protected array $unmatched = [];
    protected array $keputusanError = [];
    protected array $failures = [];    // diisi ResolvesWilayah::flagUnresolvedWilayah

    protected ?array $columnMap = null;
    protected int $headerRowIdx = 0;
    protected int $rowOffset = 0;

    /**
     * @param array<int,string>|null $keputusan Peta baris(rowNum)→keputusan_id ('skip' atau id anak)
     *                                           untuk menyelesaikan baris ambigu secara manual.
     * @param bool $buatTakCocok Baris TAK_COCOK dibuatkan Anak baru ber-NIK dummy + measurement
     *                           (untuk publikasi hasil OT; lihat specs/013-publikasi-ot-prod).
     */
    public function __construct(
        protected int $userId,
        protected bool $commit = false,
        protected int $minNama = 88,
        protected int|string $sheet = 0,
        protected ?array $keputusan = null,
        protected bool $buatTakCocok = false,
    ) {
        $this->matcher = new OperasiTimbangMatcher($minNama);
        if ($this->buatTakCocok) {
            $this->nikService = new NikDummyService();
            $this->initWilayahCache();
        }
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
                    if ($this->terapkanKeputusan($rowNum, $row, $map, $tglUkur, $nama)) {
                        continue;
                    }
                    $this->ambiguous[] = $catatan;
                } else {
                    if ($this->buatTakCocok) {
                        $this->buatDanTulis($row, $map, $rowNum, $nama, $tglLahir, $jk, $namaOrtu ? (string) $namaOrtu : null, $tglUkur);
                    } else {
                        $this->unmatched[] = $catatan;
                    }
                }
            } catch (\Throwable $e) {
                $this->unmatched[] = ['baris' => $rowNum, 'nama' => $nama, 'tgl_lahir' => $tglLahir, 'alasan' => mb_substr($e->getMessage(), 0, 120), 'kandidat' => ''];
                Log::warning("OperasiTimbangImport skip baris {$rowNum}: " . $e->getMessage());
            }
        }

        $this->rowOffset += $originalSize;
    }

    /**
     * Terapkan keputusan manual untuk baris ambigu.
     * @return bool true bila baris terselesaikan (ditulis/skip) → jangan catat sbg ambigu.
     */
    protected function terapkanKeputusan(int $rowNum, $row, array $map, string $tglUkur, string $nama): bool
    {
        $dec = $this->keputusan[$rowNum] ?? null;
        if ($dec === null || $dec === '') {
            return false;
        }

        if (strtolower((string) $dec) === 'skip') {
            $this->resolvedSkip++;
            return true;
        }

        if (ctype_digit((string) $dec)) {
            $anak = Anak::find((int) $dec);
            if ($anak) {
                $this->resolved++;
                if ($this->commit) {
                    $this->tulis($anak, $row, $map, $tglUkur);
                }
                return true;
            }
            // id tak ditemukan → catat error & biarkan tetap dilaporkan sebagai ambigu
            $this->keputusanError[] = ['baris' => $rowNum, 'nama' => $nama, 'alasan' => "keputusan_id #{$dec} tidak ditemukan di data anak."];
        }

        return false;
    }

    /**
     * Buat Anak baru ber-NIK dummy untuk baris TAK_COCOK, lalu tulis measurement-nya.
     * Dry-run: hanya menghitung, tidak menulis apa pun.
     */
    protected function buatDanTulis($row, array $map, int $rowNum, string $nama, ?string $tglLahir, string $jk, ?string $namaOrtu, string $tglUkur): void
    {
        $this->dibuat++;

        if (!$this->commit) {
            $this->dibuatList[] = ['baris' => $rowNum, 'nama' => $nama, 'tgl_lahir' => $tglLahir, 'nik' => '(dry-run)'];
            return;
        }

        $jkInt  = strtoupper(trim($jk)) === 'L' ? 1 : 2;
        $jkChar = $jkInt === 1 ? 'L' : 'P';
        $tgl    = $tglLahir ?? date('Y-m-d');

        [$namaAyah, $namaIbu] = $this->pecahNamaOrtu($namaOrtu);

        $kecNama = trim((string) ($this->colVal($row, $map, 'kec') ?? ''));
        $kelNama = trim((string) ($this->colVal($row, $map, 'desa/kel') ?? ''));
        $rtNama  = trim((string) ($this->colVal($row, $map, 'rt') ?? ''));

        $idKec = $kecNama !== '' ? $this->resolveKecamatan($kecNama) : null;
        $idKel = $kelNama !== '' ? $this->resolveKelurahan($kelNama, $idKec) : null;
        $idRt  = $rtNama  !== '' ? $this->resolveRt($rtNama, $idKel) : null;

        // findExisting dulu → run ulang tidak menggandakan anak dummy (idempoten).
        $nik = $this->nikService->findExisting($nama, $tgl, $jkChar)
            ?? $this->nikService->generate(NikDummyService::DEFAULT_KODE_WILAYAH, $tgl, $jkChar);

        $anak = Anak::updateOrCreate(['nik' => $nik], [
            'nama'      => $nama,
            'jk'        => $jkInt,
            'tgl_lahir' => $tglLahir,
            'nama_ayah' => $namaAyah,
            'nama_ibu'  => $namaIbu,
            'alamat'    => $this->trimOrNull($this->colVal($row, $map, 'alamat')),
            'id_kec'    => $idKec,
            'id_kel'    => $idKel,
            'id_rt'     => $idRt,
            'no'        => 'OT-' . str_pad((string) $rowNum, 5, '0', STR_PAD_LEFT),
            'status'    => 1,
            'sumber'    => 'operasi_timbang',
        ]);

        $this->dibuatList[] = ['baris' => $rowNum, 'nama' => $nama, 'tgl_lahir' => $tglLahir, 'nik' => $anak->nik];
        $this->tulis($anak, $row, $map, $tglUkur);
    }

    /**
     * Pecah "Nama Ortu" e-PPGBM (format "AYAH / IBU") → [ayah, ibu].
     * Satu nama tanpa '/' dianggap ibu (konsisten dgn tie-break matcher yang memakai nama_ibu).
     */
    protected function pecahNamaOrtu(?string $namaOrtu): array
    {
        $v = trim((string) $namaOrtu);
        if ($v === '') {
            return [null, null];
        }
        $parts = array_values(array_filter(array_map('trim', explode('/', $v)), fn ($p) => $p !== ''));
        if (count($parts) >= 2) {
            return [$parts[0], $parts[1]];
        }
        return [null, $parts[0] ?? null];
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
                'sumber'           => 'operasi_timbang',
            ]
        );

        // Anak yang benar-benar menerima pengukuran OT harus ikut populasi
        // terkunci juga — walau identitasnya semula dibuat lewat jalur lain
        // (Capil/manual/AnakImport), begitu ia punya data timbang OT sungguhan
        // ia bagian dari sasaran OT.
        if ($anak->sumber !== 'operasi_timbang') {
            $anak->update(['sumber' => 'operasi_timbang']);
        }
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
            'matched'         => $this->matched,
            'ambiguous'       => $this->ambiguous,
            'unmatched'       => $this->unmatched,
            'skipped'         => $this->skipped,
            'resolved'        => $this->resolved,
            'resolved_skip'   => $this->resolvedSkip,
            'dibuat'          => $this->dibuat,
            'dibuat_list'     => $this->dibuatList,
            'keputusan_error' => $this->keputusanError,
            'failures'        => $this->failures,
        ];
    }
}
