<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec akun Kesmas §2: Pimpinan melihat daftar nama, tetapi tanpa export dan tanpa menulis. */
class PimpinanBacaSajaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
    }

    private function pimpinan(): User
    {
        return User::factory()->create(['type' => 2, 'role' => 'pimpinan']);
    }

    public function test_dasbor_imunisasi_tanpa_export_dan_tautan_admin(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('admin.imunisasiDashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-kelompok="bbl"', $html, 'kartu tetap membuka daftar nama');
        $this->assertStringNotContainsString('id="sasaran-export"', $html);
        $this->assertStringNotContainsString(route('admin.imunisasiDashboard.sasaranExport'), $html);
        $this->assertStringNotContainsString(route('admin.earlyWarning'), $html);
    }

    public function test_dasbor_ot_tanpa_export_peta_dan_pj_bisa_diubah(): void
    {
        $html = $this->actingAs($this->pimpinan())->get(route('admin.timbang.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="daftar-export"', $html);
        // URL export & PJ tak ikut dicetak ke JS untuk akun baca-saja (sama dengan dasbor Imunisasi).
        $this->assertStringNotContainsString(route('admin.timbang.daftar.export'), $html);
        $this->assertStringNotContainsString(route('admin.timbang.pj', ['anak' => '__ID__']), $html);
        $this->assertStringContainsString("var API_DAFTAR_EXPORT = '';", $html);
        $this->assertStringNotContainsString('id="btn-peta"', $html);
        $this->assertStringContainsString('var BOLEH_UBAH = false;', $html);
        $this->assertStringNotContainsString('id="tb-publikasi"', $html);
    }

    public function test_dasbor_ot_pimpinan_memakai_filter_se_kota(): void
    {
        // Server memperlakukan Pimpinan se-kota; UI-nya wajib ikut: pilih kecamatan/kelurahan bebas,
        // bukan mode per-kelurahan (input tersembunyi tanpa kelurahan).
        $html = $this->actingAs($this->pimpinan())->get(route('admin.timbang.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<select id="f-kec">', $html);
        $this->assertStringContainsString('<select id="f-kel">', $html);
        $this->assertStringNotContainsString('<input type="hidden" id="f-kec"', $html);
        $this->assertStringContainsString('var FILTER_SE_KOTA = true;', $html);
        $this->assertStringNotContainsString('id="tb-publikasi"', $html, 'publikasi tetap khusus superadmin');
    }

    public function test_admin_tetap_punya_tombolnya(): void
    {
        $admin = User::factory()->create(['type' => 1, 'role' => null]);
        $this->actingAs($admin)->get(route('admin.imunisasiDashboard'))->assertOk()->assertSee('id="sasaran-export"', false);
        $this->actingAs($admin)->get(route('admin.timbang.dashboard'))->assertOk()
            ->assertSee('id="daftar-export"', false)->assertSee('var BOLEH_UBAH = true;', false)
            ->assertSee(route('admin.timbang.daftar.export'), false);
    }
}
