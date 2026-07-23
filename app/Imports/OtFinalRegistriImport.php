<?php

namespace App\Imports;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Services\NikDummyService;
use App\Traits\ResolvesAnakByTwoOfThree;
use App\Traits\ResolvesWilayah;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Bangun registri Operasi Timbang dari berkas final e-PPGBM (47 kolom).
 *
 * Seluruh logika diturunkan dari kolom NIK, BUKAN dari pewarnaan sel:
 *  - NIK kosong   → NIK dummy (setara baris cream di berkas)
 *  - NIK duplikat → satu Anak, banyak pengukuran (setara baris biru)
 *
 * Pengukuran di-upsert by (id_anak, tgl_kunjungan) sehingga dua baris ber-NIK
 * sama dan bertanggal ukur sama melebur jadi satu (keputusan klien).
 * Lihat specs/2026-07-23-ot-final-registri-import-design.md.
 */
class OtFinalRegistriImport implements ToCollection, WithStartRow, WithChunkReading, WithMultipleSheets
{
    use ResolvesAnakByTwoOfThree, ResolvesWilayah;

    /** Kolom yang wajib ada; tanpa ini pemetaan tak bermakna. */
    protected const KOLOM_WAJIB = [
        'nama anak', 'jenis kelamin', 'tanggal lahir', 'nik',
        'kecamatan', 'kelurahan',
        'tanggal pengukuran', 'berat (kg)', 'tinggi (cm)',
    ];

    protected NikDummyService $nikService;

    protected int $anakDibuat = 0;
    protected int $ukurDitulis = 0;
    protected int $dummy = 0;
    protected int $lebur = 0;
    protected int $dilewati = 0;
    protected array $peringatan = [];
    protected array $error = [];
    protected array $failures = []; // diisi ResolvesWilayah::flagUnresolvedWilayah

    protected ?array $columnMap = null;
    protected int $headerRowIdx = 0;
    protected int $rowOffset = 0;
    protected bool $headerRusak = false;

    protected array $posyanduCache = [];
    protected array $puskesmasCache = [];
    protected bool $faskesCacheSiap = false;

    /** Kunci anak yang sudah ditemui pada run ini ("NIK:xxx" / "BARIS:n"). */
    protected array $kunciAnak = [];

    /** Kunci "<kunciAnak>|tgl" yang sudah ditemui — untuk menghitung peleburan. */
    protected array $kunciUkur = [];

    public function __construct(
        protected int $userId,
        protected bool $commit = false,
        protected int|string $sheet = 0,
    ) {
        $this->nikService = new NikDummyService();
        $this->initWilayahCache();
    }

    public function sheets(): array
    {
        return [$this->sheet => $this];
    }

    public function startRow(): int
    {
        return 1;
    }

    public function chunkSize(): int
    {
        return 300;
    }

    public function collection(Collection $rows): void
    {
        $isFirstChunk = $this->rowOffset === 0;
        $originalSize = count($rows);

        if ($isFirstChunk) {
            $detected = $this->detectImportHeader($rows);
            if ($detected === null) {
                $this->error[] = 'Header tidak ditemukan pada berkas.';
                $this->headerRusak = true;
                $this->rowOffset += $originalSize;

                return;
            }
            [$this->headerRowIdx, $this->columnMap] = $detected;

            $hilang = array_values(array_diff(self::KOLOM_WAJIB, array_keys($this->columnMap)));
            if (!empty($hilang)) {
                $this->error[] = 'Kolom wajib tidak ditemukan: ' . implode(', ', $hilang);
                $this->headerRusak = true;
                $this->rowOffset += $originalSize;

                return;
            }

            $rows = $rows->slice($this->headerRowIdx + 1)->values();
        }

        if ($this->headerRusak) {
            $this->rowOffset += $originalSize;

            return;
        }

        $this->prosesBaris($rows, $isFirstChunk);
        $this->rowOffset += $originalSize;
    }

    protected function prosesBaris(Collection $rows, bool $isFirstChunk): void
    {
        $map = $this->columnMap ?? [];
        $baseOffset = $isFirstChunk ? ($this->headerRowIdx + 1) : 0;

        foreach ($rows as $index => $row) {
            $rowNum = $this->rowOffset + $index + 1 + ($isFirstChunk ? $baseOffset : 0);

            $nama = trim((string) ($this->colVal($row, $map, 'nama anak') ?? ''));
            if ($nama === '') {
                continue; // baris kosong di ekor berkas
            }

            try {
                $tglLahir = $this->parseDate($this->colVal($row, $map, 'tanggal lahir'));
                if (!$tglLahir) {
                    $this->dilewati++;
                    $this->peringatan[] = "baris {$rowNum} ({$nama}): Tanggal Lahir kosong/tidak valid.";
                    continue;
                }

                // --- Penghitungan: berbasis kunci, identik di dry-run & commit. ---
                // Baris ber-NIK kosong dihitung sebagai anak unik PER BARIS (spec §6).
                $nikBerkas = trim((string) ($this->colVal($row, $map, 'nik') ?? ''));
                $kunciAnak = $nikBerkas !== '' ? 'NIK:' . $nikBerkas : 'BARIS:' . $rowNum;

                if (!isset($this->kunciAnak[$kunciAnak])) {
                    $this->kunciAnak[$kunciAnak] = true;
                    $this->anakDibuat++;
                    if ($nikBerkas === '') {
                        $this->dummy++;
                    }
                }

                $tglUkur = $this->parseDate($this->colVal($row, $map, 'tanggal pengukuran'));
                if (!$tglUkur) {
                    $this->dilewati++;
                    $this->peringatan[] = "baris {$rowNum} ({$nama}): Tanggal Pengukuran kosong/tidak valid.";
                    continue;
                }

                $kunciUkur = $kunciAnak . '|' . $tglUkur;
                if (isset($this->kunciUkur[$kunciUkur])) {
                    $this->lebur++;
                } else {
                    $this->kunciUkur[$kunciUkur] = true;
                    $this->ukurDitulis++;
                }

                // --- Penulisan: hanya saat commit. ---
                if (!$this->commit) {
                    continue;
                }

                $anak = $this->upsertAnak($row, $map, $rowNum, $nama, $tglLahir, $nikBerkas);
                $this->tulisUkur($anak, $row, $map, $tglUkur);
            } catch (\Throwable $e) {
                $this->dilewati++;
                $this->peringatan[] = "baris {$rowNum} ({$nama}): " . mb_substr($e->getMessage(), 0, 120);
            }
        }
    }

    /**
     * Buat/perbarui Anak dari satu baris. Hanya dipanggil saat commit;
     * penghitungan sudah dilakukan di prosesBaris().
     */
    protected function upsertAnak($row, array $map, int $rowNum, string $nama, string $tglLahir, string $nikBerkas): Anak
    {
        $jkRaw = strtoupper(trim((string) ($this->colVal($row, $map, 'jenis kelamin') ?? '')));
        $jkInt = $jkRaw === 'L' ? 1 : 2;

        $nik = $this->tentukanNik($nikBerkas, $tglLahir, $jkRaw === 'L' ? 'L' : 'P');

        [$namaAyah, $namaIbu] = $this->pecahNamaOrtu($this->colVal($row, $map, 'nama orang tua (ibu/ayah)'));

        $this->initFaskesCache();

        $kecNama = trim((string) ($this->colVal($row, $map, 'kecamatan') ?? ''));
        $kelNama = trim((string) ($this->colVal($row, $map, 'kelurahan') ?? ''));
        $rtNama  = trim((string) ($this->colVal($row, $map, 'rt') ?? ''));
        $pusNama = trim((string) ($this->colVal($row, $map, 'puskesmas') ?? ''));
        $posNama = trim((string) ($this->colVal($row, $map, 'posyandu') ?? ''));

        $idKec = $kecNama !== '' ? $this->resolveKecamatan($kecNama) : null;
        $idKel = $kelNama !== '' ? $this->resolveKelurahan($kelNama, $idKec) : null;
        $idRt  = $rtNama  !== '' ? $this->resolveRt($rtNama, $idKel) : null;
        $idPus = $pusNama !== '' ? $this->resolveFaskes($this->puskesmasCache, $pusNama, Puskesmas::class) : null;
        $idPos = $posNama !== '' ? $this->resolveFaskes($this->posyanduCache, $posNama, Posyandu::class) : null;

        $anak = Anak::updateOrCreate(['nik' => $nik], [
            'nama'                 => $nama,
            'jk'                   => $jkInt,
            'tgl_lahir'            => $tglLahir,
            'no_kk'                => $this->trimOrNull($this->colVal($row, $map, 'nomor kk')),
            'anak'                 => $this->parseIntOrNull($this->colVal($row, $map, 'anak ke')),
            'nama_ayah'            => $namaAyah,
            'nama_ibu'             => $namaIbu,
            'nik_ortu'             => $this->trimOrNull($this->colVal($row, $map, 'nik orang tua')),
            'no_hp'                => $this->trimOrNull($this->colVal($row, $map, 'no hp orang tua')),
            'alamat'               => $this->trimOrNull($this->colVal($row, $map, 'alamat')),
            'usia_kehamilan_lahir' => $this->parseIntOrNull($this->colVal($row, $map, 'usia kehamilan (minggu)')),
            'bbl'                  => $this->parseDecimal($this->colVal($row, $map, 'berat lahir - sasaran (kg)')),
            'pbl'                  => $this->parseDecimal($this->colVal($row, $map, 'panjang lahir - sasaran (cm)')),
            'lk_lahir'             => $this->parseDecimal($this->colVal($row, $map, 'lingkar kepala lahir (cm)')),
            'imd'                  => $this->parseBoolean($this->colVal($row, $map, 'imd')),
            'id_kec'               => $idKec,
            'id_kel'               => $idKel,
            'id_rt'                => $idRt,
            'id_puskesmas'         => $idPus,
            'id_posyandu'          => $idPos,
            'no'                   => 'OT-' . str_pad((string) $rowNum, 5, '0', STR_PAD_LEFT),
            'status'               => 1,
        ]);

        return $anak;
    }

    /**
     * Tulis satu pengukuran. Kunci (id_anak, tgl_kunjungan) membuat dua baris
     * ber-NIK sama dan bertanggal ukur sama MELEBUR jadi satu — nilai baris
     * terakhir menang. Peleburan dihitung di prosesBaris() agar terlihat.
     */
    protected function tulisUkur(Anak $anak, $row, array $map, string $tglUkur): void
    {
        DataAnak::updateOrCreate(
            ['id_anak' => $anak->id, 'tgl_kunjungan' => $tglUkur],
            [
                'bln'              => usia_bulan($anak->tgl_lahir, $tglUkur) ?? 0,
                'posisi'           => normalisasi_posisi($this->colVal($row, $map, 'cara ukur')),
                'bb'               => $this->parseDecimal($this->colVal($row, $map, 'berat (kg)')) ?? 0,
                'tb'               => $this->parseDecimal($this->colVal($row, $map, 'tinggi (cm)')) ?? 0,
                'lla'              => $this->parseDecimal($this->colVal($row, $map, 'lila (cm)')) ?? 0,
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

    protected function initFaskesCache(): void
    {
        if ($this->faskesCacheSiap) {
            return;
        }

        $this->posyanduCache = Posyandu::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtoupper(trim($name)) => $id])->toArray();
        $this->puskesmasCache = Puskesmas::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtoupper(trim($name)) => $id])->toArray();

        $this->faskesCacheSiap = true;
    }

    /** Cari id faskes by nama. TIDAK pernah membuat master baru. */
    protected function resolveFaskes(array &$cache, string $name, string $modelClass): ?int
    {
        $key = strtoupper(trim($name));
        if ($key === '') {
            return null;
        }

        if (!array_key_exists($key, $cache)) {
            $record = $modelClass::where('name', 'like', '%' . trim($name) . '%')->first();
            $cache[$key] = $record?->id;
        }

        return $cache[$key];
    }

    /** NIK berkas bila ada; kosong → NIK dummy baru. */
    protected function tentukanNik(string $nikBerkas, string $tglLahir, string $jkChar): string
    {
        if ($nikBerkas !== '') {
            return $nikBerkas;
        }

        // SENGAJA tidak memakai findExisting(): di berkas ini setiap baris ber-NIK
        // kosong adalah anak yang berbeda. Mencocokkan by (nama, tgl lahir, jk)
        // berisiko melebur dua anak berbeda yang kebetulan seragam. Idempotensi
        // lintas-run dijamin oleh TRUNCATE sebelum build, bukan oleh findExisting.
        return $this->nikService->generate(
            NikDummyService::DEFAULT_KODE_WILAYAH,
            $tglLahir,
            $jkChar
        );
    }

    /**
     * Pecah "Nama Ortu" e-PPGBM (format "AYAH / IBU") → [ayah, ibu].
     * Satu nama tanpa '/' dianggap ibu.
     */
    protected function pecahNamaOrtu($namaOrtu): array
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

    protected function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
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

    protected function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function parseIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    protected function parseBoolean($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array(strtolower(trim((string) $value)), ['ya', 'y', 'yes', 'true', '1'], true);
    }

    protected function trimOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);

        return $v === '' ? null : $v;
    }

    public function getResults(): array
    {
        return [
            'anak_dibuat' => $this->anakDibuat,
            'ukur_ditulis' => $this->ukurDitulis,
            'dummy' => $this->dummy,
            'lebur' => $this->lebur,
            'dilewati' => $this->dilewati,
            'peringatan' => array_merge($this->peringatan, $this->failures),
            'error' => $this->error,
        ];
    }
}
