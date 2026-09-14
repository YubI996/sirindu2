<?php

namespace App\Http\Controllers;

use App\Models\Rt;
use App\Models\VerifikasiAnak;
use App\Services\VerifikasiRtService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Antrean reviu usulan RT (spec §6.1–6.2). Faskes melihat kelurahannya, superadmin semua.
 */
class VerifikasiRtReviuController extends Controller
{
    public function __construct(private readonly VerifikasiRtService $svc)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $q = $this->svc->antreanQuery($user);

        if ($request->query('rt')) {
            $q->where('id_rt', (int) $request->query('rt'));
        }
        if ($request->query('status') && in_array($request->query('status'), VerifikasiAnak::STATUS, true)) {
            $q->where('status', $request->query('status'));
        }

        $rtList = Rt::query()
            ->when(!$user->isSuperAdmin(), fn ($r) => $r->where('id_kelurahan', (int) $user->id_kel))
            ->orderBy('name')->get();

        return view('admin.verifikasi-rt.index', [
            'antrean' => $q->paginate(50)->withQueryString(),
            'rtList'  => $rtList,
            'label'   => VerifikasiAnak::LABEL_STATUS,
            'filter'  => ['rt' => $request->query('rt'), 'status' => $request->query('status')],
        ]);
    }

    public function tinjau(Request $request, VerifikasiAnak $verifikasi): RedirectResponse
    {
        $data = $request->validate([
            'setuju'  => 'required|boolean',
            'catatan' => 'nullable|string|max:1000',
        ]);

        try {
            $this->svc->tinjau($verifikasi, $request->user(), (bool) $data['setuju'], $data['catatan'] ?? null);
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.verifikasiRt.index', $request->only('rt', 'status', 'page'))
            ->with('success', $data['setuju'] ? 'Usulan disetujui.' : 'Usulan ditolak.');
    }
}
