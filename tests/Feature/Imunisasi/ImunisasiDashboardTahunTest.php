<?php

namespace Tests\Feature\Imunisasi;

use App\Models\Anak;
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

    public function test_kartu_wus_placeholder_tampil(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertSee('WUS hamil')
            ->assertSee('WUS tidak hamil');
    }

    public function test_catatan_antigen_dilewati_tidak_tampil_saat_master_data_bersih(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertDontSee('dilewatkan dari cakupan');
    }

    public function test_catatan_antigen_dilewati_tampil_saat_ada_antigen_tanpa_batas_usia(): void
    {
        \App\Models\JenisVaksin::create([
            'kode' => 'ANTIGEN-TANPA-BATAS',
            'nama' => 'Antigen Tanpa Batas Usia',
            'kategori' => 'Wajib',
            'usia_pemberian_min' => 60,
            'usia_pemberian_max' => null,
            'interval_hari' => null,
            'catchup_max_hari' => null,
            'bisa_dikejar' => true,
            'aktif' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertSee('dilewatkan dari cakupan')
            ->assertSee('Antigen Tanpa Batas Usia');
    }

    public function test_blok_operasional_diberi_penanda_tidak_mengikuti_tahun(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->assertSee('tidak mengikuti tahun sasaran');
    }

    public function test_butuh_kejar_di_view_tetap_saat_tahun_sasaran_diganti(): void
    {
        Anak::create([
            'nama' => 'Anak Kejar Lintas Kohort',
            'nik' => '9876543210123456',
            'jk' => 1,
            'tempat_lahir' => 'Bontang',
            'tgl_lahir' => now()->subMonths(18)->toDateString(),
            'status' => 1,
        ]);
        $tahun = (int) date('Y');
        $this->actingAs($this->admin());

        $sekarang = $this->get(route('admin.imunisasiDashboard', ['tahun' => $tahun]))
            ->assertOk()->assertViewHas('tahun', $tahun);
        $lampau = $this->get(route('admin.imunisasiDashboard', ['tahun' => $tahun - 2]))
            ->assertOk()->assertViewHas('tahun', $tahun - 2);

        $this->assertSame(1, $sekarang->viewData('butuhKejar'), 'Fixture harus benar-benar membutuhkan kejar.');
        $this->assertSame($sekarang->viewData('butuhKejar'), $lampau->viewData('butuhKejar'));
        $this->assertNotSame($sekarang->viewData('sasaran'), $lampau->viewData('sasaran'));
    }
}
