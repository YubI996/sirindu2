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

    /** Angka besar kartu: `<span class="n">N</span><span class="d">/ SASARAN …` di dalam satu blok. */
    private function assertAngkaBesar(string $blok, string $n, string $sasaran): void
    {
        $this->assertMatchesRegularExpression(
            '/class="n">' . preg_quote($n, '/') . '<\/span><span class="d">\/ ' . preg_quote($sasaran, '/') . ' /',
            $blok,
            "Angka besar {$n} / {$sasaran} tidak ditemukan"
        );
    }

    private function kunjungan(Anak $anak, string $tgl, array $extra = []): void
    {
        \App\Models\DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 60, 'bb' => 6, 'lla' => 12, 'lk' => 40, 'id_user' => $this->admin->id, 'sumber' => 'manual',
        ], $extra));
    }

    public function test_kartu_spm_menampilkan_angka_dari_service(): void
    {
        // Bayi 8 bln pada 31 Des 2025: 8 timbang, 2 DDTKA, Vit A, LK → lengkap.
        $bayi = $this->anak('Bayi Lengkap', '2025-04-30');
        foreach (range(5, 10) as $b) {
            $this->kunjungan($bayi, sprintf('2025-%02d-15', $b));
        }
        $this->kunjungan($bayi, '2025-11-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($bayi, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->anak('Bayi Kosong', '2025-06-30');          // 6 bln, tanpa kunjungan
        $this->anak('Anak Balita', '2023-06-30');          // 30 bln, tanpa kunjungan
        $this->anak('Prasekolah', '2020-07-31');           // 65 bln

        $html = $this->actingAs($this->admin)
            ->get(route('admin.kesmas.dashboard', ['tahun' => 2025, 'periode' => 'tahun']))
            ->assertOk()->getContent();

        $balita = $this->blok($html, 'spm-balita');
        $this->assertAngkaBesar($balita, '1', '3');
        $this->assertStringContainsString('33,3 %', $balita);

        $kBayi = $this->blok($html, 'spm-bayi');
        $this->assertAngkaBesar($kBayi, '1', '2');
        $this->assertStringContainsString('50,0 %', $kBayi);
        $this->assertStringContainsString('8× Tbg', $kBayi);
        $this->assertStringContainsString('Sisa belum lengkap', $kBayi);

        $kAb = $this->blok($html, 'spm-anak-balita');
        $this->assertAngkaBesar($kAb, '0', '1');
        $this->assertStringContainsString('0,0 %', $kAb);

        $kTk = $this->blok($html, 'spm-tk');
        $this->assertAngkaBesar($kTk, '1', '4');
        $this->assertStringContainsString('perlu perhatian', $kTk);
    }

    public function test_kartu_spm_tanpa_sasaran_menampilkan_strip_bukan_nol_persen(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->getContent();

        foreach (['spm-balita', 'spm-bayi', 'spm-anak-balita', 'spm-tk'] as $nama) {
            $blok = $this->blok($html, $nama);
            $this->assertStringContainsString('—', $blok, $nama);
            $this->assertStringNotContainsString('0,0 %', $blok, "{$nama}: denominator 0 tidak boleh tampil 0%");
        }
    }

    public function test_sdidtk_ckg_dan_idl_terisi(): void
    {
        $b = $this->anak('Bayi', '2025-07-31'); // 5 bln
        $this->kunjungan($b, '2025-10-01', ['ddtka' => 'Sesuai', 'tgl_penanda_ckg' => '2025-10-01', 'pemeriksaan_gigi' => 'Sehat']);
        $this->anak('Balita', '2023-06-30');    // 30 bln, tanpa DDTKA → gap terbesar

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->getContent();

        $sdidtk = $this->blok($html, 'sdidtk');
        $this->assertStringContainsString('Kemandirian &amp; KPSP', $sdidtk);
        $this->assertStringContainsString('Fokus intervensi', $sdidtk);
        $this->assertStringContainsString('Balita (24–59 bln)', $sdidtk);

        $ckg = $this->blok($html, 'ckg');
        $this->assertStringContainsString('Bayi baru lahir', $ckg);
        $this->assertStringContainsString('100,0 %', $ckg, 'Bebas karies 1/1');

        $idl = $this->blok($html, 'idl');
        $this->assertStringContainsString('idlDonut', $idl);
        $this->assertStringContainsString(route('admin.imunisasiDashboard'), $idl);
        $this->assertStringNotContainsString('status saat ini', $idl);
    }
}
