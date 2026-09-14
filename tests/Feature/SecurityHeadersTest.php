<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Mengunci header keamanan global (App\Http\Middleware\SecurityHeaders), termasuk
 * Content-Security-Policy yang ditambahkan saat hardening 2026-08-10.
 *
 * Direktif allowlist (cdn.jsdelivr.net, unpkg.com) sengaja diuji agar tak ada yang
 * diam-diam menghapusnya — tanpa origin itu, peta Leaflet & Chart.js akan mati.
 */
class SecurityHeadersTest extends TestCase
{
    use DatabaseTransactions;

    public function test_response_membawa_header_keamanan_inti(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_csp_hadir_dengan_direktif_hardening(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'CSP header wajib ada.');

        // Lockdown inti — nilai hardening walau script/style pakai unsafe-inline.
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString('upgrade-insecure-requests', $csp);

        // Origin eksternal yang dipakai fitur inti tak boleh hilang.
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp); // Chart.js/SweetAlert
        $this->assertStringContainsString('https://unpkg.com', $csp);        // Leaflet
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp); // Google Fonts
        $this->assertStringContainsString('https://www.google.com', $csp);   // reCAPTCHA
    }

    /**
     * Dev lokal lewat http://sirindu.test: `upgrade-insecure-requests` membuat Chrome
     * memaksa POST login ke https://sirindu.test (sertifikat self-signed Laragon) →
     * net::ERR_CERT_AUTHORITY_INVALID. Di env local + HTTP polos, dua header itu tak dikirim.
     * Env lain (production/testing) tetap mengirimnya — dikunci test di atas.
     */
    public function test_env_local_lewat_http_polos_tidak_memaksa_https(): void
    {
        app()->detectEnvironment(fn () => 'local');

        $http = $this->get('http://sirindu.test/');
        $http->assertOk();
        $http->assertHeaderMissing('Strict-Transport-Security');
        $this->assertStringNotContainsString('upgrade-insecure-requests', $http->headers->get('Content-Security-Policy'));

        $https = $this->get('https://sirindu.test/');
        $https->assertOk();
        $https->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->assertStringContainsString('upgrade-insecure-requests', $https->headers->get('Content-Security-Policy'));
    }
}
