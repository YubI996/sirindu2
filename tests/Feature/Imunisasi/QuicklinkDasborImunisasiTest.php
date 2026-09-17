<?php

namespace Tests\Feature\Imunisasi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laporan petugas puskesmas (Sep 2026): "dasbor imunisasi kuno". Quicklink beranda
 * "Dashboard Imunisasi" menunjuk ke halaman analitik lama (admin.analytics), sementara
 * dasbor imunisasi hasil redesign (admin.imunisasiDashboard) tidak punya quicklink —
 * user faskes yang mengandalkan kotak beranda selalu mendarat di yang lama.
 */
class QuicklinkDasborImunisasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_quicklink_dashboard_imunisasi_user_faskes_menuju_dasbor_baru(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'imunisasi_faskes', 'id_kel' => 1]);

        $html = $this->actingAs($faskes)->get(route('admin.home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '~href="' . preg_quote(route('admin.imunisasiDashboard'), '~') . '"[^>]*>\s*<i[^>]*></i>Dashboard Imunisasi~',
            $html,
            'Quicklink "Dashboard Imunisasi" harus menuju admin.imunisasiDashboard.'
        );
        $this->assertMatchesRegularExpression(
            '~href="' . preg_quote(route('admin.analytics'), '~') . '"[^>]*>\s*<i[^>]*></i>Dashboard Gizi~',
            $html,
            'Halaman analitik lama tetap tersedia, berlabel "Dashboard Gizi" seperti di sidebar.'
        );
    }

    public function test_preferensi_lama_yang_memilih_quicklink_imunisasi_tetap_mendapat_dasbor_baru(): void
    {
        // Key 'analytics' dipertahankan supaya user yang sudah memilihnya tidak kehilangan kotak itu.
        $faskes = User::factory()->create([
            'type' => 1, 'role' => 'imunisasi_faskes', 'id_kel' => 1,
            'beranda_quicklinks' => ['analytics'],
        ]);

        $html = $this->actingAs($faskes)->get(route('admin.home'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('admin.imunisasiDashboard') . '"', $html);
        $this->assertStringNotContainsString('class="srd-ql" href="' . route('admin.analytics') . '"', $html);
    }
}
