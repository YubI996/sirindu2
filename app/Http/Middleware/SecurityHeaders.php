<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Content-Security-Policy per direktif.
     *
     * CATATAN 'unsafe-inline': aplikasi ini (Blade + AdminLTE/Bootstrap) memakai
     * banyak <script> inline dan handler inline (mis. onclick="deleteCase(...)")
     * serta atribut style inline. CSP berbasis nonce akan mematikan semuanya, jadi
     * script/style-src sengaja mengizinkan 'unsafe-inline'. Nilai hardening tetap
     * nyata: object-src 'none', base-uri/form-action/frame-ancestors 'self', dan
     * allowlist origin eksternal (script/style/img hanya dari host tepercaya —
     * skrip dari origin penyerang tetap diblokir), plus upgrade-insecure-requests.
     *
     * Origin eksternal yang benar-benar dipakai (hasil audit view):
     *   - cdn.jsdelivr.net      : Chart.js, Bootstrap, SweetAlert, dll.
     *   - unpkg.com             : Leaflet (js/css + marker images)
     *   - fonts.googleapis.com / fonts.gstatic.com : Google Fonts
     *   - *.tile.openstreetmap.org / server.arcgisonline.com / *.basemaps.cartocdn.com : tile peta
     *   - www.google.com / www.gstatic.com : reCAPTCHA v3 (halaman login)
     */
    private const CSP = [
        'default-src'               => "'self'",
        'base-uri'                  => "'self'",
        'object-src'                => "'none'",
        'frame-ancestors'           => "'self'",
        'form-action'               => "'self'",
        'script-src'                => "'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://www.google.com https://www.gstatic.com",
        'style-src'                 => "'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com",
        'font-src'                  => "'self' data: https://fonts.gstatic.com",
        'img-src'                   => "'self' data: blob: https://*.tile.openstreetmap.org https://server.arcgisonline.com https://*.basemaps.cartocdn.com https://unpkg.com https://cdn.jsdelivr.net",
        'connect-src'               => "'self' https://www.google.com https://www.gstatic.com",
        'frame-src'                 => "'self' https://www.google.com",
        'upgrade-insecure-requests' => '',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Content-Security-Policy', $this->buildCsp());
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    /** Rangkai direktif CSP jadi satu string header. */
    private function buildCsp(): string
    {
        return collect(self::CSP)
            ->map(fn ($value, $directive) => trim("{$directive} {$value}"))
            ->implode('; ');
    }
}
