<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UserAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $userType)
    {
        $user = auth()->user();

        // New role-based system: superadmin punya akses penuh
        if ($user->isSuperAdmin() && $userType === 'super-admin') {
            return $next($request);
        }

        // Map string userType to tinyInteger values (PHP 8 strict comparison)
        $typeMap = ['super-admin' => 0, 'admin' => 1, 'user' => 2];
        $expectedType = $typeMap[$userType] ?? null;

        if ($expectedType !== null && $user->type == $expectedType) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
