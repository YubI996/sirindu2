<?php

namespace App\Services;

use App\Support\FilterWilayahAnak;
use App\Support\PeriodeKesmas;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agregat dasbor Kesmas — spec docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md §2–3.
 *
 * Semua agregat murni SQL (SUM/COUNT/GROUP BY/joinSub); model Anak hanya dimuat
 * di registri() (≤ 20 per halaman). JANGAN memuat populasi anak ke PHP di sini —
 * insiden OOM dasbor imunisasi 16 Sep 2026 (memory_limit prod 128 MB, ±10 rb anak).
 *
 * Dua subquery inti dipakai berulang:
 *  - sasaranSub(): anak terfilter wilayah + umur (bulan) pada AKHIR periode;
 *  - kunjunganSub(): jumlah timbang/DDTKA/Vit A/LK per anak dalam periode
 *    (DDTKA & Vit A pada semester induk — lihat PeriodeKesmas).
 */
class KesmasDashboardService
{
    use FilterWilayahAnak;

    /** Kelompok umur SDIDTK & registri: kode => [min, max, label, domain SDIDTK (teks statis pedoman)]. */
    public const KELOMPOK = [
        'bayi'       => [0, 11, 'Bayi', 'Motorik kasar, motorik halus & bahasa'],
        'baduta'     => [12, 23, 'Baduta', 'Kemandirian & KPSP'],
        'balita'     => [24, 59, 'Balita', 'Daya dengar & daya lihat'],
        'prasekolah' => [60, 72, 'Prasekolah', 'Kesiapan sekolah (PAUD)'],
    ];

    /** Kolom anak yang dibawa subquery sasaran (dipakai skrining neonatal & sanitasi). */
    private const KOLOM_KESMAS_ANAK = [
        'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b',
        'air_bersih', 'jamban_sehat', 'merokok_keluarga',
    ];

    /** Persen 1 desimal; pembagi 0 → null (tampil "—", bukan 0 %). */
    public static function persen(int $n, int $sasaran): ?float
    {
        return $sasaran > 0 ? round($n / $sasaran * 100, 1) : null;
    }

    /** Subquery `s`: anak terfilter wilayah dengan umur (bulan) pada akhir periode. */
    private function sasaranSub(PeriodeKesmas $p, array $filters): Builder
    {
        $akhir = $p->akhir()->toDateString();
        $q = DB::table('anak as a')
            ->selectRaw(
                'a.id, TIMESTAMPDIFF(MONTH, a.tgl_lahir, ?) as umur, a.' . implode(', a.', self::KOLOM_KESMAS_ANAK),
                [$akhir]
            )
            ->whereNotNull('a.tgl_lahir')
            ->where('a.tgl_lahir', '<=', $akhir);

        return $this->applyWilayahFilters($q, $filters, 'a');
    }

    private function dariSasaran(PeriodeKesmas $p, array $filters): Builder
    {
        return DB::query()->fromSub($this->sasaranSub($p, $filters), 's');
    }

    /**
     * Subquery `k`: per anak, jumlah kunjungan (timbang) & LK dalam periode, serta
     * DDTKA & Vit A dalam semester induk. Hanya anak yang punya kunjungan di
     * rentang gabungan yang muncul — pemanggil memakai LEFT JOIN + COALESCE(…,0).
     */
    private function kunjunganSub(PeriodeKesmas $p): Builder
    {
        [$a, $z]   = [$p->awal()->toDateString(), $p->akhir()->toDateString()];
        [$sa, $sz] = [$p->semesterAwal()->toDateString(), $p->semesterAkhir()->toDateString()];

        return DB::table('data_anak')
            ->selectRaw(
                'id_anak, '
                . 'SUM(tgl_kunjungan BETWEEN ? AND ?) as n_timbang, '
                . "SUM(tgl_kunjungan BETWEEN ? AND ? AND ddtka IS NOT NULL AND TRIM(ddtka) <> '') as n_ddtka, "
                . 'SUM(tgl_kunjungan BETWEEN ? AND ? AND vit_a = 1) as n_vita, '
                . 'SUM(tgl_kunjungan BETWEEN ? AND ? AND lk > 0) as n_lk',
                [$a, $z, $sa, $sz, $sa, $sz, $a, $z]
            )
            ->whereBetween('tgl_kunjungan', [min($a, $sa), max($z, $sz)])
            ->groupBy('id_anak');
    }

    /** Sasaran per kelompok umur dalam SATU query (§2.3). */
    public function sasaran(PeriodeKesmas $p, array $filters): array
    {
        $r = $this->dariSasaran($p, $filters)->selectRaw(
            'SUM(umur BETWEEN 0 AND 11) as bayi, SUM(umur BETWEEN 6 AND 11) as bayi_6_11, '
            . 'SUM(umur BETWEEN 12 AND 23) as baduta, SUM(umur BETWEEN 12 AND 59) as anak_balita, '
            . 'SUM(umur BETWEEN 0 AND 59) as balita, SUM(umur BETWEEN 24 AND 59) as balita_24_59, '
            . 'SUM(umur BETWEEN 60 AND 72) as prasekolah, SUM(umur BETWEEN 0 AND 72) as semua, '
            . 'SUM(umur BETWEEN 0 AND 11) as tahun_0, SUM(umur BETWEEN 12 AND 23) as tahun_1, '
            . 'SUM(umur BETWEEN 24 AND 35) as tahun_2, SUM(umur BETWEEN 36 AND 83) as tahun_3_6'
        )->first();

        return array_map('intval', (array) $r);
    }

    /** Kartu K1 (balita 0–59), K2 (bayi 0–11), K3 (anak balita 12–59) — §3. */
    public function spmKohort(PeriodeKesmas $p, array $filters): array
    {
        $t8 = $p->syarat(8);
        $t2 = $p->syarat(2);

        $r = $this->dariSasaran($p, $filters)
            ->leftJoinSub($this->kunjunganSub($p), 'k', 'k.id_anak', '=', 's.id')
            ->selectRaw(
                'SUM(umur BETWEEN 0 AND 11) as bayi, '
                . 'SUM(umur BETWEEN 0 AND 11 AND COALESCE(n_timbang,0) >= ?) as bayi_timbang, '
                . 'SUM(umur BETWEEN 0 AND 11 AND COALESCE(n_ddtka,0) >= ?) as bayi_ddtka, '
                . 'SUM(umur BETWEEN 6 AND 11) as bayi_6_11, '
                . 'SUM(umur BETWEEN 6 AND 11 AND COALESCE(n_vita,0) >= 1) as bayi_vita, '
                . 'SUM(umur BETWEEN 0 AND 11 AND COALESCE(n_lk,0) >= 1) as bayi_lk, '
                . 'SUM(umur BETWEEN 0 AND 11 AND COALESCE(n_timbang,0) >= ? AND COALESCE(n_ddtka,0) >= ? '
                . '    AND (umur < 6 OR COALESCE(n_vita,0) >= 1) AND COALESCE(n_lk,0) >= 1) as bayi_lengkap, '
                . 'SUM(umur BETWEEN 12 AND 59) as anak_balita, '
                . 'SUM(umur BETWEEN 12 AND 59 AND COALESCE(n_timbang,0) >= ?) as ab_timbang, '
                . 'SUM(umur BETWEEN 12 AND 59 AND COALESCE(n_ddtka,0) >= ?) as ab_ddtka, '
                . 'SUM(umur BETWEEN 12 AND 59 AND COALESCE(n_vita,0) >= ?) as ab_vita, '
                . 'SUM(umur BETWEEN 12 AND 59 AND COALESCE(n_timbang,0) >= ? AND COALESCE(n_ddtka,0) >= ? '
                . '    AND COALESCE(n_vita,0) >= ?) as ab_lengkap',
                [$t8, $t2, $t8, $t2, $t8, $t2, $t2, $t8, $t2, $t2]
            )->first();
        $r = array_map('intval', (array) $r);

        $sub = fn (int $n, int $sasaran) => ['n' => $n, 'sasaran' => $sasaran, 'persen' => self::persen($n, $sasaran)];
        $balitaSasaran = $r['bayi'] + $r['anak_balita'];
        $balitaLengkap = $r['bayi_lengkap'] + $r['ab_lengkap'];

        return [
            'syarat' => ['timbang' => $t8, 'ddtka' => $t2, 'vita' => $t2],
            'balita' => [
                'sasaran' => $balitaSasaran,
                'lengkap' => $balitaLengkap,
                'persen'  => self::persen($balitaLengkap, $balitaSasaran),
            ],
            'bayi' => [
                'sasaran' => $r['bayi'],
                'lengkap' => $r['bayi_lengkap'],
                'persen'  => self::persen($r['bayi_lengkap'], $r['bayi']),
                'sisa'    => $r['bayi'] - $r['bayi_lengkap'],
                'sub'     => [
                    'timbang' => $sub($r['bayi_timbang'], $r['bayi']),
                    'ddtka'   => $sub($r['bayi_ddtka'], $r['bayi']),
                    'vita'    => $sub($r['bayi_vita'], $r['bayi_6_11']),
                    'lk'      => $sub($r['bayi_lk'], $r['bayi']),
                ],
            ],
            'anak_balita' => [
                'sasaran' => $r['anak_balita'],
                'lengkap' => $r['ab_lengkap'],
                'persen'  => self::persen($r['ab_lengkap'], $r['anak_balita']),
                'gap'     => $r['anak_balita'] - $r['ab_lengkap'],
                'sub'     => [
                    'timbang' => $sub($r['ab_timbang'], $r['anak_balita']),
                    'ddtka'   => $sub($r['ab_ddtka'], $r['anak_balita']),
                    'vita'    => $sub($r['ab_vita'], $r['anak_balita']),
                ],
            ],
        ];
    }

    /** Subquery `m`: tanggal kunjungan terakhir per anak DI DALAM periode. */
    private function kunjunganTerakhirSub(PeriodeKesmas $p): Builder
    {
        return DB::table('data_anak')
            ->selectRaw('id_anak, MAX(tgl_kunjungan) as max_tgl')
            ->whereBetween('tgl_kunjungan', [$p->awal()->toDateString(), $p->akhir()->toDateString()])
            ->groupBy('id_anak');
    }

    /** Kartu K4: 0–72 bln dengan ≥T8 timbang & ≥T2 DDTKA; "perlu perhatian" dari kunjungan terakhir. */
    public function pemantauanTk(PeriodeKesmas $p, array $filters): array
    {
        $r = $this->dariSasaran($p, $filters)
            ->leftJoinSub($this->kunjunganSub($p), 'k', 'k.id_anak', '=', 's.id')
            ->selectRaw(
                'SUM(umur BETWEEN 0 AND 72) as sasaran, '
                . 'SUM(umur BETWEEN 0 AND 72 AND COALESCE(n_timbang,0) >= ? AND COALESCE(n_ddtka,0) >= ?) as lengkap',
                [$p->syarat(8), $p->syarat(2)]
            )->first();
        $sasaran = (int) $r->sasaran;
        $lengkap = (int) $r->lengkap;

        // KMS kuning/merah: BB tidak naik (ntob T) atau BB/U ≤ -2 SD pada kunjungan terakhir dalam periode.
        $perhatian = $this->dariSasaran($p, $filters)
            ->joinSub($this->kunjunganTerakhirSub($p), 'm', 'm.id_anak', '=', 's.id')
            ->join('data_anak as da', function ($j) {
                $j->on('da.id_anak', '=', 'm.id_anak')->on('da.tgl_kunjungan', '=', 'm.max_tgl');
            })
            ->where('s.umur', '<=', 72)
            ->where(function ($w) {
                $w->whereRaw("UPPER(TRIM(da.ntob)) = 'T'")->orWhere('da.zscore_bb_u', '<=', -2.01);
            })
            ->distinct()->count('s.id');

        return ['sasaran' => $sasaran, 'lengkap' => $lengkap, 'persen' => self::persen($lengkap, $sasaran), 'perhatian' => $perhatian];
    }

    /** Cakupan SDIDTK per kelompok umur: anak dengan ≥1 kunjungan ber-ddtka dalam periode. */
    public function sdidtk(PeriodeKesmas $p, array $filters): array
    {
        $ddtka = DB::table('data_anak')->select('id_anak')->distinct()
            ->whereBetween('tgl_kunjungan', [$p->awal()->toDateString(), $p->akhir()->toDateString()])
            ->whereNotNull('ddtka')->whereRaw("TRIM(ddtka) <> ''");

        $sel = [];
        foreach (self::KELOMPOK as $kode => [$min, $max]) {
            $sel[] = "SUM(umur BETWEEN {$min} AND {$max}) as {$kode}_sasaran";
            $sel[] = "SUM(umur BETWEEN {$min} AND {$max} AND d.id_anak IS NOT NULL) as {$kode}_ya";
        }
        $r = (array) $this->dariSasaran($p, $filters)
            ->leftJoinSub($ddtka, 'd', 'd.id_anak', '=', 's.id')
            ->selectRaw(implode(', ', $sel))->first();

        $kelompok = [];
        $totalSasaran = 0;
        $totalYa = 0;
        $fokus = null;
        foreach (self::KELOMPOK as $kode => [$min, $max, $label, $domain]) {
            $sasaran = (int) $r["{$kode}_sasaran"];
            $ya      = (int) $r["{$kode}_ya"];
            $persen  = self::persen($ya, $sasaran);
            $kelompok[$kode] = [
                'label' => $label, 'domain' => $domain, 'min' => $min, 'max' => $max,
                'sasaran' => $sasaran, 'realisasi' => $ya, 'persen' => $persen,
            ];
            $totalSasaran += $sasaran;
            $totalYa      += $ya;
            if ($persen !== null && ($fokus === null || (100 - $persen) > $fokus['gap'])) {
                $fokus = ['kelompok' => $kode, 'label' => $label, 'min' => $min, 'max' => $max, 'gap' => round(100 - $persen, 1)];
            }
        }

        return [
            'kelompok' => $kelompok,
            'total'    => ['sasaran' => $totalSasaran, 'realisasi' => $totalYa, 'persen' => self::persen($totalYa, $totalSasaran)],
            'fokus'    => $fokus,
        ];
    }

    /** CKG (Cek Kesehatan Gratis) per umur tahun + footer gigi/rujukan. */
    public function ckg(PeriodeKesmas $p, array $filters): array
    {
        [$a, $z] = [$p->awal()->toDateString(), $p->akhir()->toDateString()];

        $ckg = DB::table('data_anak')->select('id_anak')->distinct()->whereBetween('tgl_penanda_ckg', [$a, $z]);
        $r = (array) $this->dariSasaran($p, $filters)
            ->leftJoinSub($ckg, 'c', 'c.id_anak', '=', 's.id')
            ->selectRaw(
                'SUM(umur BETWEEN 0 AND 11) as t0, SUM(umur BETWEEN 0 AND 11 AND c.id_anak IS NOT NULL) as t0_ya, '
                . 'SUM(umur BETWEEN 12 AND 23) as t1, SUM(umur BETWEEN 12 AND 23 AND c.id_anak IS NOT NULL) as t1_ya, '
                . 'SUM(umur BETWEEN 24 AND 35) as t2, SUM(umur BETWEEN 24 AND 35 AND c.id_anak IS NOT NULL) as t2_ya, '
                . 'SUM(umur BETWEEN 36 AND 83) as t3, SUM(umur BETWEEN 36 AND 83 AND c.id_anak IS NOT NULL) as t3_ya'
            )->first();

        $gigi = DB::table('data_anak as da')
            ->joinSub($this->sasaranSub($p, $filters), 's', 's.id', '=', 'da.id_anak')
            ->where('s.umur', '<=', 83)
            ->whereBetween('da.tgl_kunjungan', [$a, $z])
            ->whereNotNull('da.pemeriksaan_gigi')
            ->selectRaw("COUNT(*) as terisi, SUM(da.pemeriksaan_gigi = 'Sehat') as sehat")
            ->first();

        $rujuk = DB::table('data_anak as da')
            ->joinSub($this->sasaranSub($p, $filters), 's', 's.id', '=', 'da.id_anak')
            ->where('s.umur', '<=', 83)
            ->whereBetween('da.tgl_kunjungan', [$a, $z])
            ->where('da.rujukan', 'Dokter gigi')
            ->distinct()->count('da.id_anak');

        $baris = fn (string $label, string $k) => [
            'label' => $label, 'sasaran' => (int) $r[$k], 'realisasi' => (int) $r["{$k}_ya"],
            'persen' => self::persen((int) $r["{$k}_ya"], (int) $r[$k]),
        ];

        return [
            'kelompok' => [
                't0' => $baris('Bayi baru lahir (< 1 tahun)', 't0'),
                't1' => $baris('Usia 1 tahun', 't1'),
                't2' => $baris('Usia 2 tahun', 't2'),
                't3' => $baris('Usia 3–6 tahun', 't3'),
            ],
            'gigi' => [
                'terisi' => (int) $gigi->terisi, 'sehat' => (int) $gigi->sehat,
                'persen_sehat' => self::persen((int) $gigi->sehat, (int) $gigi->terisi),
            ],
            'rujuk_gigi' => $rujuk,
        ];
    }

    /**
     * Seksi "Layanan & Lingkungan" (§3): pembagi = anak yang datanya TERISI (IS NOT NULL);
     * NULL berarti belum ditanya, bukan "tidak" — jumlah belum_diisi selalu ikut dikembalikan.
     */
    public function layananLingkungan(PeriodeKesmas $p, array $filters): array
    {
        [$a, $z] = [$p->awal()->toDateString(), $p->akhir()->toDateString()];

        // 1) Layanan per kunjungan: per anak MAX(kolom = 1) → pernah ya; MAX(kolom IS NOT NULL) → terisi.
        $layananDef = config('kesmas.layanan');
        $kolom = array_keys($layananDef);
        $perAnak = DB::table('data_anak')
            ->selectRaw('id_anak, ' . implode(', ', array_map(
                fn ($k) => "MAX({$k} = 1) as {$k}_ya, MAX({$k} IS NOT NULL) as {$k}_isi", $kolom
            )))
            ->whereBetween('tgl_kunjungan', [$a, $z])
            ->groupBy('id_anak');
        $r = (array) $this->dariSasaran($p, $filters)
            ->leftJoinSub($perAnak, 'l', 'l.id_anak', '=', 's.id')
            ->where('s.umur', '<=', 72)
            ->selectRaw('COUNT(*) as sasaran, ' . implode(', ', array_map(
                fn ($k) => "SUM(COALESCE({$k}_ya,0)) as {$k}_ya, SUM(COALESCE({$k}_isi,0)) as {$k}_isi", $kolom
            )))
            ->first();
        $sasaran = (int) $r['sasaran'];
        $layanan = [];
        $adaLayanan = false;
        foreach ($layananDef as $k => $def) {
            $ya  = (int) $r["{$k}_ya"];
            $isi = (int) $r["{$k}_isi"];
            $adaLayanan = $adaLayanan || $isi > 0;
            $layanan[$k] = [
                'label' => $def['label'], 'badge' => $def['badge'],
                'ya' => $ya, 'terisi' => $isi, 'persen' => self::persen($ya, $isi), 'belum_diisi' => $sasaran - $isi,
            ];
        }

        // 2) Skrining neonatal — bayi 0–11 bulan (kolom enum di tabel anak).
        //
        // Daftar barisnya ditulis di sini, TIDAK dibaca dari config seperti $layananDef
        // di atas, karena label dan flag 'terbalik' memang belum punya rumah di
        // config/kesmas.php (di sana hanya ada daftar OPSI tiap field). Konsekuensinya:
        // menambah field skrining/sanitasi baru ke migrasi + config TIDAK otomatis
        // memunculkannya di dasbor — barisnya hilang diam-diam, tanpa error. Kalau
        // menambah satu, sentuh ketiganya: $skriningDef/$sanitasiDef di sini,
        // KOLOM_KESMAS_ANAK (agar kolomnya ikut di-select subquery `s`), dan
        // config/kesmas.php untuk daftar opsinya.
        $skriningDef = [
            'skrining_shk'            => ['SHK (hipotiroid kongenital)', 'skrining'],
            'skrining_shak'           => ['SHAK (hiperplasia adrenal)', 'skrining'],
            'skrining_g6pd'           => ['G6PD', 'skrining'],
            'pemeriksaan_hepatitis_b' => ['Hepatitis B', 'hepatitis_b'],
        ];
        $sel = ['COUNT(*) as bayi'];
        foreach ($skriningDef as $k => [, $opsi]) {
            $sel[] = "SUM({$k} IS NOT NULL) as {$k}_isi";
            foreach (array_keys(config("kesmas.{$opsi}")) as $nilai) {
                $sel[] = "SUM({$k} = '{$nilai}') as {$k}_{$nilai}";
            }
        }
        $rs = (array) $this->dariSasaran($p, $filters)->whereBetween('s.umur', [0, 11])->selectRaw(implode(', ', $sel))->first();
        $bayi = (int) $rs['bayi'];
        $skrining = [];
        $adaSkrining = false;
        foreach ($skriningDef as $k => [$label, $opsi]) {
            $isi = (int) $rs["{$k}_isi"];
            $adaSkrining = $adaSkrining || $isi > 0;
            $sebaran = [];
            foreach (config("kesmas.{$opsi}") as $nilai => $labelNilai) {
                $n = (int) $rs["{$k}_{$nilai}"];
                $sebaran[$nilai] = ['label' => $labelNilai, 'n' => $n, 'persen' => self::persen($n, $isi)];
            }
            $skrining[$k] = ['label' => $label, 'terisi' => $isi, 'sebaran' => $sebaran, 'belum_diisi' => $bayi - $isi];
        }

        // 3) Sanitasi rumah — 0–72 bulan. 'terbalik' = nilai tinggi berarti buruk (warna dibalik di view).
        $sanitasiDef = [
            'air_bersih'       => ['Akses air bersih', false],
            'jamban_sehat'     => ['Jamban sehat', false],
            'merokok_keluarga' => ['Ada anggota keluarga merokok', true],
        ];
        $sel = ['COUNT(*) as sasaran'];
        foreach (array_keys($sanitasiDef) as $k) {
            $sel[] = "SUM({$k} IS NOT NULL) as {$k}_isi";
            $sel[] = "SUM({$k} = 1) as {$k}_ya";
        }
        $rn = (array) $this->dariSasaran($p, $filters)->where('s.umur', '<=', 72)->selectRaw(implode(', ', $sel))->first();
        $sanitasi = [];
        $adaSanitasi = false;
        foreach ($sanitasiDef as $k => [$label, $terbalik]) {
            $isi = (int) $rn["{$k}_isi"];
            $ya  = (int) $rn["{$k}_ya"];
            $adaSanitasi = $adaSanitasi || $isi > 0;
            $sanitasi[$k] = [
                'label' => $label, 'ya' => $ya, 'terisi' => $isi, 'persen' => self::persen($ya, $isi),
                'belum_diisi' => (int) $rn['sasaran'] - $isi, 'terbalik' => $terbalik,
            ];
        }

        return [
            'sasaran'  => $sasaran,
            'bayi'     => $bayi,
            'layanan'  => ['baris' => $layanan, 'ada_data' => $adaLayanan],
            'skrining' => ['baris' => $skrining, 'ada_data' => $adaSkrining],
            'sanitasi' => ['baris' => $sanitasi, 'ada_data' => $adaSanitasi],
        ];
    }
}
