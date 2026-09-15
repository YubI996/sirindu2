<?php

namespace Tests\Feature\ScopingRt;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tiga jalan masuk ke halaman RT: akun per-RT (lama), akun per-kelurahan yang memilih RT (mode A),
 * dan tautan bertoken tanpa akun (mode B). Superadmin tetap lewat ?rt=.
 */
class AksesRtTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, Rt $rt): Anak
    {
        return Anak::create([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-01-01',
            'status' => 1, 'sumber' => 'capil', 'id_kel' => $rt->id_kelurahan, 'id_rt' => $rt->id,
        ]);
    }

    public function test_akun_kelurahan_harus_memilih_rt_sekelurahan(): void
    {
        $rt1    = Rt::factory()->create(['name' => 'RT 01 Uji']);
        $rt2    = Rt::factory()->create(['id_kelurahan' => $rt1->id_kelurahan, 'name' => 'RT 02 Uji']);
        $rtLain = Rt::factory()->create(['name' => 'RT 99 Lain']);
        $u = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => null, 'rt_sekelurahan' => true, 'id_kel' => $rt1->id_kelurahan]);
        $this->anak('3201000000060001', $rt1);
        $this->anak('3201000000060002', $rtLain);

        $this->actingAs($u)->get(route('rt.verifikasi'))->assertOk()
            ->assertSee('Pilih RT')->assertSee('RT 02 Uji')->assertDontSee('RT 99 Lain');
        $this->actingAs($u)->getJson(route('rt.api.warga'))->assertStatus(422);
        $this->actingAs($u)->getJson(route('rt.api.warga', ['rt' => $rtLain->id]))->assertForbidden();

        $rows = $this->actingAs($u)->getJson(route('rt.api.warga', ['rt' => $rt1->id]))->assertOk()->json('rows');
        $this->assertCount(1, $rows);
        // pilihan diingat di sesi → request berikutnya tanpa ?rt= tetap RT 01
        $this->assertCount(1, $this->getJson(route('rt.api.warga'))->assertOk()->json('rows'));
        $this->get(route('rt.verifikasi'))->assertOk()->assertSee('RT 01 Uji')->assertDontSee('id="pilih-rt-awal"', false);
    }

    public function test_tautan_token_membuka_halaman_rt_tanpa_login(): void
    {
        $rt    = Rt::factory()->create(['name' => 'RT 07 Token']);
        $super = User::factory()->create(['type' => 0]);
        $t     = RtAksesTautan::buat($rt, $super);
        $this->anak('3201000000060003', $rt);

        $this->get(route('rt.akses.masuk', $t['token']))->assertRedirect(route('rt.verifikasi'));
        $this->get(route('rt.verifikasi'))->assertOk()->assertSee('RT 07 Token')->assertSee(route('rt.akses.keluar'));
        $this->assertCount(1, $this->getJson(route('rt.api.warga'))->assertOk()->json('rows'));
        $this->assertSame(1, (int) $t['model']->fresh()->jumlah_pakai);

        // mode tautan tak menjangkau halaman admin
        $this->get('/admin/home')->assertRedirect(route('login'));

        $this->post(route('rt.akses.keluar'))->assertRedirect();
        $this->getJson(route('rt.api.warga'))->assertStatus(401);
    }

    public function test_tautan_kedaluwarsa_atau_dicabut_ditolak(): void
    {
        $rt    = Rt::factory()->create();
        $super = User::factory()->create(['type' => 0]);
        $t     = RtAksesTautan::buat($rt, $super, 1);

        $this->travel(2)->days();
        $this->get(route('rt.akses.masuk', $t['token']))->assertStatus(410)->assertSee('tidak berlaku');
        $this->get(route('rt.akses.masuk', 'ngawur'))->assertStatus(410);

        // sesi yang sudah masuk lalu tautannya dicabut → ikut tertutup
        $this->travelBack();
        $t2 = RtAksesTautan::buat($rt, $super);
        $this->get(route('rt.akses.masuk', $t2['token']))->assertRedirect(route('rt.verifikasi'));
        $t2['model']->cabut();
        $this->getJson(route('rt.api.warga'))->assertStatus(401);
    }

    public function test_akun_rt_dan_superadmin_tetap_seperti_semula(): void
    {
        $rt = Rt::factory()->create();
        $u  = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);
        $this->anak('3201000000060004', $rt);
        $this->assertCount(1, $this->actingAs($u)->getJson(route('rt.api.warga'))->assertOk()->json('rows'));

        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->getJson(route('rt.api.warga', ['rt' => $rt->id]))->assertOk();

        $faskes = User::factory()->create(['type' => 1, 'role' => 'imunisasi_faskes']);
        $this->actingAs($faskes)->get(route('rt.verifikasi'))->assertForbidden();
    }

    public function test_akun_rt_tanpa_rt_dan_tanpa_penanda_kelurahan_tetap_403(): void
    {
        $rt = Rt::factory()->create();
        $u  = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => null, 'rt_sekelurahan' => false, 'id_kel' => $rt->id_kelurahan]);
        $this->actingAs($u)->get(route('rt.verifikasi'))->assertForbidden();
        $this->actingAs($u)->getJson(route('rt.api.warga', ['rt' => $rt->id]))->assertForbidden();
    }

    public function test_tamu_tanpa_apa_pun_ditolak(): void
    {
        $this->get(route('rt.verifikasi'))->assertRedirect(route('login'));
        $this->getJson(route('rt.api.warga'))->assertStatus(401);
    }
}
