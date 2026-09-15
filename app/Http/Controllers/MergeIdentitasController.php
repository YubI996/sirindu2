<?php

namespace App\Http\Controllers;

use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Services\IdentitasMergeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Penggabungan baris anak oleh Dinkes (spec verifikasi RT §6.3) — hanya superadmin. */
class MergeIdentitasController extends Controller
{
    public function __construct(private readonly IdentitasMergeService $svc)
    {
        $this->middleware(function ($request, $next) {
            abort_if(!$request->user()?->isSuperAdmin(), 403);
            return $next($request);
        });
    }

    public function index(): View
    {
        return view('admin.verifikasi-rt.gabung.index', [
            'antrean' => AnakTautan::with(['anakA', 'anakB', 'pengusul.rt', 'rt'])
                ->where('keputusan', 'sama')->where('status', 'disetujui')->orderBy('ditinjau_at')->get(),
            'log'     => AnakMergeLog::with(['dipertahankan', 'pelaku', 'pembatal'])->latest('id')->limit(50)->get(),
        ]);
    }

    public function show(AnakTautan $tautan): View|RedirectResponse
    {
        try {
            $p = $this->svc->pratinjau($tautan);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.gabung.index')->with('error', $e->getMessage());
        }

        return view('admin.verifikasi-rt.gabung.show', $p + ['kolom' => IdentitasMergeService::KOLOM]);
    }

    public function store(Request $request, AnakTautan $tautan): RedirectResponse
    {
        $data = $request->validate([
            'pilihan'       => 'nullable|array',
            'pilihan.*'     => 'in:a,b',
            'dipertahankan' => 'nullable|in:a,b',
        ]);

        try {
            $log = $this->svc->gabung($tautan, $request->user(), $data['pilihan'] ?? [], $data['dipertahankan'] ?? null);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.gabung.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gabung.index')
            ->with('success', "Digabung ke anak #{$log->id_dipertahankan} (log #{$log->id}). Bisa dibatalkan dari riwayat.");
    }

    public function batalkan(Request $request, AnakMergeLog $log): RedirectResponse
    {
        try {
            $anak = $this->svc->batalkan($log, $request->user());
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.gabung.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gabung.index')->with('success', "Penggabungan dibatalkan; anak #{$anak->id} dipulihkan.");
    }
}
