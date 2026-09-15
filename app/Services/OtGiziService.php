<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Aturan populasi & klasifikasi gizi Operasi Timbang — diekstrak dari
 * TimbangDashboardController (15 Sep 2026) supaya dipakai bersama oleh dasbor OT
 * dan alokasi PJ. Logikanya TIDAK berubah: kunjungan OT terakhir per anak (bln<=60,
 * sumber operasi_timbang), klasifikasi persis rumus Dinkes dari z-score tersimpan.
 * Dikunci TimbangDashboardTerkunciTest & TimbangGiziBbTbTest.
 */
class OtGiziService
{
    public function __construct(private StatusGiziService $statusGizi)
    {
    }

    /** Filter dasbor OT: semua null (= seluruh kota, semua tahun) + override. */
    public function filterKosong(array $override = []): array
    {
        return array_merge(['tahun' => null, 'kec' => null, 'kel' => null, 'rt' => null, 'posyandu' => null], $override);
    }

    /** Terapkan filter wilayah pada query yg punya join/alias tabel anak. */
    public function applyWilayah($q, array $f, string $a = 'a'): void
    {
        if ($f['kec'])      $q->where("$a.id_kec", $f['kec']);
        if ($f['kel'])      $q->where("$a.id_kel", $f['kel']);
        if ($f['rt'])       $q->where("$a.id_rt", $f['rt']);
        if ($f['posyandu']) $q->where("$a.id_posyandu", $f['posyandu']);
    }

    /** Sub-query id data_anak = kunjungan OT TERAKHIR tiap anak (balita saja). */
    public function latestVisitQuery(array $f)
    {
        // Kunjungan TERAKHIR dihitung di antara kunjungan balita saja (da.bln<=60):
        // bila kelak anak yg dulu balita punya kunjungan usia SD, dashboard tetap
        // memakai kunjungan balita terakhirnya — kartu gizi tak diam-diam bergeser.
        $maxTgl = DB::table('data_anak as dm')
            ->join('anak as am', 'dm.id_anak', '=', 'am.id')
            ->selectRaw('dm.id_anak, MAX(dm.tgl_kunjungan) as max_tgl')
            ->whereNotNull('dm.tgl_kunjungan')
            ->where('dm.bln', '<=', 60)
            ->where('dm.sumber', 'operasi_timbang');
        if ($f['tahun']) $maxTgl->whereYear('dm.tgl_kunjungan', $f['tahun']);
        $this->applyWilayah($maxTgl, $f, 'am');
        $maxTgl->groupBy('dm.id_anak');

        return DB::table('data_anak as da')
            ->joinSub($maxTgl, 'm', function ($join) {
                $join->on('m.id_anak', '=', 'da.id_anak')
                     ->on('m.max_tgl', '=', 'da.tgl_kunjungan');
            })
            ->where('da.bln', '<=', 60)
            ->where('da.sumber', 'operasi_timbang')
            ->selectRaw('MAX(da.id) as max_id')
            ->groupBy('da.id_anak');
    }

    public function zval($v): ?float
    {
        return ($v === null || $v === '') ? null : (float) $v;
    }

    /**
     * Klasifikasi gizi kunjungan terakhir per anak (hanya bln<=60 & bb,tb>0).
     *
     * @return array<int, array{g:array, tgl:string}>  id_anak => ['g' => enumEppgbm, 'tgl' => tgl_kunjungan]
     */
    public function klasifikasiTerakhir(array $f): array
    {
        $rows = DB::table('data_anak as da')
            ->join('anak as a', 'da.id_anak', '=', 'a.id')
            ->whereIn('da.id', $this->latestVisitQuery($f))
            ->select('da.id_anak', 'da.bb', 'da.tb', 'da.bln', 'da.tgl_kunjungan', 'da.zscore_bb_u', 'da.zscore_pb_u', 'da.zscore_bb_pb')
            ->get();

        $out = [];
        foreach ($rows as $m) {
            if ($m->bln > 60 || $m->bb <= 0 || $m->tb <= 0) continue;
            $out[(int) $m->id_anak] = [
                'g'   => $this->statusGizi->enumEppgbm($this->zval($m->zscore_bb_u), $this->zval($m->zscore_pb_u), $this->zval($m->zscore_bb_pb)),
                'tgl' => $m->tgl_kunjungan,
            ];
        }

        return $out;
    }

    /** Apakah klasifikasi $g masuk kategori kartu/modal dasbor OT. */
    public static function kena(array $g, string $kategori): bool
    {
        return match ($kategori) {
            'stunting'    => in_array($g['tb_u'], ['severely_stunted', 'stunted'], true),
            'underweight' => in_array($g['bb_u'], ['severely_underweight', 'underweight'], true),
            'wasting'     => in_array($g['bb_tb'], ['wasted', 'severely_wasted'], true),
            'gizi_kurang' => $g['bb_tb'] === 'wasted',
            'gizi_buruk'  => $g['bb_tb'] === 'severely_wasted',
            default       => false,
        };
    }

    /** Id anak yang kena minimal satu dari $kategori. */
    public function idAnakMasalahGizi(array $f, array $kategori): array
    {
        $ids = [];
        foreach ($this->klasifikasiTerakhir($f) as $id => $k) {
            foreach ($kategori as $kat) {
                if (self::kena($k['g'], $kat)) {
                    $ids[] = $id;
                    break;
                }
            }
        }

        return $ids;
    }
}
