<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;

/**
 * Menutup endpoint agregat publik Operasi Timbang saat publikasi dimatikan Dinkes.
 *
 * Gating sengaja dipasang di grup route publik, bukan di method controller:
 * kelima endpoint agregat dipakai bersama oleh dashboard admin, jadi menolak
 * di dalam method akan ikut mematikan dashboard internal.
 */
class TimbangPublikAktif
{
    public function handle(Request $request, Closure $next)
    {
        if (! AppSetting::timbangPublikAktif()) {
            abort(403, 'Ringkasan Operasi Timbang sedang tidak dipublikasikan.');
        }

        return $next($request);
    }
}
