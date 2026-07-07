<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\PrioritasGizi;
use App\Services\PrioritasGiziService;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrioritasGiziRefreshTest extends TestCase
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

    public function test_refresh_anak_menulis_baris_snapshot_dengan_wilayah(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita Buruk', 'nik' => '3201000000009005', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
            'id_kel' => 7, 'id_rt' => 3,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        app(PrioritasGiziService::class)->refreshAnak($anak->id);

        $this->assertDatabaseHas('prioritas_gizi', [
            'id_anak' => $anak->id, 'prioritas' => 1, 'gizi_buruk' => 1, 'id_kel' => 7, 'id_rt' => 3,
        ]);
    }

    public function test_refresh_anak_idempoten_memakai_upsert(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita', 'nik' => '3201000000009006', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $svc = app(PrioritasGiziService::class);
        $svc->refreshAnak($anak->id);
        $svc->refreshAnak($anak->id);

        $this->assertSame(1, PrioritasGizi::where('id_anak', $anak->id)->count());
    }

    public function test_refresh_all_menulis_semua_anak(): void
    {
        foreach (['3201000000009007', '3201000000009008'] as $i => $nik) {
            $anak = Anak::create([
                'nama' => "Anak {$i}", 'nik' => $nik, 'jk' => 1,
                'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
            ]);
            DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
                'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);
        }

        $ditulis = app(PrioritasGiziService::class)->refreshAll();

        $this->assertSame(2, $ditulis);
        $this->assertSame(2, PrioritasGizi::count());
    }
}
