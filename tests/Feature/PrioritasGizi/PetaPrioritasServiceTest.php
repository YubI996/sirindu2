<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kelurahan;
use App\Services\PetaPrioritasService;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetaPrioritasServiceTest extends TestCase
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

    public function test_agregat_kelurahan_menghitung_gizi_buruk_dan_prevalensi(): void
    {
        $kel = Kelurahan::create(['name' => 'Api-Api', 'id_kecamatan' => 1]);

        // Anak gizi buruk (bb=12/tb=90/bln=24 → BB/TB severely_wasted).
        $a1 = Anak::create([
            'nama' => 'Buruk', 'nik' => '3201000000009101', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $kel->id,
        ]);
        DataAnak::create(['id_anak' => $a1->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        // Anak normal (bb=18/tb=90 → BB/TB normal, TB/U normal).
        $a2 = Anak::create([
            'nama' => 'Normal', 'nik' => '3201000000009102', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $kel->id,
        ]);
        DataAnak::create(['id_anak' => $a2->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 18, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $agg = app(PetaPrioritasService::class)->agregat('kelurahan');

        $this->assertArrayHasKey('Api-Api', $agg);
        $this->assertSame(2, $agg['Api-Api']['total']);
        $this->assertSame(1, $agg['Api-Api']['gizi_buruk']);
        $this->assertSame(1, $agg['Api-Api']['anak_prioritas']);
        $this->assertSame(50.0, $agg['Api-Api']['gizi_kurang_buruk_pct']);
    }

    public function test_agregat_mengabaikan_anak_tanpa_pengukuran(): void
    {
        $kel = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        // Anak tanpa DataAnak → snapshot usia_bln null → tidak dihitung sebagai terukur.
        Anak::create([
            'nama' => 'Tanpa Ukur', 'nik' => '3201000000009103', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $kel->id,
        ]);

        $agg = app(PetaPrioritasService::class)->agregat('kelurahan');

        $this->assertArrayNotHasKey('Kanaan', $agg);
    }
}
