<?php
// tests/Feature/Kesmas/KesmasDashboardControllerTest.php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman dasbor Kesmas (spec 2026-09-21 §4–6): akses, validasi, filter, menu, isi kartu. */
class KesmasDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private Kecamatan $kec;
    private Kelurahan $kel;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
        $this->superAdmin = User::factory()->create(['type' => 0]);
        $this->admin = User::factory()->create(['type' => 1]);
        $this->kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $this->kec->id]);
    }

    private function anak(string $nama, string $tglLahir, array $extra = []): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create(array_merge([
            'nama' => $nama, 'nik' => str_pad((string) $n, 16, '5', STR_PAD_LEFT), 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => $tglLahir, 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
        ], $extra));
    }

    /**
     * Potongan HTML satu blok: dari tag pembuka ber-`data-blok="$nama"` sampai komentar
     * penutup `<!-- /$nama -->` (setiap blok di view ditutup komentar itu). Assertion angka
     * dikurung di sini supaya tidak lolos palsu karena mencocoki blok lain.
     */
    protected function blok(string $html, string $nama): string
    {
        $re = '/<(?:section|article|div)\b[^>]*data-blok="' . preg_quote($nama, '/') . '"[^>]*>.*?<!-- \/' . preg_quote($nama, '/') . ' -->/s';
        $this->assertMatchesRegularExpression($re, $html, "Blok '{$nama}' tidak ditemukan");
        preg_match($re, $html, $m);

        return $m[0];
    }

    public function test_super_admin_dan_admin_bisa_membuka_faskes_surveilans_ditolak_tamu_dialihkan(): void
    {
        $this->actingAs($this->superAdmin)->get(route('admin.kesmas.dashboard'))->assertOk()->assertSee('Dashboard Kesmas');
        $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard'))->assertOk();

        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);
        $this->actingAs($faskes)->get(route('admin.kesmas.dashboard'))->assertForbidden();
        $this->actingAs($faskes)->getJson(route('admin.kesmas.registri'))->assertForbidden();

        // actingAs() di atas masih menempel pada guard di test yang sama; tamu sungguhan = guard bersih.
        auth()->forgetGuards();
        $this->get(route('admin.kesmas.dashboard'))->assertRedirect(route('login'));
    }

    public function test_periode_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['periode' => 'bulan']))
            ->assertSessionHasErrors('periode');
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['periode' => 'bulan']))
            ->assertStatus(422)->assertJsonValidationErrors('periode');
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['tahun' => 1999]))
            ->assertStatus(422)->assertJsonValidationErrors('tahun');
        $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', ['id_kelurahan' => 99999]))
            ->assertStatus(422)->assertJsonValidationErrors('id_kelurahan');
    }

    public function test_kepala_menampilkan_label_periode_dan_wilayah_terpilih(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.kesmas.dashboard', ['tahun' => 2025, 'periode' => 'tw3', 'id_kelurahan' => $this->kel->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Triwulan III 2025 (1 Jul–30 Sep)', $html);
        $this->assertStringContainsString('Kel. Berbas Tengah', $html);
        $this->assertMatchesRegularExpression('/<option value="tw3"[^>]*selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="' . $this->kel->id . '"[^>]*selected/', $html);
    }

    public function test_default_tahun_ini_dan_wilayah_kota(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Tahun ' . now()->year, $html);
        $this->assertStringContainsString('Kota Bontang', $html);
    }

    public function test_tautan_export_membawa_filter_wilayah(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.kesmas.dashboard', ['id_kecamatan' => $this->kec->id, 'id_kelurahan' => $this->kel->id]))
            ->getContent();

        $this->assertStringContainsString(
            e(route('admin.export.kesmas.index', ['id_kec' => $this->kec->id, 'id_kel' => $this->kel->id])),
            $html
        );
    }

    public function test_menu_sidebar_memuat_kesmas_untuk_kedua_peran(): void
    {
        foreach ([$this->superAdmin, $this->admin] as $user) {
            $html = $this->actingAs($user)->get(route('admin.kesmas.dashboard'))->getContent();
            $this->assertMatchesRegularExpression(
                '/<a href="' . preg_quote(route('admin.kesmas.dashboard'), '/') . '" class="active">Kesmas<\/a>/',
                $html,
                'Menu Dashboard → Kesmas harus ada dan aktif untuk user type ' . $user->type
            );
        }
    }

    public function test_chip_usia_dari_query_string_terpilih(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['usia' => 'baduta']))->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*data-usia="baduta"[^>]*aria-pressed="true"/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-usia="semua"[^>]*aria-pressed="false"/', $html);
    }
}
