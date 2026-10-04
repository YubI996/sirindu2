<?php

namespace Tests\Feature\Imunisasi;

use App\Models\JenisVaksin;
use App\Models\KelompokVaksin;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Insiden prod 4 Okt 2026: tabel kelompok_vaksin kosong (KelompokVaksinSeeder tak pernah
 * dipanggil), sehingga isKelompokLengkap() selalu false dan capaian IDL tampil 0 tanpa
 * error. Dasbor kini harus berteriak, bukan diam.
 */
class KelompokVaksinKosongTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        $this->seed(JenisVaksinSeeder::class);
    }

    private function service(): ImunisasiStatusService
    {
        return app(ImunisasiStatusService::class);
    }

    public function test_grup_yang_tak_ada_dilaporkan_kosong(): void
    {
        KelompokVaksin::query()->delete();

        $this->assertSame(['IDL', 'IBL'], $this->service()->getKelompokKosong());
    }

    public function test_grup_ada_tapi_tanpa_vaksin_dilaporkan_kosong(): void
    {
        // Seeder grup jalan, lalu tautan vaksinnya hilang (id_kelompok_vaksin NULL semua).
        $this->seed(KelompokVaksinSeeder::class);
        JenisVaksin::query()->update(['id_kelompok_vaksin' => null]);
        ImunisasiStatusService::flushCache();

        $this->assertSame(['IDL', 'IBL'], $this->service()->getKelompokKosong());
    }

    public function test_hanya_grup_bermasalah_yang_dilaporkan(): void
    {
        $this->seed(KelompokVaksinSeeder::class);
        $ibl = KelompokVaksin::where('kode', 'IBL')->first();
        JenisVaksin::where('id_kelompok_vaksin', $ibl->id)->update(['id_kelompok_vaksin' => null]);
        ImunisasiStatusService::flushCache();

        $this->assertSame(['IBL'], $this->service()->getKelompokKosong());
    }

    public function test_master_lengkap_tidak_dilaporkan(): void
    {
        $this->seed(KelompokVaksinSeeder::class);

        $this->assertSame([], $this->service()->getKelompokKosong());
    }

    public function test_dasbor_imunisasi_menampilkan_peringatan_saat_grup_kosong(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->get(route('admin.imunisasiDashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('kelompokKosong', ['IDL', 'IBL']);
        $response->assertSee('Grup vaksin belum diisi');
        $response->assertSee('KelompokVaksinSeeder');
    }

    public function test_dasbor_kesmas_menampilkan_peringatan_saat_grup_kosong(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->get(route('admin.kesmas.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('kelompokKosong', ['IDL', 'IBL']);
        $response->assertSee('Grup vaksin belum diisi');
    }

    public function test_dasbor_kesmas_tanpa_peringatan_saat_master_lengkap(): void
    {
        $this->seed(KelompokVaksinSeeder::class);
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->get(route('admin.kesmas.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('kelompokKosong', []);
        $response->assertDontSee('Grup vaksin belum diisi');
    }

    public function test_dasbor_imunisasi_tanpa_peringatan_saat_master_lengkap(): void
    {
        $this->seed(KelompokVaksinSeeder::class);
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->get(route('admin.imunisasiDashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('kelompokKosong', []);
        $response->assertDontSee('Grup vaksin belum diisi');
    }
}
