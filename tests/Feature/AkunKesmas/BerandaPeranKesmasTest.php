<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BerandaPeranKesmasTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_nama_sarana_ada(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'nama_sarana'));
    }

    public function test_beranda_per_peran(): void
    {
        foreach (['pimpinan', 'kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs'] as $role) {
            $this->assertSame('admin.kesmas.dashboard', (new User(['role' => $role]))->berandaRoute(), $role);
        }
        $this->assertSame('akses.belumDiatur', (new User(['role' => 'sarkes']))->berandaRoute());
        $this->assertSame('rt.verifikasi', (new User(['role' => 'rt']))->berandaRoute());
        $this->assertSame('admin.home', (new User(['role' => 'superadmin']))->berandaRoute());
    }

    public function test_login_peran_terbatas_ke_berandanya(): void
    {
        config(['services.recaptcha.enabled' => false]);
        User::factory()->create(['email' => 'pim@sirindu.test', 'password' => bcrypt('rahasia123'), 'type' => 2, 'role' => 'pimpinan']);

        $this->post(route('login'), ['email' => 'pim@sirindu.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.kesmas.dashboard'));
    }

    public function test_sarkes_melihat_halaman_akses_belum_diatur(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'sarkes', 'nama_sarana' => 'Klinik Pratama Sehat']);

        $this->actingAs($user)->get(route('akses.belumDiatur'))
            ->assertOk()
            ->assertSee('Akses akun Anda belum diatur. Hubungi Dinas Kesehatan.')
            ->assertSee('Klinik Pratama Sehat');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('akses.belumDiatur'))->assertRedirect(route('login'));
    }
}
