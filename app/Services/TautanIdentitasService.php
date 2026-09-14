<?php

namespace App\Services;

use App\Models\AnakKandidat;
use Illuminate\Support\Facades\DB;

/**
 * Tautan identitas anak (spec verifikasi RT §3.1, §4 tab 3, §6): pindai kandidat,
 * cakupan per RT, keputusan RT (sama/beda), reviu.
 */
class TautanIdentitasService
{
    public function __construct(private readonly IdentitasMatcher $matcher)
    {
    }

    /**
     * Pindai ulang seluruh anak → anak_kandidat (truncate + insert). Idempoten.
     *
     * @return array{pasangan:int, via:array<string,int>, dipindai_at:string}
     */
    public function pindai(): array
    {
        $anak = DB::table('anak')
            ->select('id', 'nik', 'nama', 'tgl_lahir', 'no_kk', 'nama_ibu', 'nama_ayah', 'sumber')
            ->get();

        $pairs = $this->matcher->pindaiSemua($anak);
        $now   = now();
        $via   = [];

        DB::transaction(function () use ($pairs, $now, &$via) {
            DB::table('anak_kandidat')->delete();
            foreach (array_chunk($pairs, 500) as $chunk) {
                DB::table('anak_kandidat')->insert(array_map(fn ($p) => [
                    'id_anak_a'   => $p['a'],
                    'id_anak_b'   => $p['b'],
                    'skor'        => round($p['score'], 2),
                    'via'         => $p['via'],
                    'child_sim'   => round($p['child'], 2),
                    'parent_sim'  => round($p['parent'], 2),
                    'dipindai_at' => $now,
                ], $chunk));
            }
            foreach ($pairs as $p) {
                $via[$p['via']] = ($via[$p['via']] ?? 0) + 1;
            }
        });

        ksort($via);

        return ['pasangan' => count($pairs), 'via' => $via, 'dipindai_at' => $now->toDateTimeString()];
    }

    /** @return array{jumlah:int, dipindai_at:?string} */
    public function ringkasanKandidat(): array
    {
        return [
            'jumlah'      => AnakKandidat::count(),
            'dipindai_at' => AnakKandidat::max('dipindai_at'),
        ];
    }
}
