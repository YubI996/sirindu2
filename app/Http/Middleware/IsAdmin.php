<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // New role-based system: superadmin punya akses admin penuh
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Legacy type-based check (type is tinyInteger: 0=super-admin, 1=admin)
        if (in_array($user->type, [0, 1])) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
