<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function kategori(string $nama, array $angka = [], bool $aktif = true): SpmKategori
    {
        $kategori = SpmKategori::create(['nama' => $nama, 'satuan' => 'orang', 'is_active' => $aktif]);

        if ($angka !== []) {
            SpmCapaian::create(array_merge(['id_kategori' => $kategori->id, 'tahun' => now()->year], $angka));
        }

        return $kategori;
    }

    public function test_superadmin_bisa_membuka(): void
    {
        $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);
    }

    public function test_admin_bisa_membuka(): void
    {
        $this->actingAs(User::factory()->create(['type' => 1]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);
    }

    public function test_faskes_surveilans_bisa_membuka(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);

        $this->actingAs($faskes)
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get(route('admin.spm.dashboard'))->assertRedirect(route('login'));
    }

    public function test_kategori_belum_dilaporkan_tidak_menurunkan_rata_rata(): void
    {
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 900]);  // 90 %
        $this->kategori('Pelayanan B', ['sasaran' => 1000]);               // belum dilaporkan

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $ringkasan = $response->viewData('ringkasan');

        $this->assertSame(90.0, $ringkasan['rata_rata']);
        $this->assertSame(1, $ringkasan['belum']);
        $this->assertSame(1, $ringkasan['dihitung']);
        $response->assertSee('belum dilaporkan');
    }

    public function test_sasaran_nol_tampil_tanpa_persen_dan_tanpa_error(): void
    {
        $this->kategori('Pelayanan Tanpa Sasaran', ['sasaran' => 0, 'tw1' => 5]);

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $this->assertSame(1, $response->viewData('ringkasan')['tanpa_sasaran']);
        $this->assertNull($response->viewData('ringkasan')['rata_rata']);
        $response->assertSee('Tanpa sasaran');
    }

    public function test_kategori_tanpa_baris_capaian_tetap_muncul_sebagai_belum(): void
    {
        $this->kategori('Pelayanan Tanpa Angka');

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee('Pelayanan Tanpa Angka')->assertSee('Belum dilaporkan');
        $this->assertSame(1, $response->viewData('ringkasan')['belum']);
    }

    public function test_kategori_nonaktif_tidak_ikut(): void
    {
        $this->kategori('Pelayanan Nonaktif', ['sasaran' => 100, 'tw1' => 10], false);

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertDontSee('Pelayanan Nonaktif');
        $this->assertSame(0, $response->viewData('ringkasan')['jumlah_kategori']);
    }

    public function test_capaian_melebihi_sasaran_bar_dipotong_angka_tidak(): void
    {
        $this->kategori('Pelayanan Melebihi', ['sasaran' => 1000, 'tw1' => 1120]);

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee('112,0%');
        $this->assertStringNotContainsString('width: 112', $response->getContent());
        $response->assertSee('width: 100%', false);
    }

    public function test_tahun_ngawur_jatuh_ke_tahun_ini(): void
    {
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 500]);

        foreach (['abcd', '1900', '9999', ''] as $tahun) {
            $response = $this->actingAs(User::factory()->create(['type' => 0]))
                ->get(route('admin.spm.dashboard', ['tahun' => $tahun]))
                ->assertStatus(200);

            $this->assertSame((int) now()->year, $response->viewData('tahun'));
        }
    }

    public function test_tahun_lain_menampilkan_angka_tahun_itu(): void
    {
        $kategori = $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 500]);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2025, 'sasaran' => 800, 'tw1' => 800]);

        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard', ['tahun' => 2025]))
            ->assertStatus(200);

        $this->assertSame(2025, $response->viewData('tahun'));
        $this->assertSame(100.0, $response->viewData('ringkasan')['rata_rata']);
    }

    public function test_belum_ada_kategori_menampilkan_empty_state(): void
    {
        $response = $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee('Belum ada kategori SPM');
        $response->assertSee(route('admin.masterdata.spm.index'), false);
    }

    public function test_empty_state_tidak_menawarkan_master_data_ke_admin_biasa(): void
    {
        $response = $this->actingAs(User::factory()->create(['type' => 1]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);

        $response->assertSee('Belum ada kategori SPM');
        $response->assertDontSee(route('admin.masterdata.spm.index'), false);
    }

    public function test_judul_dan_breadcrumb_terisi(): void
    {
        $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200)
            ->assertSee('Dasbor SPM')
            ->assertDontSee('@endsection');
    }
}
