<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use App\Services\VerifikasiRtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviuVerifikasiRtTest extends TestCase
{
    use RefreshDatabase;

    private Rt $rt;
    private User $userRt;
    private VerifikasiRtService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(VerifikasiRtService::class);
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id,
        ], $o));
    }

    public function test_faskes_melihat_antrean_kelurahannya_saja(): void
    {
        $sini = $this->anak('3201000000009201');
        $kelLain = Kelurahan::factory()->create();
        $rtLain  = Rt::factory()->create(['id_kelurahan' => $kelLain->id]);
        $userLain = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rtLain->id, 'id_kel' => $kelLain->id]);
        $sana = $this->anak('3201000000009202', ['id_kel' => $kelLain->id, 'id_rt' => $rtLain->id]);
        $this->svc->usulkan($sini, $this->rt, $this->userRt, 'pindah');
        $this->svc->usulkan($sana, $rtLain, $userLain, 'meninggal');

        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);
        $this->actingAs($faskes)->get(route('admin.verifikasiRt.index'))
            ->assertOk()
            ->assertSee('Anak 3201000000009201')
            ->assertDontSee('Anak 3201000000009202');

        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.verifikasiRt.index'))
            ->assertOk()
            ->assertSee('Anak 3201000000009201')
            ->assertSee('Anak 3201000000009202');
    }

    public function test_setujui_klaim_mengisi_id_rt(): void
    {
        $anak = $this->anak('3201000000009203', ['id_rt' => null]);
        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);

        $this->actingAs($faskes)->from(route('admin.verifikasiRt.index'))
            ->post(route('admin.verifikasiRt.tinjau', $v), ['setuju' => 1, 'catatan' => 'ok'])
            ->assertRedirect(route('admin.verifikasiRt.index'))
            ->assertSessionHas('success');

        $this->assertSame($this->rt->id, (int) $anak->fresh()->id_rt);
        $this->assertSame('disetujui', $v->fresh()->reviu);
    }

    public function test_tolak_menyimpan_catatan(): void
    {
        $anak = $this->anak('3201000000009204');
        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'meninggal');
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->from(route('admin.verifikasiRt.index'))
            ->post(route('admin.verifikasiRt.tinjau', $v), ['setuju' => 0, 'catatan' => 'masih hidup, cek ulang'])
            ->assertRedirect(route('admin.verifikasiRt.index'));

        $this->assertSame('ditolak', $v->fresh()->reviu);
        $this->assertSame('masih hidup, cek ulang', $v->fresh()->catatan_reviu);
        $this->assertSame('ditolak', $anak->fresh()->verif_rt_reviu);
    }

    public function test_faskes_kelurahan_lain_ditolak_403(): void
    {
        $anak = $this->anak('3201000000009205');
        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'pindah');
        $faskesLain = User::factory()->create(['type' => 1, 'id_kel' => Kelurahan::factory()->create()->id]);

        $this->actingAs($faskesLain)->post(route('admin.verifikasiRt.tinjau', $v), ['setuju' => 1])->assertForbidden();
        $this->assertSame('diusulkan', $v->fresh()->reviu);
    }

    public function test_akun_rt_tidak_bisa_membuka_antrean(): void
    {
        $this->actingAs($this->userRt)->get(route('admin.verifikasiRt.index'))->assertForbidden();
    }

    public function test_menu_sidebar_menampilkan_verifikasi_rt_untuk_superadmin(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.home'))->assertOk()->assertSee(route('admin.verifikasiRt.index'));
    }
}
