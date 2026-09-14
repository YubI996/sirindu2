<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Simulasi persona Ketua RT (14 Sep 2026): skenario yang lahir dari "apa yang akan dilakukan
 * RT sungguhan" — klik dua kali, sesi kedaluwarsa, ganti-ganti keputusan, mengutak-atik URL,
 * RT-nya dihapus, catatan kepanjangan. Tiap tes = satu persona.
 */
class PersonaRtTest extends TestCase
{
    use RefreshDatabase;

    private Rt $rt;
    private User $userRt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil', 'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id], $o));
    }

    /** Pak Bambang (tidak sabaran): klik "Berdomisili" dua kali cepat → tetap satu usulan. */
    public function test_klik_dua_kali_hanya_menyisakan_satu_usulan(): void
    {
        $anak = $this->anak('3201000000023001');

        $this->actingAs($this->userRt)->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])->assertOk();
        $this->actingAs($this->userRt)->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])->assertOk();

        $this->assertSame(1, VerifikasiAnak::where('id_anak', $anak->id)->where('reviu', 'diusulkan')->count());
    }

    /** Bu Siti (salah pencet "Meninggal"): pencet "Berdomisili" lagi → usulan salah tergantikan. */
    public function test_salah_pencet_bisa_diperbaiki_dengan_pencet_ulang(): void
    {
        $anak = $this->anak('3201000000023002');

        $this->actingAs($this->userRt)->postJson(route('rt.api.usulkan', $anak), ['status' => 'meninggal'])->assertOk();
        $this->actingAs($this->userRt)->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])
            ->assertOk()->assertJsonPath('verif_status', 'berdomisili');

        $this->assertSame('berdomisili', $anak->fresh()->verif_rt_status);
        $this->assertSame('ditolak', VerifikasiAnak::where('id_anak', $anak->id)->where('status', 'meninggal')->value('reviu'));
    }

    /** Pak Rudi (halaman dibiarkan terbuka semalaman): sesi habis → JSON 401, bukan HTML login. */
    public function test_sesi_habis_mengembalikan_401_json(): void
    {
        $anak = $this->anak('3201000000023003');

        $this->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
        $this->getJson(route('rt.api.warga'))->assertStatus(401);
    }

    /** Bu Fitri (menulis catatan panjang lebar): 1001 karakter ditolak 422 dengan pesan. */
    public function test_catatan_kepanjangan_ditolak_dengan_pesan(): void
    {
        $anak = $this->anak('3201000000023004');

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'pindah', 'catatan' => str_repeat('a', 1001)])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['catatan']]);
    }

    /** Pak Irfan (iseng ganti URL): RT biasa menambah ?rt=RT_LAIN → tetap RT-nya sendiri. */
    public function test_parameter_rt_diabaikan_untuk_akun_rt(): void
    {
        $lain = Rt::factory()->create();
        $this->anak('3201000000023005');
        $this->anak('3201000000023006', ['id_rt' => $lain->id, 'id_kel' => $lain->id_kelurahan]);

        $rows = $this->actingAs($this->userRt)->getJson(route('rt.api.warga', ['rt' => $lain->id]))->assertOk()->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('3201000000023005', $rows[0]['nik']);
    }

    /** Pak Hendra (coba buka menu admin): semua rute admin 403, halaman RT tetap bisa. */
    public function test_akun_rt_tidak_bisa_masuk_admin_tapi_halaman_rt_normal(): void
    {
        $this->actingAs($this->userRt)->get('/admin/verifikasi-rt')->assertForbidden();
        $this->actingAs($this->userRt)->get('/admin/verifikasi-rt/gabung')->assertForbidden();
        $this->actingAs($this->userRt)->get(route('rt.verifikasi'))->assertOk();
    }

    /** Bu Mega (RT-nya dihapus dari master setelah akun dibuat): tidak boleh error 500. */
    public function test_rt_yang_dihapus_memberi_403_bukan_500(): void
    {
        $this->rt->delete(); // users.id_rt → NULL (nullOnDelete)

        $this->actingAs($this->userRt->fresh())->get(route('rt.verifikasi'))->assertForbidden();
        $this->actingAs($this->userRt->fresh())->getJson(route('rt.api.warga'))->assertForbidden();
    }

    /** Bu Dewi (klaim, lalu sadar bukan warganya): "Bukan" menggantikan klaim & menyembunyikan baris. */
    public function test_klaim_lalu_bukan_menggantikan_klaim(): void
    {
        $anak = $this->anak('3201000000023007', ['id_rt' => null]);

        $this->actingAs($this->userRt)->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])->assertOk();
        $this->actingAs($this->userRt)->postJson(route('rt.api.usulkan', $anak), ['status' => 'bukan_rt_ini'])
            ->assertOk()->assertJsonPath('hilang', true);

        $this->assertSame(0, VerifikasiAnak::where('id_anak', $anak->id)->where('klaim_id_rt', $this->rt->id)->where('reviu', 'diusulkan')->count());
        $this->assertNull($anak->fresh()->verif_rt_status, 'klaim yang digantikan tidak meninggalkan tag');
        $this->assertCount(0, $this->actingAs($this->userRt)->getJson(route('rt.api.tanpaRt'))->json('rows'));
    }

    /** Pak Eko (pasangan dengan anak dari kelurahan lain): kartu harus menyebut wilayah kedua anak. */
    public function test_kandidat_menyertakan_wilayah_kedua_anak(): void
    {
        $kelLain = Kelurahan::factory()->create(['name' => 'Kelurahan Seberang']);
        $a = $this->anak('3201000000023008', ['nama' => 'Kembar Persis']);
        $b = $this->anak('3201000000023009', ['nama' => 'Kembar Persis', 'id_rt' => null, 'id_kel' => $kelLain->id]);
        [$x, $y] = AnakTautan::urut($a->id, $b->id);
        AnakKandidat::create(['id_anak_a' => $x, 'id_anak_b' => $y, 'skor' => 190, 'via' => 'ortu', 'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);

        $row = $this->actingAs($this->userRt)->getJson(route('rt.api.kandidat'))->assertOk()->json('rows.0');

        $this->assertArrayHasKey('wilayah', $row['a']);
        $this->assertStringContainsString('Kelurahan Seberang', $row['b']['wilayah']);
        $this->assertStringContainsString($this->rt->name, $row['a']['wilayah']);
    }

    /** Dua RT sekelurahan mengklaim anak yang sama: klaim yang disetujui membatalkan klaim RT lain. */
    public function test_klaim_ganda_dari_dua_rt_dibereskan_saat_disetujui(): void
    {
        $rtLain = Rt::factory()->create(['id_kelurahan' => $this->rt->id_kelurahan]);
        $userLain = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rtLain->id, 'id_kel' => $rtLain->id_kelurahan]);
        $anak = $this->anak('3201000000023010', ['id_rt' => null]);
        $svc = app(\App\Services\VerifikasiRtService::class);
        $klaimA = $svc->usulkan($anak, $this->rt, $this->userRt, 'berdomisili');
        $klaimB = $svc->usulkan($anak, $rtLain, $userLain, 'berdomisili');
        $super = User::factory()->create(['type' => 0]);

        $svc->tinjau($klaimA, $super, true);

        $this->assertSame($this->rt->id, (int) $anak->fresh()->id_rt);
        $this->assertSame('ditolak', $klaimB->fresh()->reviu);
        $this->assertStringContainsString($this->rt->name, (string) $klaimB->fresh()->catatan_reviu);
        $this->assertSame([], $svc->antreanQuery($super)->pluck('id')->all(), 'antrean bersih');
    }

    /** Pak Agus (RT tanpa satu pun warga): halaman tetap terbuka, progres 0 dari 0. */
    public function test_rt_kosong_tetap_terbuka(): void
    {
        $this->actingAs($this->userRt)->get(route('rt.verifikasi'))->assertOk()->assertSee('0 dari 0');
        $this->assertSame(['total' => 0, 'diverifikasi' => 0], $this->actingAs($this->userRt)->getJson(route('rt.api.warga'))->json('progres'));
    }
}
