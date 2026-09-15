<?php

namespace App\Http\Middleware;

use App\Services\RtAksesService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga grup /rt: lolos bila ada sesi tautan RT yang masih berlaku (tanpa akun),
 * atau user yang masuk berperan rt / superadmin. Menggantikan `auth` + `module.role:rt`.
 */
class RtAkses
{
    public function __construct(private readonly RtAksesService $akses)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->akses->tautanAktif($request)) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->guest(route('login'));
        }
        if ($user->isRt() || $user->isSuperAdmin()) {
            return $next($request);
        }

        abort(403, 'Halaman ini hanya untuk peran RT.');
    }
}
