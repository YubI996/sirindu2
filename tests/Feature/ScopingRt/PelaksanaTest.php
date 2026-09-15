<?php

namespace Tests\Feature\ScopingRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use App\Models\VerifikasiAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `pelaksana` = nama orang yang mengisi. Wajib untuk mode tautan & akun kelurahan (satu akun
 * dipakai banyak orang), opsional untuk akun per-RT. Diingat di sesi setelah pertama kali diisi.
 */
class PelaksanaTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, Rt $rt, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-01-01',
            'status' => 1, 'sumber' => 'capil', 'id_kel' => $rt->id_kelurahan, 'id_rt' => $rt->id,
            'nama_ibu' => 'Ibu Uji', 'nama_ayah' => 'Ayah Uji',
        ], $o));
    }

    public function test_mode_tautan_wajib_nama_pengisi_dan_tersimpan_tanpa_user(): void
    {
        $rt    = Rt::factory()->create();
        $super = User::factory()->create(['type' => 0]);
        $t     = RtAksesTautan::buat($rt, $super);
        $anak  = $this->anak('3201000000061001', $rt);
        $this->get(route('rt.akses.masuk', $t['token']));

        $this->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])
            ->assertStatus(422)->assertJsonPath('message', 'Nama pengisi wajib diisi.');
        $this->assertSame(0, VerifikasiAnak::count());

        $this->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili', 'pelaksana' => 'Bu Sari, Ketua RT'])->assertOk();
        $v = VerifikasiAnak::sole();
        $this->assertNull($v->diusulkan_oleh);
        $this->assertSame('Bu Sari, Ketua RT', $v->pelaksana);
        $this->assertSame($rt->id, (int) $anak->fresh()->verif_rt_status ? $v->id_rt : $v->id_rt);

        // pengisi diingat di sesi → request berikutnya tanpa `pelaksana` tetap tercatat
        $this->postJson(route('rt.api.usulkan', $anak), ['status' => 'pindah'])->assertOk();
        $this->assertSame('Bu Sari, Ketua RT', VerifikasiAnak::latest('id')->first()->pelaksana);
    }

    public function test_mode_tautan_putuskan_kandidat_menyimpan_pelaksana_dan_rt(): void
    {
        $rt    = Rt::factory()->create();
        $super = User::factory()->create(['type' => 0]);
        $t     = RtAksesTautan::buat($rt, $super);
        $a     = $this->anak('3201000000061011', $rt, ['sumber' => 'operasi_timbang']);
        $b     = $this->anak('3201000000061012', $rt, ['nama' => $a->nama]);
        [$x, $y] = AnakTautan::urut($a->id, $b->id);
        AnakKandidat::create(['id_anak_a' => $x, 'id_anak_b' => $y, 'skor' => 90, 'via' => 'ortu', 'child_sim' => 95, 'parent_sim' => 90, 'dipindai_at' => now()]);
        $this->get(route('rt.akses.masuk', $t['token']));

        $this->postJson(route('rt.api.putuskan'), ['a' => $a->hashid, 'b' => $b->hashid, 'keputusan' => 'sama'])->assertStatus(422);
        $this->postJson(route('rt.api.putuskan'), ['a' => $a->hashid, 'b' => $b->hashid, 'keputusan' => 'sama', 'pelaksana' => 'Pak Amir'])->assertOk();

        $tautan = AnakTautan::sole();
        $this->assertNull($tautan->diusulkan_oleh);
        $this->assertSame('Pak Amir', $tautan->pelaksana);
        $this->assertSame($rt->id, (int) $tautan->id_rt);

        // Reviu Dinkes tetap tampil walau tanpa user pengusul
        $this->actingAs($super)->get(route('admin.verifikasiRt.index', ['tab' => 'tautan']))->assertOk()->assertSee('Pak Amir')->assertSee($rt->name);
    }

    public function test_akun_rt_tidak_wajib_pelaksana_dan_reviu_menampilkan_pengisi(): void
    {
        $rt   = Rt::factory()->create();
        $u    = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan, 'name' => 'Akun RT 05']);
        $anak = $this->anak('3201000000061002', $rt);

        $this->actingAs($u)->postJson(route('rt.api.usulkan', $anak), ['status' => 'pindah'])->assertOk();
        $this->assertNull(VerifikasiAnak::sole()->pelaksana);

        $this->actingAs($u)->postJson(route('rt.api.usulkan', $anak), ['status' => 'pindah', 'pelaksana' => 'Pak Joko'])->assertOk();
        $v = VerifikasiAnak::latest('id')->first();
        $this->assertSame($u->id, (int) $v->diusulkan_oleh);
        $this->assertSame('Pak Joko', $v->pelaksana);

        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.verifikasiRt.index'))->assertOk()->assertSee('Akun RT 05')->assertSee('Pak Joko');
    }

    public function test_akun_kelurahan_wajib_pelaksana(): void
    {
        $rt   = Rt::factory()->create();
        $u    = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => null, 'rt_sekelurahan' => true, 'id_kel' => $rt->id_kelurahan]);
        $anak = $this->anak('3201000000061003', $rt);

        $this->actingAs($u)->postJson(route('rt.api.usulkan', ['anak' => $anak, 'rt' => $rt->id]), ['status' => 'berdomisili'])->assertStatus(422);
        $this->actingAs($u)->postJson(route('rt.api.usulkan', ['anak' => $anak, 'rt' => $rt->id]), ['status' => 'berdomisili', 'pelaksana' => 'Kader Nia'])->assertOk();
        $v = VerifikasiAnak::sole();
        $this->assertSame($u->id, (int) $v->diusulkan_oleh);
        $this->assertSame('Kader Nia', $v->pelaksana);
    }

    public function test_halaman_rt_menyiapkan_modal_nama_pengisi_hanya_bila_perlu(): void
    {
        $rt = Rt::factory()->create();
        $u  = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);
        $this->actingAs($u)->get(route('rt.verifikasi'))->assertOk()->assertSee('var BUTUH_PELAKSANA = false;', false);

        $super = User::factory()->create(['type' => 0]);
        $t     = RtAksesTautan::buat($rt, $super);
        $this->get(route('rt.akses.masuk', $t['token']));
        $this->get(route('rt.verifikasi'))->assertOk()->assertSee('var BUTUH_PELAKSANA = true;', false)->assertSee('Siapa yang mengisi');
    }
}
