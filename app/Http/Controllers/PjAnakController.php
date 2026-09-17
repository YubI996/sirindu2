<?php

namespace App\Http\Controllers;

use App\Models\Anak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penanggung jawab (PJ) per anak — diisi inline dari modal daftar anak di
 * dasbor Operasi Timbang. Hanya nama; nama kosong menghapus PJ.
 */
class PjAnakController extends Controller
{
    public function update(Request $request, Anak $anak): JsonResponse
    {
        $this->pastikanBolehAksesAnak($anak);

        $data = $request->validate([
            'pj_nama' => 'nullable|string|max:'.Anak::PJ_NAMA_MAKS,
        ]);

        $nama = trim((string) ($data['pj_nama'] ?? ''));
        $user = auth()->user();

        $anak->forceFill([
            'pj_nama'       => $nama !== '' ? $nama : null,
            'pj_updated_by' => $user->id,
            'pj_updated_at' => now(),
        ])->save();

        return response()->json([
            'id'           => $anak->hashid,
            'pj_nama'      => $anak->pj_nama,
            'pj_oleh'      => $user->name,
            'pj_updated_at'=> $anak->pj_updated_at?->toDateTimeString(),
        ]);
    }

    /** Scoping tulis: user non-super hanya boleh mengelola anak di kelurahannya. */
    private function pastikanBolehAksesAnak(Anak $anak): void
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            return;
        }
        abort_if(!$user->id_kel, 403);
        abort_if((int) $anak->id_kel !== (int) $user->id_kel, 403);
    }
}
