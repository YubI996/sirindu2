<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec akun Kesmas §6. */
class KelolaAkunKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private Kelurahan $kel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Satimpo', 'id_kecamatan' => $kec->id]);
    }

    private function simpan(array $data)
    {
        // $data di kiri `+`: email/nama dari pemanggil menang atas bawaan.
        return $this->actingAs($this->super)->from(route('super.admin.user'))
            ->post(route('super.admin.storeUser'), $data + ['name' => 'Akun', 'email' => uniqid() . '@sirindu.test']);
    }

    public function test_akun_kelurahan_tersimpan_dengan_wilayah_dan_type_2(): void
    {
        $this->simpan(['role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id, 'email' => 'satimpo@sirindu.test'])
            ->assertRedirect(route('super.admin.user'));

        $u = User::where('email', 'satimpo@sirindu.test')->firstOrFail();
        $this->assertSame(2, (int) $u->type);
        $this->assertSame($this->kel->id, (int) $u->id_kel);
        $this->assertSame($this->kel->id_kecamatan, (int) $u->id_kec);
    }

    public function test_akun_kelurahan_kedua_ditolak_dengan_pesan_jelas(): void
    {
        User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kel->id, 'email' => 'lama@sirindu.test']);

        $this->simpan(['role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id])
            ->assertSessionHasErrors(['kesmas_id_kel' => 'Kelurahan Satimpo sudah memiliki akun (lama@sirindu.test). Satu kelurahan hanya boleh memiliki satu akun.']);
    }

    public function test_akun_kelurahan_terhapus_tidak_menghalangi(): void
    {
        User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kel->id])->delete();

        $this->simpan(['role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id])->assertSessionHasNoErrors();
    }

    public function test_edit_akun_kelurahan_itu_sendiri_lolos(): void
    {
        $u = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kel->id]);

        $this->actingAs($this->super)->put(route('super.admin.updateUser', $u->id), [
            'name' => 'Nama Baru', 'email' => $u->email, 'role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', $u->fresh()->name);
    }

    public function test_kader_menurunkan_puskesmas_dari_posyandu(): void
    {
        $pkm = Puskesmas::factory()->create();
        $pos = Posyandu::factory()->create(['id_puskesmas' => $pkm->id]);

        $this->simpan(['role' => 'kader', 'kesmas_id_posyandu' => $pos->id, 'email' => 'kader@sirindu.test'])->assertSessionHasNoErrors();
        $u = User::where('email', 'kader@sirindu.test')->firstOrFail();
        $this->assertSame($pos->id, (int) $u->id_posyandu);
        $this->assertSame($pkm->id, (int) $u->id_puskesmas);
    }

    public function test_isian_wajib_per_peran(): void
    {
        $this->simpan(['role' => 'kelurahan'])->assertSessionHasErrors('kesmas_id_kel');
        $this->simpan(['role' => 'kader'])->assertSessionHasErrors('kesmas_id_posyandu');
        $this->simpan(['role' => 'kesmas_puskesmas'])->assertSessionHasErrors('id_puskesmas');
        $this->simpan(['role' => 'kesmas_rs'])->assertSessionHasErrors('id_rs');
        $this->simpan(['role' => 'sarkes'])->assertSessionHasErrors('nama_sarana');
        $this->simpan(['role' => 'pimpinan'])->assertSessionHasNoErrors();
    }

    public function test_ganti_peran_mengosongkan_lingkup_lama(): void
    {
        $u = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kel->id, 'id_kec' => $this->kel->id_kecamatan]);

        $this->actingAs($this->super)->put(route('super.admin.updateUser', $u->id), [
            'name' => $u->name, 'email' => $u->email, 'role' => 'pimpinan',
        ])->assertSessionHasNoErrors();

        $u->refresh();
        $this->assertSame('pimpinan', $u->role);
        $this->assertNull($u->id_kel);
        $this->assertNull($u->id_kec);

        // Kelurahan itu kini boleh punya akun baru.
        $this->simpan(['role' => 'kelurahan', 'kesmas_id_kel' => $this->kel->id])->assertSessionHasNoErrors();
    }

    public function test_sarkes_menyimpan_nama_sarana(): void
    {
        $this->simpan(['role' => 'sarkes', 'nama_sarana' => '  Klinik Pratama Sehat ', 'email' => 'klinik@sirindu.test'])->assertSessionHasNoErrors();
        $this->assertSame('Klinik Pratama Sehat', User::where('email', 'klinik@sirindu.test')->value('nama_sarana'));
    }

    public function test_form_menampilkan_peran_baru(): void
    {
        $html = $this->actingAs($this->super)->get(route('super.admin.user'))->assertOk()->getContent();
        foreach (['pimpinan', 'kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs', 'sarkes'] as $role) {
            $this->assertStringContainsString('value="' . $role . '"', $html, $role);
        }
        $this->assertStringContainsString('name="kesmas_id_kel"', $html);
        $this->assertStringContainsString('name="kesmas_id_posyandu"', $html);
        $this->assertStringContainsString('name="nama_sarana"', $html);
    }
}
