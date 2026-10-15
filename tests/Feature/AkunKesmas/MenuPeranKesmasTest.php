<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kelurahan;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPeranKesmasTest extends TestCase
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

    /** Hanya isi sidebar (.left-side-bar sampai .main-container) — header/konten tak ikut diperiksa. */
    private function menu(User $user): string
    {
        $html = $this->actingAs($user)->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<div class="left-side-bar">(.*?)<div class="main-container/s', $html, $m), 'sidebar tak ditemukan');

        return $m[1];
    }

    public function test_pimpinan_hanya_empat_dasbor(): void
    {
        $menu = $this->menu(User::factory()->create(['type' => 2, 'role' => 'pimpinan']));

        foreach (['admin.kesmas.dashboard', 'admin.imunisasiDashboard', 'admin.timbang.dashboard', 'admin.spm.dashboard'] as $r) {
            $this->assertStringContainsString(route($r), $menu, $r);
        }
        foreach (['admin.anak', 'admin.importCsv.index', 'admin.export.kesmas.index', 'super.admin.user', 'admin.home'] as $r) {
            $this->assertStringNotContainsString(route($r), $menu, $r);
        }
    }

    public function test_kelurahan_kesmas_dan_export(): void
    {
        $kel = Kelurahan::factory()->create();
        $menu = $this->menu(User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $kel->id]));

        $this->assertStringContainsString(route('admin.kesmas.dashboard'), $menu);
        $this->assertStringContainsString(route('admin.export.kesmas.index'), $menu);
        $this->assertStringNotContainsString(route('admin.imunisasiDashboard'), $menu);
        $this->assertStringNotContainsString(route('admin.anak'), $menu);
    }

    public function test_menu_admin_tidak_berubah(): void
    {
        $menu = $this->menu(User::factory()->create(['type' => 0, 'role' => 'superadmin']));
        $this->assertStringContainsString(route('admin.anak'), $menu);
        $this->assertStringContainsString(route('super.admin.user'), $menu);
    }
}
