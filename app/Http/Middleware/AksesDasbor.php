<?php

namespace App\Http\Middleware;

use App\Support\AksesKesmas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga rute baca dasbor: `dasbor:kesmas` atau `dasbor:kesmas,imunisasi` (lolos bila salah satu boleh).
 * Admin-setara selalu lolos (perilaku lama grup is_admin); peran Kesmas sesuai AksesKesmas.
 * Spec akun Kesmas §4. Dipasang SETELAH `auth`.
 */
class AksesDasbor
{
    public function handle(Request $request, Closure $next, string ...$dasbor): Response
    {
        $akses = AksesKesmas::untuk($request->user());
        foreach ($dasbor as $nama) {
            if ($akses->bolehDasbor($nama)) {
                return $next($request);
            }
        }

        abort(403, 'Akses tidak diizinkan.');
    }
}
