<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkunRtTest extends TestCase
{
    use RefreshDatabase;

    private function userRt(): User
    {
        $rt = Rt::factory()->create();
        return User::factory()->create([
            'type' => 2, 'role' => 'rt', 'id_rt' => $rt->id,
            'id_kel' => $rt->id_kelurahan, 'password' => bcrypt('rahasia123'),
        ]);
    }

    public function test_login_rt_diarahkan_ke_halaman_verifikasi(): void
    {
        config(['services.recaptcha.enabled' => false]);
        $user = $this->userRt();

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('rt.verifikasi'));
    }

    public function test_rt_yang_sudah_login_membuka_login_diarahkan_ke_verifikasi(): void
    {
        $this->actingAs($this->userRt())->get('/login')->assertRedirect(route('rt.verifikasi'));
    }

    public function test_rt_ditolak_dari_rute_admin(): void
    {
        $user = $this->userRt();
        $this->actingAs($user)->get('/admin/home')->assertForbidden();
        $this->actingAs($user)->get('/admin/timbang-dashboard')->assertForbidden();
        $this->actingAs($user)->getJson(route('admin.timbang.daftar'))->assertForbidden();
    }

    public function test_non_rt_ditolak_dari_halaman_rt(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'imunisasi_faskes']);
        $this->actingAs($faskes)->get(route('rt.verifikasi'))->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_login_dari_halaman_rt(): void
    {
        $this->get(route('rt.verifikasi'))->assertRedirect(route('login'));
    }

    public function test_landing_menawarkan_halaman_verifikasi_untuk_rt(): void
    {
        $this->actingAs($this->userRt())->get('/')
            ->assertOk()
            ->assertSee(route('rt.verifikasi'))
            ->assertDontSee(route('admin.home'));
    }
}
