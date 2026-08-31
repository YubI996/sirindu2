<?php

namespace Tests\Feature\Imunisasi;

use App\Models\Anak;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImunisasiDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
        $this->admin = User::factory()->create(['type' => 1]);
    }

    public function test_dashboard_menyediakan_data_ringkasan_rutin_baru_ke_view(): void
    {
        Anak::create([
            'nama' => 'Anak Dashboard', 'nik' => '3201000000000099', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(5)->toDateString(), 'status' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.imunisasiDashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('sasaran');
        $response->assertViewHas('iblCoverage');
        $response->assertViewHas('funnel');
        $response->assertViewHas('cakupanAntigen');
        $response->assertViewHas('kohortWilayah');
        $response->assertViewHas('rincianPuskesmas');
        $response->assertViewHas('sasaranHarian');
    }
}
