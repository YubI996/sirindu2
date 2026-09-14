<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use App\Services\VerifikasiRtService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifikasiRtServiceTest extends TestCase
{
    use RefreshDatabase;

    private VerifikasiRtService $svc;
    private Rt $rt;
    private Rt $rtLain;
    private User $userRt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(VerifikasiRtService::class);
        $this->rt     = Rt::factory()->create();
        $this->rtLain = Rt::factory()->create(['id_kelurahan' => $this->rt->id_kelurahan]);
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

    public function test_cakupan_warga_dan_tanpa_rt(): void
    {
        $warga   = $this->anak('3201000000009001');
        $tanpaRt = $this->anak('3201000000009002', ['id_rt' => null]);
        $rtLain  = $this->anak('3201000000009003', ['id_rt' => $this->rtLain->id]);
        $kelLain = $this->anak('3201000000009004', ['id_rt' => null, 'id_kel' => Kelurahan::factory()->create()->id]);

        $this->assertSame([$warga->id], $this->svc->wargaQuery($this->rt)->pluck('id')->all());
        $this->assertSame([$tanpaRt->id], $this->svc->tanpaRtQuery($this->rt)->pluck('id')->all());
        $this->assertTrue($this->svc->dalamCakupan($warga, $this->rt));
        $this->assertTrue($this->svc->dalamCakupan($tanpaRt, $this->rt));
        $this->assertFalse($this->svc->dalamCakupan($rtLain, $this->rt));
        $this->assertFalse($this->svc->dalamCakupan($kelLain, $this->rt));
    }

    public function test_usulan_berdomisili_menandai_anak_dan_tidak_menyentuh_updated_at(): void
    {
        $anak = $this->anak('3201000000009005');
        $updatedAtAwal = $anak->fresh()->updated_at;
        $this->travel(1)->hours();

        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili', 'sudah ditemui');

        $anak->refresh();
        $this->assertSame('berdomisili', $v->status);
        $this->assertSame('diusulkan', $v->reviu);
        $this->assertNull($v->klaim_id_rt);
        $this->assertSame('berdomisili', $anak->verif_rt_status);
        $this->assertSame('diusulkan', $anak->verif_rt_reviu);
        $this->assertNotNull($anak->verif_rt_at);
        $this->assertEquals($updatedAtAwal, $anak->updated_at, 'verif_rt_* tidak boleh menyentuh updated_at');
    }

    public function test_usulan_di_luar_cakupan_ditolak(): void
    {
        $anak = $this->anak('3201000000009006', ['id_rt' => $this->rtLain->id]);

        $this->expectException(AuthorizationException::class);
        $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');
    }

    public function test_klaim_anak_tanpa_rt_mengisi_klaim_id_rt(): void
    {
        $anak = $this->anak('3201000000009007', ['id_rt' => null]);

        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');

        $this->assertSame($this->rt->id, (int) $v->klaim_id_rt);
        $this->assertNull($anak->fresh()->id_rt, 'id_rt baru diisi setelah disetujui');
    }

    public function test_bukan_rt_ini_menyembunyikan_dari_tanpa_rt_tanpa_menyentuh_tag(): void
    {
        $anak = $this->anak('3201000000009008', ['id_rt' => null]);

        $this->svc->usulkan($anak, $this->rt, $this->userRt, 'bukan_rt_ini');

        $this->assertSame([], $this->svc->tanpaRtQuery($this->rt)->pluck('id')->all());
        $this->assertSame([$anak->id], $this->svc->tanpaRtQuery($this->rtLain)->pluck('id')->all(), 'RT lain masih melihatnya');
        $this->assertNull($anak->fresh()->verif_rt_status);
    }

    public function test_bukan_rt_ini_untuk_warga_sendiri_ditolak(): void
    {
        $anak = $this->anak('3201000000009009');

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->usulkan($anak, $this->rt, $this->userRt, 'bukan_rt_ini');
    }

    public function test_usulan_baru_menggantikan_usulan_lama_yang_belum_ditinjau(): void
    {
        $anak = $this->anak('3201000000009010');
        $lama = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'pindah');
        $baru = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');

        $this->assertSame('ditolak', $lama->fresh()->reviu);
        $this->assertSame('Digantikan usulan baru', $lama->fresh()->catatan_reviu);
        $this->assertSame('berdomisili', $anak->fresh()->verif_rt_status);
        $this->assertSame(1, VerifikasiAnak::where('id_anak', $anak->id)->where('reviu', 'diusulkan')->count());
        $this->assertSame($baru->id, VerifikasiAnak::where('id_anak', $anak->id)->where('reviu', 'diusulkan')->value('id'));
    }

    public function test_setuju_klaim_mengisi_id_rt_dan_posyandu_hanya_bila_kosong(): void
    {
        $super  = User::factory()->create(['type' => 0]);
        $posLama = Posyandu::factory()->create();
        $a = $this->anak('3201000000009011', ['id_rt' => null, 'id_posyandu' => null]);
        $b = $this->anak('3201000000009012', ['id_rt' => null, 'id_posyandu' => $posLama->id]);
        $va = $this->svc->usulkan($a, $this->rt, $this->userRt, 'berdomisili');
        $vb = $this->svc->usulkan($b, $this->rt, $this->userRt, 'berdomisili');

        $this->svc->tinjau($va, $super, true, 'ok');
        $this->svc->tinjau($vb, $super, true);

        $this->assertSame($this->rt->id, (int) $a->fresh()->id_rt);
        $this->assertSame($this->rt->id_posyandu, (int) $a->fresh()->id_posyandu);
        $this->assertSame($this->rt->id, (int) $b->fresh()->id_rt);
        $this->assertSame($posLama->id, (int) $b->fresh()->id_posyandu, 'posyandu terisi tidak ditimpa');
        $this->assertSame('disetujui', $a->fresh()->verif_rt_reviu);
        $this->assertSame('disetujui', $va->fresh()->reviu);
        $this->assertSame($super->id, (int) $va->fresh()->ditinjau_oleh);
    }

    public function test_tolak_menandai_ditolak_tanpa_mengubah_id_rt(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak  = $this->anak('3201000000009013', ['id_rt' => null]);
        $v     = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');

        $this->svc->tinjau($v, $super, false, 'bukan warga');

        $this->assertNull($anak->fresh()->id_rt);
        $this->assertSame('ditolak', $anak->fresh()->verif_rt_reviu);
        $this->assertSame('bukan warga', $v->fresh()->catatan_reviu);
    }

    public function test_faskes_hanya_meninjau_kelurahannya(): void
    {
        $kelLain = Kelurahan::factory()->create();
        $faskesLain = User::factory()->create(['type' => 1, 'id_kel' => $kelLain->id]);
        $faskesSini = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);
        $anak = $this->anak('3201000000009014');
        $v = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'meninggal');

        $this->assertSame([], $this->svc->antreanQuery($faskesLain)->pluck('id')->all());
        $this->assertSame([$v->id], $this->svc->antreanQuery($faskesSini)->pluck('id')->all());

        try {
            $this->svc->tinjau($v, $faskesLain, true);
            $this->fail('harus ditolak');
        } catch (AuthorizationException) {
        }

        $this->svc->tinjau($v, $faskesSini, true);
        $this->assertSame('disetujui', $v->fresh()->reviu);
    }

    public function test_usulan_yang_sudah_ditinjau_tidak_bisa_ditinjau_lagi(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak  = $this->anak('3201000000009015');
        $v     = $this->svc->usulkan($anak, $this->rt, $this->userRt, 'pindah');
        $this->svc->tinjau($v, $super, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->tinjau($v->fresh(), $super, false);
    }

    public function test_progres_menghitung_warga_yang_sudah_diverifikasi(): void
    {
        $a = $this->anak('3201000000009016');
        $this->anak('3201000000009017');
        $this->anak('3201000000009018', ['id_rt' => null]); // bukan warga → tidak dihitung
        $this->svc->usulkan($a, $this->rt, $this->userRt, 'berdomisili');

        $this->assertSame(['total' => 2, 'diverifikasi' => 1], $this->svc->progres($this->rt));
    }
}
