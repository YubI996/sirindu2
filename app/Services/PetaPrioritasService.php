<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Agregasi snapshot prioritas_gizi per wilayah (kecamatan/kelurahan/rt),
 * di-key nama wilayah agar cocok dengan pencocokan GeoJSON di peta.
 * Denominator prevalensi = anak terukur (usia_bln non-null).
 */
class PetaPrioritasService
{
    /** @return array<string,array<string,int|float>> */
    public function agregat(string $level): array
    {
        [$kolomId, $tabel] = match ($level) {
            'kecamatan' => ['id_kec', 'kecamatan'],
            'rt'        => ['id_rt', 'rt'],
            default     => ['id_kel', 'kelurahan'],
        };

        $rows = DB::table('prioritas_gizi as p')
            ->join("{$tabel} as w", "p.{$kolomId}", '=', 'w.id')
            ->whereNotNull('p.usia_bln')
            ->groupBy('w.name')
            ->selectRaw('w.name as nama')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(p.stunting) as stunting')
            ->selectRaw('SUM(p.gizi_buruk) as gizi_buruk')
            ->selectRaw('SUM(p.gizi_kurang) as gizi_kurang')
            ->selectRaw('SUM(p.bb_tidak_naik) as bb_tidak_naik')
            ->selectRaw('SUM(CASE WHEN p.prioritas IS NOT NULL THEN 1 ELSE 0 END) as anak_prioritas')
            ->get();

        $hasil = [];
        foreach ($rows as $r) {
            $total = (int) $r->total;
            if ($total === 0) {
                continue;
            }
            $giziKB = (int) $r->gizi_buruk + (int) $r->gizi_kurang;
            $hasil[$r->nama] = [
                'total'                 => $total,
                'stunting'              => (int) $r->stunting,
                'gizi_buruk'            => (int) $r->gizi_buruk,
                'gizi_kurang'           => (int) $r->gizi_kurang,
                'bb_tidak_naik'         => (int) $r->bb_tidak_naik,
                'anak_prioritas'        => (int) $r->anak_prioritas,
                'stunting_pct'          => round((int) $r->stunting / $total * 100, 1),
                'gizi_kurang_buruk_pct' => round($giziKB / $total * 100, 1),
                'bb_tidak_naik_pct'     => round((int) $r->bb_tidak_naik / $total * 100, 1),
            ];
        }

        return $hasil;
    }
}
