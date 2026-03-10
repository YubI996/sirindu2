<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * CheckModuleRole Middleware
 *
 * Mengecek apakah user memiliki salah satu role yang diperlukan.
 *
 * Penggunaan di route:
 *   ->middleware('module.role:surveilans_superadmin')
 *   ->middleware('module.role:surveilans_superadmin,surveilans_puskesmas,surveilans_rs')
 */
class CheckModuleRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): mixed  $next
     * @param  string  ...$roles  Role(s) yang diizinkan (OR logic)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // superadmin sistem selalu punya akses
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Cek apakah role user ada di daftar role yang diizinkan
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // Jika request AJAX, kembalikan JSON
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk mengakses halaman ini.',
                'role_required' => $roles,
                'your_role' => $user->role,
            ], 403);
        }

        abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}
