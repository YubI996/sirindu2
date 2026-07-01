<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Operasi Timbang khusus balita (<60 bln); status wasting ("Gizi
 * Kurang"/"Gizi Buruk") harus mengikuti indikator BB/TB, bukan IMT/U.
 *
 * Referensi z_score disuntik via StatusGiziService::useRefs() agar batas SD
 * deterministik tanpa menyeed tabel z_score.
 */
class TimbangGiziBbTbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Referensi jk=1, umur 24 bln (var=2), tinggi 90 (BB/TB var=2).
        // Sengaja dibuat agar BB/TB dan IMT/U BERBEDA untuk bb=12, tb=90:
        //   - IMT/U bmi=10000*12/8100=14.81 ∈ [m2sd 13, 1sd 17] → normal
        //   - BB/TB bb=12 < m3sd 15 → severely_wasted
        // Sehingga endpoint hanya menghitung gizi_buruk bila memakai BB/TB.
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

    public function test_gizi_buruk_balita_dihitung_dari_bb_tb_bukan_imt_u(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);

        $anak = Anak::create([
            'nama' => 'Balita Wasted', 'nik' => '3201000000009001', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
        ]);

        $data = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.gizi'))
            ->assertStatus(200)
            ->json();

        // Status wasting kini bersumber dari BB/TB.
        $this->assertArrayHasKey('bb_tb', $data);
        $this->assertArrayNotHasKey('imt_u', $data);

        // bb=12 → BB/TB severely_wasted → gizi_buruk; IMT/U normal (tak terhitung).
        $this->assertSame(1, $data['gizi_buruk']);
        $this->assertSame(0, $data['gizi_kurang']);
        $this->assertSame(1, $data['bb_tb']['buruk']);
        $this->assertSame(0, $data['bb_tb']['normal']);
    }
}
