<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaVerifikasiRtTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_dan_tabel_baru_ada(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'id_rt'));
        $this->assertTrue(Schema::hasColumns('anak', ['verif_rt_status', 'verif_rt_reviu', 'verif_rt_at']));
        $this->assertTrue(Schema::hasColumns('verifikasi_anak', [
            'id_anak', 'id_rt', 'status', 'klaim_id_rt', 'catatan',
            'diusulkan_oleh', 'diusulkan_at', 'reviu', 'ditinjau_oleh', 'ditinjau_at', 'catatan_reviu',
        ]));
    }

    public function test_user_rt_mengenal_rt_dan_berandanya(): void
    {
        $rt   = Rt::factory()->create();
        $user = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);

        $this->assertTrue($user->isRt());
        $this->assertSame($rt->id, $user->rt->id);
        $this->assertSame('rt.verifikasi', $user->berandaRoute());

        $super = User::factory()->create(['type' => 0]);
        $this->assertFalse($super->isRt());
        $this->assertSame('admin.home', $super->berandaRoute());
    }

    public function test_verifikasi_anak_menyimpan_relasi(): void
    {
        $rt   = Rt::factory()->create();
        $user = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id]);
        $anak = Anak::create([
            'nama' => 'Anak A', 'nik' => '3201000000008001', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_rt' => $rt->id,
        ]);

        $v = VerifikasiAnak::create([
            'id_anak' => $anak->id, 'id_rt' => $rt->id, 'status' => 'berdomisili',
            'diusulkan_oleh' => $user->id, 'diusulkan_at' => now(),
        ]);

        $this->assertSame('diusulkan', $v->fresh()->reviu);
        $this->assertSame($anak->id, $v->anak->id);
        $this->assertSame($rt->id, $v->rt->id);
        $this->assertSame($user->id, $v->pengusul->id);
        $this->assertContains('bukan_rt_ini', VerifikasiAnak::STATUS);
    }
}
