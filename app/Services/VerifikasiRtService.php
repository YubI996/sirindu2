<?php

namespace App\Services;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Seluruh aturan verifikasi RT (spec docs/superpowers/specs/2026-09-14-verifikasi-rt-design.md
 * §3–§6): cakupan anak per RT, usulan RT, reviu puskesmas/Dinkes, dan denormalisasi ke
 * anak.verif_rt_*. Controller tidak boleh menulis tabel ini sendiri.
 */
class VerifikasiRtService
{
    /** Catatan reviu untuk usulan yang digantikan usulan baru dari RT yang sama (bukan keputusan peninjau). */
    public const CATATAN_DIGANTIKAN = 'Digantikan usulan baru';

    /** Anak yang sudah dipetakan ke RT ini (tab "Warga RT"). */
    public function wargaQuery(Rt $rt): Builder
    {
        return Anak::query()->where('id_rt', $rt->id);
    }

    /**
     * Anak sekelurahan yang belum ber-RT (tab "Belum ber-RT"), minus yang sudah dinyatakan
     * "bukan warga RT ini" oleh RT ini (selama pernyataan itu belum ditolak peninjau).
     */
    public function tanpaRtQuery(Rt $rt): Builder
    {
        return Anak::query()
            ->where('id_kel', $rt->id_kelurahan)
            ->whereNull('id_rt')
            ->whereNotExists(function ($q) use ($rt) {
                $q->select(DB::raw(1))->from('verifikasi_anak as va')
                  ->whereColumn('va.id_anak', 'anak.id')
                  ->where('va.id_rt', $rt->id)
                  ->where('va.status', 'bukan_rt_ini')
                  ->where('va.reviu', '!=', 'ditolak');
            });
    }

    public function dalamCakupan(Anak $anak, Rt $rt): bool
    {
        if ((int) $anak->id_rt === (int) $rt->id) {
            return true;
        }

        return $anak->id_rt === null && (int) $anak->id_kel === (int) $rt->id_kelurahan;
    }

    /**
     * Usulan RT. Usulan lama yang masih `diusulkan` dari RT yang sama untuk anak yang sama
     * ditandai `ditolak` ("Digantikan usulan baru") supaya antrean hanya memuat satu usulan per anak.
     */
    public function usulkan(Anak $anak, Rt $rt, User $oleh, string $status, ?string $catatan = null): VerifikasiAnak
    {
        if (!in_array($status, VerifikasiAnak::STATUS, true)) {
            throw new InvalidArgumentException("Status verifikasi tidak dikenal: {$status}");
        }
        if (!$this->dalamCakupan($anak, $rt)) {
            throw new AuthorizationException('Anak di luar cakupan RT ini.');
        }
        if ($status === 'bukan_rt_ini' && $anak->id_rt !== null) {
            throw new InvalidArgumentException('"Bukan warga RT ini" hanya untuk anak yang belum ber-RT.');
        }

        return DB::transaction(function () use ($anak, $rt, $oleh, $status, $catatan) {
            VerifikasiAnak::where('id_anak', $anak->id)
                ->where('id_rt', $rt->id)
                ->where('reviu', 'diusulkan')
                ->update([
                    'reviu'         => 'ditolak',
                    'catatan_reviu' => self::CATATAN_DIGANTIKAN,
                    'ditinjau_at'   => now(),
                ]);

            $v = VerifikasiAnak::create([
                'id_anak'        => $anak->id,
                'id_rt'          => $rt->id,
                'status'         => $status,
                // Klaim: anak belum ber-RT dinyatakan berdomisili → setelah disetujui, id_rt diisi
                'klaim_id_rt'    => ($status === 'berdomisili' && $anak->id_rt === null) ? $rt->id : null,
                'catatan'        => $catatan ?: null,
                'diusulkan_oleh' => $oleh->id,
                'diusulkan_at'   => now(),
                'reviu'          => 'diusulkan',
            ]);

            $this->segarkanDenormalisasi($anak);

            return $v;
        });
    }

    /** Reviu puskesmas (kelurahannya) / Dinkes (semua). */
    public function tinjau(VerifikasiAnak $v, User $peninjau, bool $setuju, ?string $catatan = null): VerifikasiAnak
    {
        if ($v->reviu !== 'diusulkan') {
            throw new InvalidArgumentException('Usulan ini sudah ditinjau.');
        }
        $this->pastikanBolehMeninjau($v, $peninjau);

        return DB::transaction(function () use ($v, $peninjau, $setuju, $catatan) {
            $v->update([
                'reviu'         => $setuju ? 'disetujui' : 'ditolak',
                'ditinjau_oleh' => $peninjau->id,
                'ditinjau_at'   => now(),
                'catatan_reviu' => $catatan ?: null,
            ]);

            $anak = $v->anak;

            if ($setuju && $v->klaim_id_rt) {
                $rt = Rt::findOrFail($v->klaim_id_rt);

                // Klaim RT lain atas anak yang sama tak lagi bermakna — tutup agar antrean bersih dan
                // id_rt tidak bolak-balik bila peninjau menyetujui keduanya.
                VerifikasiAnak::where('id_anak', $anak->id)
                    ->where('id', '!=', $v->id)
                    ->whereNotNull('klaim_id_rt')
                    ->where('reviu', 'diusulkan')
                    ->update([
                        'reviu'         => 'ditolak',
                        'ditinjau_oleh' => $peninjau->id,
                        'ditinjau_at'   => now(),
                        'catatan_reviu' => "Anak sudah dimasukkan ke {$rt->name} lewat klaim RT lain",
                    ]);

                $update = ['id_rt' => $rt->id];
                if (!$anak->id_kel) {
                    $update['id_kel'] = $rt->id_kelurahan;
                }
                if (!$anak->id_posyandu) { // posyandu yang sudah terisi tidak ditimpa (spec §6.2)
                    $update['id_posyandu'] = $rt->id_posyandu;
                }
                // Perubahan domisili sungguhan → updated_at BOLEH ikut berubah (pakai Eloquent)
                $anak->update($update);
            }

            $this->segarkanDenormalisasi($anak);

            return $v->fresh();
        });
    }

    /** Antrean reviu: `diusulkan`, dibatasi kelurahan untuk non-superadmin. */
    public function antreanQuery(User $peninjau): Builder
    {
        $q = VerifikasiAnak::query()->with(['anak', 'rt', 'pengusul'])->where('reviu', 'diusulkan');

        if (!$peninjau->isSuperAdmin()) {
            $kel = (int) $peninjau->id_kel;
            $q->where(function ($w) use ($kel) {
                $w->whereHas('anak', fn ($a) => $a->where('id_kel', $kel))
                  ->orWhere(fn ($x) => $x->whereHas('anak', fn ($a) => $a->whereNull('id_kel'))
                                          ->whereHas('rt', fn ($r) => $r->where('id_kelurahan', $kel)));
            });
        }

        return $q->orderBy('id');
    }

    /**
     * anak.verif_rt_* = usulan terbaru yang bukan `bukan_rt_ini`. Ditulis lewat query builder
     * agar anak.updated_at tidak tersentuh (heuristik CapilDedupService::sigiziUntouched()).
     */
    public function segarkanDenormalisasi(Anak $anak): void
    {
        $terbaru = VerifikasiAnak::where('id_anak', $anak->id)
            ->where('status', '!=', 'bukan_rt_ini')
            // Usulan yang digantikan RT sendiri bukan keputusan — jangan sampai tampil sebagai "ditolak"
            ->where(fn ($q) => $q->whereNull('catatan_reviu')->orWhere('catatan_reviu', '!=', self::CATATAN_DIGANTIKAN))
            ->orderByDesc('id')
            ->first();

        DB::table('anak')->where('id', $anak->id)->update([
            'verif_rt_status' => $terbaru?->status,
            'verif_rt_reviu'  => $terbaru?->reviu,
            'verif_rt_at'     => $terbaru?->diusulkan_at,
        ]);
    }

    // =========================================================================
    // Dasbor Gizi (spec §7)
    // =========================================================================

    /** Nilai filter "Status verifikasi RT" di Dasbor Gizi. */
    public const FILTER_VERIF = ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal', 'belum'];

    /**
     * Terapkan filter status verifikasi ke kueri anak. Kosong / tak dikenal = tanpa filter (Semua).
     * `belum` = belum pernah diusulkan, masih menunggu reviu, atau ditolak — pokoknya belum final.
     */
    public function terapkanFilterVerif($query, ?string $verif, string $tabel = 'anak'): void
    {
        if (!$verif || !in_array($verif, self::FILTER_VERIF, true)) {
            return;
        }
        if ($verif === 'belum') {
            $query->where(fn ($w) => $w->whereNull("$tabel.verif_rt_reviu")->orWhere("$tabel.verif_rt_reviu", '!=', 'disetujui'));
            return;
        }
        $query->where("$tabel.verif_rt_status", $verif)->where("$tabel.verif_rt_reviu", 'disetujui');
    }

    /** @return array{total:int, berdomisili:int, pindah:int, meninggal:int, tidak_dikenal:int, menunggu:int, belum:int} */
    public function ringkasanVerifikasi(?int $idKel = null): array
    {
        $q = DB::table('anak');
        if ($idKel) {
            $q->where('id_kel', $idKel);
        }
        $r = $q->selectRaw("
            COUNT(*) AS total,
            SUM(verif_rt_status = 'berdomisili'   AND verif_rt_reviu = 'disetujui') AS berdomisili,
            SUM(verif_rt_status = 'pindah'        AND verif_rt_reviu = 'disetujui') AS pindah,
            SUM(verif_rt_status = 'meninggal'     AND verif_rt_reviu = 'disetujui') AS meninggal,
            SUM(verif_rt_status = 'tidak_dikenal' AND verif_rt_reviu = 'disetujui') AS tidak_dikenal,
            SUM(verif_rt_reviu = 'diusulkan') AS menunggu
        ")->first();

        $out = [
            'total'         => (int) $r->total,
            'berdomisili'   => (int) $r->berdomisili,
            'pindah'        => (int) $r->pindah,
            'meninggal'     => (int) $r->meninggal,
            'tidak_dikenal' => (int) $r->tidak_dikenal,
            'menunggu'      => (int) $r->menunggu,
        ];
        $out['belum'] = $out['total'] - $out['berdomisili'] - $out['pindah'] - $out['meninggal'] - $out['tidak_dikenal'] - $out['menunggu'];

        return $out;
    }

    /** @return array{total:int, diverifikasi:int} */
    public function progres(Rt $rt): array
    {
        $total        = $this->wargaQuery($rt)->count();
        $diverifikasi = $this->wargaQuery($rt)->whereNotNull('verif_rt_status')->count();

        return ['total' => $total, 'diverifikasi' => $diverifikasi];
    }

    private function pastikanBolehMeninjau(VerifikasiAnak $v, User $peninjau): void
    {
        if ($peninjau->isSuperAdmin()) {
            return;
        }
        if (!$peninjau->id_kel) {
            throw new AuthorizationException('Akun peninjau tidak punya kelurahan.');
        }
        $kelAnak = $v->anak->id_kel ?: $v->rt->id_kelurahan;
        if ((int) $kelAnak !== (int) $peninjau->id_kel) {
            throw new AuthorizationException('Usulan di luar kelurahan peninjau.');
        }
    }
}
