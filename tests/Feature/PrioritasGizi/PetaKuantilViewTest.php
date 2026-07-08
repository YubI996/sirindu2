<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetaKuantilViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StatusGiziService::useRefs([
            '1_1_24_2' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            '2_1_24_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            '3_1_24_2' => (object) ['m3sd' => 80, 'm2sd' => 83, '3sd' => 97],
            '4_1_90_2' => (object) ['m3sd' => 15, 'm2sd' => 16, '1sd' => 20, '2sd' => 22, '3sd' => 24],
        ]);
    }

    protected function tearDown(): void
    {
        StatusGiziService::flushCache();
        parent::tearDown();
    }

    public function test_view_peta_menerima_agregat_dan_ambang_kuantil(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);
        $kel = Kelurahan::create(['name' => 'Api-Api', 'id_kecamatan' => 1]);
        $anak = Anak::create([
            'nama' => 'Buruk', 'nik' => '3201000000009111', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $kel->id,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $response = $this->actingAs($superAdmin)->get(route('admin.map'));

        $response->assertStatus(200);
        $agg = $response->viewData('petaAgregat');
        $kuantil = $response->viewData('petaKuantil');

        $this->assertArrayHasKey('kelurahan', $agg);
        $this->assertArrayHasKey('Api-Api', $agg['kelurahan']);
        $this->assertArrayHasKey('kelurahan', $kuantil);
        $this->assertArrayHasKey('stunting', $kuantil['kelurahan']);
        $this->assertArrayHasKey('gizi', $kuantil['kelurahan']);
        $this->assertArrayHasKey('bbtn', $kuantil['kelurahan']);
        $this->assertArrayHasKey('prioritas', $kuantil['kelurahan']);
    }
}
