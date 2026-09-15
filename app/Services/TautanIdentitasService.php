<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tautan identitas anak (spec verifikasi RT §3.1, §4 tab 3, §6): pindai kandidat,
 * cakupan per RT, keputusan RT (sama/beda), reviu. Merge fisik ada di tahap 3.
 */
class TautanIdentitasService
{
    public function __construct(
        private readonly IdentitasMatcher $matcher,
        private readonly VerifikasiRtService $verifikasi,
    ) {
    }

    // =========================================================================
    // Pindai (precompute anak_kandidat)
    // =========================================================================

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

    // =========================================================================
    // Kandidat per RT & keputusan (spec §4 tab 3, §6)
    // =========================================================================

    /** Id anak dalam cakupan RT: warga ∪ sekelurahan tanpa RT (yang belum dinyatakan bukan warga). */
    private function idCakupan(Rt $rt): array
    {
        return array_values(array_unique(array_merge(
            $this->verifikasi->wargaQuery($rt)->pluck('id')->all(),
            $this->verifikasi->tanpaRtQuery($rt)->pluck('id')->all(),
        )));
    }

    /** Kandidat yang ≥1 anggotanya dalam cakupan RT, minus pasangan yang sudah diputus (kecuali ditolak). */
    public function kandidatUntukRt(Rt $rt): Collection
    {
        $ids = $this->idCakupan($rt);
        if (empty($ids)) {
            return collect();
        }

        return AnakKandidat::query()
            ->with(['anakA.posyandu:id,name', 'anakA.kel:id,name', 'anakA.rt:id,name', 'anakB.posyandu:id,name', 'anakB.kel:id,name', 'anakB.rt:id,name'])
            ->where(fn ($q) => $q->whereIn('id_anak_a', $ids)->orWhereIn('id_anak_b', $ids))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('anak_tautan as t')
                  ->whereColumn('t.id_anak_a', 'anak_kandidat.id_anak_a')
                  ->whereColumn('t.id_anak_b', 'anak_kandidat.id_anak_b')
                  ->where('t.status', '!=', 'ditolak');
            })
            ->orderByDesc('skor')
            ->get();
    }

    /** Keputusan RT atas satu kandidat. Menimpa usulan lama yang belum disetujui. */
    public function putuskan(int $idX, int $idY, Rt $rt, ?User $oleh, string $keputusan, ?string $catatan = null, ?string $pelaksana = null): AnakTautan
    {
        if (!in_array($keputusan, AnakTautan::KEPUTUSAN, true)) {
            throw new InvalidArgumentException("Keputusan tidak dikenal: {$keputusan}");
        }
        [$a, $b] = AnakTautan::urut($idX, $idY);

        $kandidat = AnakKandidat::where('id_anak_a', $a)->where('id_anak_b', $b)->first();
        if (!$kandidat) {
            throw new InvalidArgumentException('Pasangan ini bukan kandidat hasil pindai.');
        }

        $anakA = Anak::findOrFail($a);
        $anakB = Anak::findOrFail($b);
        if (!$this->verifikasi->dalamCakupan($anakA, $rt) && !$this->verifikasi->dalamCakupan($anakB, $rt)) {
            throw new AuthorizationException('Pasangan di luar cakupan RT ini.');
        }

        $t = AnakTautan::where('id_anak_a', $a)->where('id_anak_b', $b)->first();
        if ($t && in_array($t->status, ['disetujui', 'digabung'], true)) {
            throw new InvalidArgumentException('Pasangan ini sudah diputus dan disetujui.');
        }

        $data = [
            'keputusan'      => $keputusan,
            'skor'           => $kandidat->skor,
            'via'            => $kandidat->via,
            'status'         => 'diusulkan',
            'id_rt'          => $rt->id,
            'diusulkan_oleh' => $oleh?->id,
            'pelaksana'      => $pelaksana ?: null,
            'diusulkan_at'   => now(),
            'ditinjau_oleh'  => null,
            'ditinjau_at'    => null,
            'catatan'        => $catatan ?: null,
            'catatan_reviu'  => null,
        ];

        if ($t) {
            $t->update($data);
            return $t->fresh();
        }

        return AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b] + $data);
    }

    /** Reviu puskesmas (kelurahan salah satu anak) / Dinkes. */
    public function tinjauTautan(AnakTautan $t, User $peninjau, bool $setuju, ?string $catatan = null): AnakTautan
    {
        if ($t->status !== 'diusulkan') {
            throw new InvalidArgumentException('Tautan ini sudah ditinjau.');
        }
        if (!$peninjau->isSuperAdmin()) {
            if (!$peninjau->id_kel || !in_array((int) $peninjau->id_kel, $this->kelurahanTautan($t), true)) {
                throw new AuthorizationException('Tautan di luar kelurahan peninjau.');
            }
        }

        $t->update([
            'status'        => $setuju ? 'disetujui' : 'ditolak',
            'ditinjau_oleh' => $peninjau->id,
            'ditinjau_at'   => now(),
            'catatan_reviu' => $catatan ?: null,
        ]);

        return $t->fresh();
    }

    /** Kelurahan yang "memiliki" tautan: id_kel kedua anak, atau kelurahan RT pemutus bila keduanya kosong. */
    private function kelurahanTautan(AnakTautan $t): array
    {
        $kel = array_filter([(int) $t->anakA?->id_kel, (int) $t->anakB?->id_kel]);
        if (empty($kel)) {
            $kel = [(int) ($t->rt?->id_kelurahan ?? $t->pengusul?->rt?->id_kelurahan)];
        }
        return array_values(array_unique($kel));
    }

    /** Antrean tautan `diusulkan`, dibatasi kelurahan untuk non-superadmin. */
    public function antreanTautanQuery(User $peninjau): Builder
    {
        $q = AnakTautan::query()->with(['anakA', 'anakB', 'pengusul', 'rt'])->where('status', 'diusulkan');

        if (!$peninjau->isSuperAdmin()) {
            $kel = (int) $peninjau->id_kel;
            $q->where(function ($w) use ($kel) {
                $w->whereHas('anakA', fn ($a) => $a->where('id_kel', $kel))
                  ->orWhereHas('anakB', fn ($a) => $a->where('id_kel', $kel))
                  ->orWhere(fn ($x) => $x->whereHas('anakA', fn ($a) => $a->whereNull('id_kel'))
                                          ->whereHas('anakB', fn ($a) => $a->whereNull('id_kel'))
                                          ->whereHas('rt', fn ($r) => $r->where('id_kelurahan', $kel)));
            });
        }

        return $q->orderByDesc('skor')->orderBy('id');
    }

    /** Tautan `sama` yang disetujui dan belum digabung (dikonsumsi T3). */
    public function menungguGabung(): int
    {
        return AnakTautan::where('keputusan', 'sama')->where('status', 'disetujui')->count();
    }
}
