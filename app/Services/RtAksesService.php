<?php

namespace App\Services;

use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Siapa yang membuka halaman RT dan RT mana yang aktif (scoping akses Sept 2026).
 *
 * Urutan: sesi tautan (mode B, tanpa akun) → superadmin (?rt= wajib) → akun per-RT (id_rt terisi)
 * → akun per-kelurahan (users.rt_sekelurahan=1; memilih RT sekelurahan lewat ?rt=, diingat di sesi).
 * Mode tautan & kelurahan wajib menyebut nama pengisi (`pelaksana`) di tiap usulan.
 */
class RtAksesService
{
    public const SESI_TAUTAN    = 'rt_akses_id';
    public const SESI_PILIHAN   = 'rt_pilihan';
    public const SESI_PELAKSANA = 'rt_pelaksana';

    /**
     * @return array{mode: string, rt: ?Rt, user: ?User, tautan: ?RtAksesTautan, rt_list: Collection, butuh_pelaksana: bool}
     */
    public function konteks(Request $r): array
    {
        $dasar = ['rt' => null, 'user' => null, 'tautan' => null, 'rt_list' => collect(), 'butuh_pelaksana' => false];

        if ($tautan = $this->tautanAktif($r)) {
            return ['mode' => 'tautan', 'rt' => $tautan->rt, 'tautan' => $tautan, 'butuh_pelaksana' => true] + $dasar;
        }

        $user = $r->user();
        if (!$user) {
            return ['mode' => 'tamu'] + $dasar;
        }

        if ($user->isSuperAdmin()) {
            $rt = $r->query('rt') ? Rt::findOrFail((int) $r->query('rt')) : null;

            return ['mode' => 'superadmin', 'rt' => $rt, 'user' => $user] + $dasar;
        }

        // Akun per-RT (termasuk yang RT-nya terhapus → rt null → controller 403; tidak naik jadi akun kelurahan)
        if ($user->id_rt || !$user->rt_sekelurahan || !$user->id_kel) {
            return ['mode' => 'akun_rt', 'rt' => $user->id_rt ? Rt::find($user->id_rt) : null, 'user' => $user] + $dasar;
        }

        // Akun kelurahan: hanya RT di kelurahannya; pilihan disimpan di sesi supaya
        // request API berikutnya (tanpa ?rt=) tetap mengarah ke RT yang sama.
        $rtList = Rt::where('id_kelurahan', (int) $user->id_kel)->orderBy('name')->get();
        $rt     = null;
        if ($r->query('rt')) {
            $rt = $rtList->firstWhere('id', (int) $r->query('rt'));
            abort_if(!$rt, 403, 'RT itu di luar kelurahan akun ini.');
            $r->session()->put(self::SESI_PILIHAN, $rt->id);
        } elseif ($r->session()->has(self::SESI_PILIHAN)) {
            $rt = $rtList->firstWhere('id', (int) $r->session()->get(self::SESI_PILIHAN));
        }

        return ['mode' => 'akun_kel', 'rt' => $rt, 'user' => $user, 'rt_list' => $rtList, 'butuh_pelaksana' => true] + $dasar;
    }

    /** Tautan yang tersimpan di sesi, bila masih berlaku; sesi basi (dicabut/kedaluwarsa) dilupakan. */
    public function tautanAktif(Request $r): ?RtAksesTautan
    {
        $id = $r->session()->get(self::SESI_TAUTAN);
        if (!$id) {
            return null;
        }
        $t = RtAksesTautan::aktif()->with('rt.kelurahan')->find($id);
        if (!$t) {
            $r->session()->forget(self::SESI_TAUTAN);
        }

        return $t;
    }

    public function keluarTautan(Request $r): void
    {
        $r->session()->forget([self::SESI_TAUTAN, self::SESI_PILIHAN, self::SESI_PELAKSANA]);
    }
}
