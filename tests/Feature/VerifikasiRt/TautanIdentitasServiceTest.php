<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use App\Services\TautanIdentitasService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TautanIdentitasServiceTest extends TestCase
{
    use RefreshDatabase;

    private TautanIdentitasService $svc;
    private Rt $rt;
    private User $userRt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(TautanIdentitasService::class);
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id,
        ], $o));
    }

    private function kandidat(Anak $x, Anak $y): AnakKandidat
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        return AnakKandidat::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'skor' => 190, 'via' => 'ortu',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);
    }

    public function test_kandidat_untuk_rt_minimal_satu_anggota_dalam_cakupan(): void
    {
        $warga  = $this->anak('3201000000013001');
        $luar1  = $this->anak('3201000000013002', ['id_rt' => null, 'id_kel' => Kelurahan::factory()->create()->id]);
        $luar2  = $this->anak('3201000000013003', ['id_rt' => null, 'id_kel' => Kelurahan::factory()->create()->id]);
        $tanpa  = $this->anak('3201000000013004', ['id_rt' => null]);
        $k1 = $this->kandidat($warga, $luar1);   // warga × luar → tampil
        $k2 = $this->kandidat($luar1, $luar2);   // luar × luar → tidak
        $k3 = $this->kandidat($tanpa, $luar2);   // tanpa-RT sekelurahan × luar → tampil

        $ids = $this->svc->kandidatUntukRt($this->rt)->pluck('id')->sort()->values()->all();
        $this->assertSame([$k1->id, $k3->id], $ids);
    }

    public function test_kandidat_yang_sudah_diputus_tidak_tampil_kecuali_ditolak(): void
    {
        $a = $this->anak('3201000000013005');
        $b = $this->anak('3201000000013006', ['id_rt' => null]);
        $this->kandidat($a, $b);

        $t = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'beda');
        $this->assertCount(0, $this->svc->kandidatUntukRt($this->rt));

        $super = User::factory()->create(['type' => 0]);
        $this->svc->tinjauTautan($t, $super, false, 'cek lagi');
        $this->assertCount(1, $this->svc->kandidatUntukRt($this->rt), 'ditolak peninjau → muncul lagi');
    }

    public function test_putuskan_menormalkan_urutan_dan_menyalin_skor(): void
    {
        $a = $this->anak('3201000000013007');
        $b = $this->anak('3201000000013008', ['id_rt' => null]);
        $this->kandidat($a, $b);

        $t = $this->svc->putuskan($b->id, $a->id, $this->rt, $this->userRt, 'sama', 'anak yang sama, NIK lama salah');

        $this->assertSame([$a->id, $b->id], [(int) $t->id_anak_a, (int) $t->id_anak_b]);
        $this->assertSame('sama', $t->keputusan);
        $this->assertSame('diusulkan', $t->status);
        $this->assertSame('ortu', $t->via);
        $this->assertEquals(190, $t->skor);
        $this->assertSame($this->userRt->id, (int) $t->diusulkan_oleh);
    }

    public function test_putuskan_bukan_kandidat_ditolak(): void
    {
        $a = $this->anak('3201000000013009');
        $b = $this->anak('3201000000013010');

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');
    }

    public function test_putuskan_di_luar_cakupan_ditolak(): void
    {
        $kelLain = Kelurahan::factory()->create()->id;
        $a = $this->anak('3201000000013011', ['id_rt' => null, 'id_kel' => $kelLain]);
        $b = $this->anak('3201000000013012', ['id_rt' => null, 'id_kel' => $kelLain]);
        $this->kandidat($a, $b);

        $this->expectException(AuthorizationException::class);
        $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');
    }

    public function test_putuskan_ulang_menimpa_usulan_yang_belum_disetujui(): void
    {
        $a = $this->anak('3201000000013013');
        $b = $this->anak('3201000000013014', ['id_rt' => null]);
        $this->kandidat($a, $b);
        $t1 = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'beda');
        $t2 = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');

        $this->assertSame($t1->id, $t2->id);
        $this->assertSame('sama', $t2->keputusan);
        $this->assertSame(1, AnakTautan::count());
    }

    public function test_tautan_yang_sudah_disetujui_tidak_bisa_diputus_ulang(): void
    {
        $a = $this->anak('3201000000013015');
        $b = $this->anak('3201000000013016', ['id_rt' => null]);
        $this->kandidat($a, $b);
        $t = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');
        $this->svc->tinjauTautan($t, User::factory()->create(['type' => 0]), true);

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'beda');
    }

    public function test_reviu_sama_disetujui_masuk_hitungan_menunggu_gabung(): void
    {
        $a = $this->anak('3201000000013017');
        $b = $this->anak('3201000000013018', ['id_rt' => null]);
        $this->kandidat($a, $b);
        $t = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'sama');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);

        $this->assertSame([$t->id], $this->svc->antreanTautanQuery($faskes)->pluck('id')->all());
        $this->svc->tinjauTautan($t, $faskes, true, 'ok');

        $this->assertSame('disetujui', $t->fresh()->status);
        $this->assertSame(1, $this->svc->menungguGabung());
        $this->assertSame([], $this->svc->antreanTautanQuery($faskes)->pluck('id')->all());
    }

    public function test_faskes_kelurahan_lain_tidak_bisa_meninjau(): void
    {
        $a = $this->anak('3201000000013019');
        $b = $this->anak('3201000000013020', ['id_rt' => null]);
        $this->kandidat($a, $b);
        $t = $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, 'beda');
        $faskesLain = User::factory()->create(['type' => 1, 'id_kel' => Kelurahan::factory()->create()->id]);

        $this->assertSame([], $this->svc->antreanTautanQuery($faskesLain)->pluck('id')->all());
        $this->expectException(AuthorizationException::class);
        $this->svc->tinjauTautan($t, $faskesLain, true);
    }
}
