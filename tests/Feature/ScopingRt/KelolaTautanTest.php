<?php

namespace Tests\Feature\ScopingRt;

use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\RtAksesTautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman kelola tautan akses RT (Dinkes/puskesmas) + akun rt lingkup kelurahan di manajemen user. */
class KelolaTautanTest extends TestCase
{
    use RefreshDatabase;

    public function test_faskes_membuat_dan_mencabut_tautan_rt_sekelurahan_saja(): void
    {
        $rt     = Rt::factory()->create(['name' => 'RT 03 Sini']);
        $rtLain = Rt::factory()->create(['name' => 'RT 09 Sana']);
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $rt->id_kelurahan]);

        $this->actingAs($faskes)->get(route('admin.aksesTautan.index'))->assertOk()
            ->assertSee('RT 03 Sini')->assertDontSee('RT 09 Sana')->assertSee('Belum ada tautan');
        $this->actingAs($faskes)->post(route('admin.aksesTautan.buat', $rtLain))->assertForbidden();

        $this->actingAs($faskes)->post(route('admin.aksesTautan.buat', $rt), ['hari' => 14])
            ->assertRedirect(route('admin.aksesTautan.index'))->assertSessionHas('tautan_baru');
        $url = session('tautan_baru')['url'];
        $this->assertStringContainsString('/rt/akses/', $url);
        $t = RtAksesTautan::sole();
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $t->kedaluwarsa_at->timestamp, 5);
        $this->assertSame($faskes->id, (int) $t->dibuat_oleh);

        // Redirect pertama menampilkan token sekali; muat ulang hanya menampilkan status.
        $this->actingAs($faskes)->get(route('admin.aksesTautan.index'))->assertOk()
            ->assertSee(basename($url));
        $this->actingAs($faskes)->get(route('admin.aksesTautan.index'))->assertOk()
            ->assertSee('Aktif s.d.')->assertDontSee(basename($url));

        // Tautan bisa dibuka tanpa akun; setelah dicabut → 410
        $this->get($url)->assertRedirect(route('rt.verifikasi'));
        $this->post(route('rt.akses.keluar'));

        $this->actingAs($faskes)->post(route('admin.aksesTautan.cabut', $t))->assertRedirect();
        $this->assertNotNull($t->fresh()->dicabut_at);
        $this->get($url)->assertStatus(410);

        $tLain = RtAksesTautan::buat($rtLain, User::factory()->create(['type' => 0]));
        $this->actingAs($faskes)->post(route('admin.aksesTautan.cabut', $tLain['model']))->assertForbidden();
    }

    public function test_superadmin_melihat_semua_rt_dan_bisa_filter_kelurahan(): void
    {
        $rtA   = Rt::factory()->create(['name' => 'RT 01 Alfa']);
        $rtB   = Rt::factory()->create(['name' => 'RT 01 Beta']);
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->get(route('admin.aksesTautan.index'))->assertOk()->assertSee('RT 01 Alfa')->assertSee('RT 01 Beta');
        $this->actingAs($super)->get(route('admin.aksesTautan.index', ['kel' => $rtA->id_kelurahan]))->assertOk()
            ->assertSee('RT 01 Alfa')->assertDontSee('RT 01 Beta');
        $this->actingAs($super)->post(route('admin.aksesTautan.buat', $rtB))->assertRedirect();
        $this->assertSame(1, RtAksesTautan::aktif()->where('id_rt', $rtB->id)->count());
    }

    public function test_peran_lain_tidak_boleh_mengelola_tautan(): void
    {
        $rt = Rt::factory()->create();
        $rtUser = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);
        $this->actingAs($rtUser)->get(route('admin.aksesTautan.index'))->assertForbidden();
        $this->actingAs($rtUser)->post(route('admin.aksesTautan.buat', $rt))->assertForbidden();
    }

    public function test_superadmin_membuat_akun_rt_lingkup_kelurahan(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $kel   = Kelurahan::factory()->create();

        $this->actingAs($super)->post(route('super.admin.storeUser'), [
            'name' => 'Akun Kel', 'email' => 'kel@sirindu.go.id', 'role' => 'rt', 'rt_sekelurahan' => '1',
            'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id,
        ])->assertRedirect(route('super.admin.user'));

        $u = User::where('email', 'kel@sirindu.go.id')->firstOrFail();
        $this->assertNull($u->id_rt);
        $this->assertTrue((bool) $u->rt_sekelurahan);
        $this->assertSame($kel->id, (int) $u->id_kel);
        $this->assertSame($kel->id_kecamatan, (int) $u->id_kec);

        // lingkup kelurahan tanpa kelurahan → ditolak
        $this->actingAs($super)->from(route('super.admin.user'))->post(route('super.admin.storeUser'), [
            'name' => 'Tanpa Wilayah', 'email' => 'x@sirindu.go.id', 'role' => 'rt', 'rt_sekelurahan' => '1',
        ])->assertSessionHasErrors('id_kel');

        // ubah akun per-RT jadi lingkup kelurahan lewat edit → id_rt dikosongkan
        $rt = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $kel->id]);
        $this->actingAs($super)->put(route('super.admin.updateUser', $userRt->id), [
            'name' => $userRt->name, 'email' => $userRt->email, 'role' => 'rt', 'rt_sekelurahan' => '1', 'id_rt' => $rt->id,
            'id_kecx' => $kel->id_kecamatan, 'id_kelx' => $kel->id,
        ])->assertRedirect(route('super.admin.user'));
        $userRt->refresh();
        $this->assertNull($userRt->id_rt);
        $this->assertTrue((bool) $userRt->rt_sekelurahan);
        $this->assertSame($kel->id, (int) $userRt->id_kel);

        // dan sebaliknya: pilih RT lagi → penanda kelurahan dilepas
        $this->actingAs($super)->put(route('super.admin.updateUser', $userRt->id), [
            'name' => $userRt->name, 'email' => $userRt->email, 'role' => 'rt', 'rt_sekelurahan' => '0', 'id_rt' => $rt->id,
        ])->assertRedirect(route('super.admin.user'));
        $userRt->refresh();
        $this->assertSame($rt->id, (int) $userRt->id_rt);
        $this->assertFalse((bool) $userRt->rt_sekelurahan);
    }

    public function test_form_user_menyediakan_pilihan_lingkup_kelurahan(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $this->actingAs($super)->get(route('super.admin.user'))->assertOk()->assertSee('name="rt_sekelurahan"', false);
        $rt = Rt::factory()->create();
        $userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);
        $this->actingAs($super)->get(route('super.admin.editUser', $userRt->id))->assertOk()->assertSee('name="rt_sekelurahan"', false);
    }

    public function test_edit_akun_kelurahan_memvalidasi_wilayah_baru_dan_mempertahankan_wilayah_lama(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $kel = Kelurahan::factory()->create();
        $baru = Kelurahan::factory()->create();
        $user = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => null,
            'rt_sekelurahan' => true, 'id_kel' => $kel->id, 'id_kec' => $kel->id_kecamatan]);
        $form = ['name' => 'Nama Baru', 'email' => $user->email, 'role' => 'rt',
            'rt_sekelurahan' => '1', 'id_kel' => $kel->id];

        // Kontrol lokasi disabled: hidden id_kel tetap dikirim saat hanya mengganti nama.
        $this->actingAs($super)->put(route('super.admin.updateUser', $user->id), $form)
            ->assertSessionHasNoErrors()->assertRedirect(route('super.admin.user'));
        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->assertSame($kel->id, (int) $user->fresh()->id_kel);

        foreach (['', '999999999'] as $invalid) {
            $this->put(route('super.admin.updateUser', $user->id), $form + ['id_kelx' => $invalid])
                ->assertSessionHasErrors('id_kel');
            $this->assertSame($kel->id, (int) $user->fresh()->id_kel);
        }

        $this->put(route('super.admin.updateUser', $user->id), $form + [
            'id_kelx' => $baru->id, 'id_kecx' => $kel->id_kecamatan,
        ])->assertSessionHasNoErrors();
        $this->assertSame($baru->id, (int) $user->fresh()->id_kel);
        $this->assertSame($baru->id_kecamatan, (int) $user->fresh()->id_kec);
    }

    public function test_checkbox_nol_tetap_mewajibkan_rt_dan_pergantian_role_melepas_lingkup_kelurahan(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $this->actingAs($super)->post(route('super.admin.storeUser'), [
            'name' => 'RT biasa', 'email' => 'rt-biasa@example.test', 'role' => 'rt', 'rt_sekelurahan' => '0',
        ])->assertSessionHasErrors('id_rt');

        $kel = Kelurahan::factory()->create();
        $user = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => null,
            'rt_sekelurahan' => true, 'id_kel' => $kel->id]);
        $this->put(route('super.admin.updateUser', $user->id), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'superadmin', 'rt_sekelurahan' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $user->fresh()->rt_sekelurahan);
    }
}
