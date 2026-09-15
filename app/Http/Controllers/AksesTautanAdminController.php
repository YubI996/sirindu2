<?php

namespace App\Http\Controllers;

use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\RtAksesTautan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kelola tautan akses RT (mode B scoping akses): puskesmas untuk RT di kelurahannya,
 * Dinkes untuk semua. Token hanya ditampilkan sekali, tepat setelah dibuat.
 */
class AksesTautanAdminController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $this->bolehKelola($user);

        $kel = $user->isSuperAdmin() ? (int) $request->query('kel') : (int) $user->id_kel;

        $rtList = Rt::query()
            ->with(['kelurahan:id,name', 'tautanAktif.pembuat:id,name'])
            ->when($kel, fn ($q) => $q->where('id_kelurahan', $kel))
            ->orderBy('id_kelurahan')->orderBy('name')
            ->get();

        return view('admin.verifikasi-rt.tautan-akses', [
            'rtList'   => $rtList,
            'kelList'  => $user->isSuperAdmin() ? Kelurahan::orderBy('name')->get(['id', 'name']) : collect(),
            'kel'      => $kel,
            'hariOpsi' => [7, 14, 30, 60, 90],
        ]);
    }

    public function buat(Request $request, Rt $rt): RedirectResponse
    {
        $user = $request->user();
        $this->bolehKelola($user, $rt);
        $valid = $request->validate(['hari' => 'sometimes|required|integer|min:1|max:365']);
        $hari = (int) ($valid['hari'] ?? RtAksesTautan::HARI_DEFAULT);

        $t = RtAksesTautan::buat($rt, $user, $hari);
        $rt->load('kelurahan');

        // Token plaintext hanya lewat flash sesi sekali ini; halaman berikutnya cuma tahu hash-nya.
        return redirect()->route('admin.aksesTautan.index', $user->isSuperAdmin() ? ['kel' => $rt->id_kelurahan] : [])
            ->with('tautan_baru', [
                'rt'          => $rt->name,
                'kelurahan'   => $rt->kelurahan?->name,
                'url'         => route('rt.akses.masuk', $t['token']),
                'kedaluwarsa' => $t['model']->kedaluwarsa_at->translatedFormat('d F Y'),
            ]);
    }

    public function cabut(Request $request, RtAksesTautan $tautan): RedirectResponse
    {
        $user = $request->user();
        $this->bolehKelola($user, $tautan->rt);
        $tautan->cabut();

        return redirect()->route('admin.aksesTautan.index', $user->isSuperAdmin() ? ['kel' => $tautan->rt?->id_kelurahan] : [])
            ->with('success', "Tautan {$tautan->rt?->name} dicabut. Tautan lama tidak bisa dipakai lagi.");
    }

    /** Superadmin: semua; faskes: hanya RT di kelurahannya; peran lain (termasuk rt) ditolak. */
    private function bolehKelola($user, ?Rt $rt = null): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        abort_if($user->isRt() || !$user->id_kel, 403, 'Hanya puskesmas atau Dinkes yang mengelola tautan akses RT.');
        if ($rt && (int) $rt->id_kelurahan !== (int) $user->id_kel) {
            abort(403, 'RT itu di luar kelurahan Anda.');
        }
    }
}
