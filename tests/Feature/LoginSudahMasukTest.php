<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug prod Sept 2026: petugas yang masih memegang sesi (mis. "Ingat saya")
 * klik "Masuk" di landing → /login → dilempar balik ke / — terlihat seperti
 * halaman cuma refresh. Sebabnya middleware `guest` bawaan Laravel mengarah
 * ke route('home') yang sudah dihapus, lalu jatuh ke '/'.
 */
class LoginSudahMasukTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_yang_sudah_login_membuka_login_diarahkan_ke_beranda_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/admin/home');
    }

    /** @test */
    public function landing_menampilkan_tautan_dasbor_bukan_masuk_saat_sudah_login(): void
    {
        $user = User::factory()->superAdmin()->create();

        $res = $this->actingAs($user)->get('/');

        $res->assertOk()
            ->assertSee(route('admin.home'))
            ->assertDontSee(route('login'));
    }

    /** @test */
    public function landing_menampilkan_tautan_masuk_untuk_tamu(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('login'))
            ->assertDontSee(route('admin.home'));
    }
}
