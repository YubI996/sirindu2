<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HalamanRtTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_memuat_dua_tab_aksi_dan_logout_tanpa_menu_admin(): void
    {
        $rt   = Rt::factory()->create();
        $user = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rt->id, 'id_kel' => $rt->id_kelurahan]);

        $res = $this->actingAs($user)->get(route('rt.verifikasi'))->assertOk();

        $res->assertSee('Warga RT')
            ->assertSee('Belum ber-RT')
            ->assertSee(route('rt.api.warga'))
            ->assertSee(route('rt.api.tanpaRt'))
            ->assertSee(route('logout'))
            ->assertSee('data-status="berdomisili"', false)
            ->assertSee('data-status="pindah"', false)
            ->assertSee('data-status="meninggal"', false)
            ->assertSee('data-status="tidak_dikenal"', false)
            ->assertSee('data-status="bukan_rt_ini"', false)
            ->assertDontSee(route('admin.home'))
            ->assertDontSee('Berat Badan')
            ->assertDontSee('Tinggi Badan');
    }

    public function test_url_usulkan_memakai_placeholder_hashid(): void
    {
        $src = file_get_contents(resource_path('views/rt/verifikasi.blade.php'));

        $this->assertStringContainsString("route('rt.api.usulkan', ['anak' => '__ID__']", $src);
        $this->assertStringContainsString("'__ID__'", $src);
        $this->assertStringContainsString('X-CSRF-TOKEN', $src);
    }
}
