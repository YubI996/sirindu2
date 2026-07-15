<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPublikTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function endpoint_agregat_publik_bisa_diakses_tamu(): void
    {
        foreach (['ringkasan', 'gizi', 'tren', 'coverage', 'program'] as $name) {
            $this->getJson(route("public.timbang.$name"))
                ->assertOk();
        }
    }

    /** @test */
    public function ringkasan_publik_mengembalikan_kunci_agregat(): void
    {
        $this->getJson(route('public.timbang.ringkasan'))
            ->assertOk()
            ->assertJsonStructure(['total_anak', 'total_ditimbang', 'coverage', 'bb_tidak_naik']);
    }

    /** @test */
    public function daftar_dan_export_tetap_butuh_login(): void
    {
        $this->get(route('admin.timbang.daftar'))->assertRedirect(route('login'));
        $this->get(route('admin.timbang.daftar.export'))->assertRedirect(route('login'));
    }

    /** @test */
    public function dashboard_admin_tetap_butuh_login(): void
    {
        $this->get('/admin/timbang-dashboard')->assertRedirect(route('login'));
    }

    /** @test */
    public function root_menampilkan_landing_untuk_tamu(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('SIRINDU');
    }

    /** @test */
    public function login_tetap_bisa_diakses(): void
    {
        $this->get('/login')->assertOk();
    }

    /** @test */
    public function landing_tidak_membocorkan_fitur_daftar_nama(): void
    {
        $res = $this->get('/');
        $res->assertOk();
        $res->assertDontSee('daftar-modal');
        $res->assertDontSee('Export Excel');
        $res->assertDontSee('admin.timbang.daftar');
        $res->assertDontSee('timbang-publik/api/daftar');
    }

    /** @test */
    public function landing_memuat_section_dashboard(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Status Gizi Balita')
            ->assertSee('Ketercapaian per Wilayah')
            ->assertSee('timbang-publik/api/ringkasan'); // route publik dirender di JS var
    }
}
