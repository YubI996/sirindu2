<?php

namespace Tests\Feature\Imunisasi;

use App\Models\User;
use App\Services\ImunisasiStatusService;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImunisasiDashboardTahunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
    }

    private function admin(): User
    {
        // `type` (0=super-admin, 1=admin) adalah yang dibaca middleware IsAdmin.
        // Kolom `role` dipakai untuk sub-peran faskes, bukan untuk ini.
        return User::factory()->create(['type' => 1]);
    }

    public function test_tahun_default_adalah_tahun_berjalan(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertViewHas('tahun', (int) date('Y'));
    }

    public function test_tahun_valid_dari_query_string_dipakai(): void
    {
        $tahun = (int) date('Y') - 2;

        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard', ['tahun' => $tahun]))
            ->assertOk()
            ->assertViewHas('tahun', $tahun);
    }

    /**
     * @dataProvider tahunNgawur
     */
    public function test_tahun_ngawur_kembali_ke_default_bukan_500(string $input): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard', ['tahun' => $input]))
            ->assertOk()
            ->assertViewHas('tahun', (int) date('Y'));
    }

    public static function tahunNgawur(): array
    {
        return [
            'huruf'        => ['abc'],
            'nol'          => ['0'],
            'negatif'      => ['-5'],
            'terlalu jauh' => ['2099'],
            'pecahan'      => ['2026.5'],
            'kosong'       => [''],
            'sebelum daftar' => ['1999'],
        ];
    }

    public function test_pilihan_tahun_berisi_lima_tahun(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertViewHas('pilihanTahun', fn ($p) => count($p) === 5 && $p[0] === (int) date('Y'));
    }

    public function test_halaman_menampilkan_dropdown_tahun_dan_label_periode(): void
    {
        $tahun = (int) date('Y');

        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertSee('Tahun sasaran')
            ->assertSee('name="tahun"', false)
            ->assertSee("Kohort {$tahun}")
            ->assertSee('potret umur');
    }

    public function test_kartu_sasaran_memakai_istilah_bbl_si_baduta_dan_balita_dihapus(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk();

        $response->assertSee('BBL');
        $response->assertSee('Surviving Infant');
        $response->assertSee('Baduta');
        $response->assertDontSee('Balita 0&ndash;59 bulan', false);
    }

    public function test_blok_operasional_diberi_penanda_tidak_mengikuti_tahun(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertSee('tidak mengikuti tahun sasaran');
    }
}
