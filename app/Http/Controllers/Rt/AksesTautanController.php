<?php

namespace App\Http\Controllers\Rt;

use App\Http\Controllers\Controller;
use App\Models\RtAksesTautan;
use App\Services\RtAksesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Pintu masuk tautan bertoken per RT (mode B): /rt/akses/{token} → sesi → halaman RT. */
class AksesTautanController extends Controller
{
    public function __construct(private readonly RtAksesService $akses)
    {
    }

    public function masuk(Request $request, string $token): RedirectResponse|Response
    {
        $t = RtAksesTautan::cariToken($token);
        if (!$t) {
            return response()->view('rt.akses-tidak-berlaku', [], 410);
        }

        $t->catatPakai();
        $request->session()->put(RtAksesService::SESI_TAUTAN, $t->id);

        return redirect()->route('rt.verifikasi');
    }

    public function keluar(Request $request): RedirectResponse
    {
        $this->akses->keluarTautan($request);

        return redirect()->route('landing');
    }
}
