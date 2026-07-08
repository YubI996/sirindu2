<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Rekap cakupan intervensi & daftar anak prioritas beserta intervensinya.
 * Anak prioritas = baris prioritas_gizi dengan prioritas (P1-P3) NOT NULL.
 */
class IntervensiGiziService
{
    /**
     * @param array{kec?:?int,kel?:?int,rt?:?int,posyandu?:?int} $f
     * @return array{total_prioritas:int,sudah:int,persen:float}
     */
    public function rekap(array $f): array
    {
        $base = DB::table('prioritas_gizi as p')->whereNotNull('p.prioritas');
        $this->applyWilayah($base, $f);

        $totalPrioritas = (clone $base)->count();
        $sudah = (clone $base)->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('intervensi_gizi as iv')
                ->whereColumn('iv.id_anak', 'p.id_anak');
        })->count();

        return [
            'total_prioritas' => $totalPrioritas,
            'sudah'           => $sudah,
            'persen'          => $totalPrioritas > 0 ? round($sudah / $totalPrioritas * 100, 1) : 0.0,
        ];
    }

    /** Daftar anak prioritas + intervensinya, terurut prioritas lalu nama. */
    public function daftarPrioritas(array $f): array
    {
        $q = DB::table('prioritas_gizi as p')
            ->whereNotNull('p.prioritas')
            ->join('anak as a', 'a.id', '=', 'p.id_anak')
            ->leftJoin('kelurahan as k', 'a.id_kel', '=', 'k.id')
            ->leftJoin('rt', 'a.id_rt', '=', 'rt.id')
            ->leftJoin('posyandu as pos', 'a.id_posyandu', '=', 'pos.id')
            ->select('p.id_anak', 'p.prioritas', 'a.nama', 'a.nik',
                'k.name as kelurahan', 'rt.name as rt', 'pos.name as posyandu');
        $this->applyWilayah($q, $f);
        $rows = $q->orderBy('p.prioritas')->orderBy('a.nama')->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $intervensi = DB::table('intervensi_gizi')
            ->whereIn('id_anak', $rows->pluck('id_anak')->all())
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->get()
            ->groupBy('id_anak');

        $hasil = [];
        foreach ($rows as $r) {
            $ivs = $intervensi->get($r->id_anak, collect());
            $hasil[] = [
                'id_anak'           => (int) $r->id_anak,
                'hashid'            => HashIdService::encode((int) $r->id_anak, 'anak'),
                'nama'              => $r->nama,
                'nik'               => $r->nik,
                'prioritas'         => (int) $r->prioritas,
                'kelurahan'         => $r->kelurahan ?: '-',
                'rt'                => $r->rt ?: '-',
                'posyandu'          => $r->posyandu ?: '-',
                'jumlah_intervensi' => $ivs->count(),
                'intervensi'        => $ivs->map(fn ($i) => [
                    'id'        => $i->id,
                    'jenis'     => $i->jenis,
                    'tanggal'   => $i->tanggal,
                    'pelaksana' => $i->pelaksana,
                    'status'    => $i->status,
                    'catatan'   => $i->catatan,
                ])->values()->all(),
            ];
        }

        return $hasil;
    }

    /** Filter wilayah pada kolom denormalisasi prioritas_gizi (alias p). */
    private function applyWilayah($q, array $f): void
    {
        if (!empty($f['kec']))      $q->where('p.id_kec', $f['kec']);
        if (!empty($f['kel']))      $q->where('p.id_kel', $f['kel']);
        if (!empty($f['rt']))       $q->where('p.id_rt', $f['rt']);
        if (!empty($f['posyandu'])) $q->where('p.id_posyandu', $f['posyandu']);
    }
}
