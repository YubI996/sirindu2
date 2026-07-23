<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Toggle publikasi ringkasan Operasi Timbang di landing publik.
 *
 * Yang dikunci di sini: default tetap tampil, hanya Dinkes (superadmin) yang bisa
 * mengubah, dan saat dimatikan bukan cuma tampilannya yang hilang — endpoint agregat
 * publik ikut ditolak, sementara dashboard admin tetap hidup.
 */
class TimbangPublikasiTest extends TestCase
{
    use DatabaseTransactions;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('app_setting:' . AppSetting::KEY_TIMBANG_PUBLIK);

        // type=0 => superadmin (Dinkes)
        $this->superadmin = User::factory()->create(['type' => 0]);
    }

    protected function tearDown(): void
    {
        Cache::forget('app_setting:' . AppSetting::KEY_TIMBANG_PUBLIK);

        parent::tearDown();
    }

    private function matikanPublikasi(): void
    {
        AppSetting::setBool(AppSetting::KEY_TIMBANG_PUBLIK, false);
    }

    public function test_landing_menampilkan_ringkasan_secara_default()
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('id="data"', false);
    }

    public function test_landing_menyembunyikan_ringkasan_saat_publikasi_dimatikan()
    {
        $this->matikanPublikasi();

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertDontSee('id="data"', false);
        $response->assertDontSee('Lihat ringkasan data');
        // Wajah aplikasi tetap ada.
        $response->assertSee('SIRINDU', false);
    }

    public function test_api_publik_terbuka_secara_default()
    {
        $this->get(route('public.timbang.ringkasan'))->assertOk();
    }

    public function test_api_publik_ditolak_saat_publikasi_dimatikan()
    {
        $this->matikanPublikasi();

        $this->get(route('public.timbang.ringkasan'))->assertForbidden();
        $this->get(route('public.timbang.gizi'))->assertForbidden();
        $this->get(route('public.timbang.tren'))->assertForbidden();
        $this->get(route('public.timbang.coverage'))->assertForbidden();
        $this->get(route('public.timbang.program'))->assertForbidden();
    }

    /** Gating hanya menyentuh route publik — dashboard internal tidak boleh ikut mati. */
    public function test_api_admin_tetap_hidup_saat_publikasi_dimatikan()
    {
        $this->matikanPublikasi();

        $this->actingAs($this->superadmin)
            ->get(route('admin.timbang.ringkasan'))
            ->assertOk();

        $this->actingAs($this->superadmin)
            ->get(route('admin.timbang.dashboard'))
            ->assertOk();
    }

    public function test_superadmin_dapat_mematikan_dan_menyalakan_publikasi()
    {
        $this->actingAs($this->superadmin)
            ->post(route('admin.timbang.publikasi'), ['aktif' => 0])
            ->assertOk()
            ->assertJson(['success' => true, 'aktif' => false]);

        $this->assertFalse(AppSetting::timbangPublikAktif());

        $this->actingAs($this->superadmin)
            ->post(route('admin.timbang.publikasi'), ['aktif' => 1])
            ->assertOk()
            ->assertJson(['success' => true, 'aktif' => true]);

        $this->assertTrue(AppSetting::timbangPublikAktif());
    }

    public function test_non_superadmin_tidak_dapat_mengubah_publikasi()
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);

        $this->actingAs($faskes)
            ->post(route('admin.timbang.publikasi'), ['aktif' => 0])
            ->assertForbidden();

        $this->assertTrue(AppSetting::timbangPublikAktif());
    }

    public function test_tamu_tidak_dapat_mengubah_publikasi()
    {
        $this->post(route('admin.timbang.publikasi'), ['aktif' => 0])
            ->assertRedirect(route('login'));

        $this->assertTrue(AppSetting::timbangPublikAktif());
    }

    /** Cache tidak boleh menyajikan nilai basi setelah toggle diubah. */
    public function test_perubahan_langsung_terlihat_tanpa_menunggu_cache()
    {
        $this->assertTrue(AppSetting::timbangPublikAktif());

        AppSetting::setBool(AppSetting::KEY_TIMBANG_PUBLIK, false);
        $this->assertFalse(AppSetting::timbangPublikAktif());

        AppSetting::setBool(AppSetting::KEY_TIMBANG_PUBLIK, true);
        $this->assertTrue(AppSetting::timbangPublikAktif());
    }
}
