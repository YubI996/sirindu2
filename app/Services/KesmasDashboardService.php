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
}
