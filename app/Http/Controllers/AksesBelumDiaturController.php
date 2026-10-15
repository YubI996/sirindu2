<?php

namespace App\Http\Controllers;

/** Beranda akun yang aksesnya belum diputuskan (peran sarkes) — spec akun Kesmas §5. */
class AksesBelumDiaturController extends Controller
{
    public function __invoke()
    {
        return view('akses-belum-diatur', ['user' => auth()->user()]);
    }
}
