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
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id, 'sasaran_balita_kesmas' => 1,
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
        $this->assertStringContainsString('1 bayi (0–11 bln) + 0 anak balita (12–59 bln)', $balita);

        $kBayi = $this->blok($html, 'spm-bayi');
        $this->assertAngkaBesar($kBayi, '1', '2');
        $this->assertStringContainsString('50,0 %', $kBayi);
        $this->assertStringContainsString('8× Tbg', $kBayi);
        $this->assertStringContainsString('Sisa belum lengkap', $kBayi);

        $kAb = $this->blok($html, 'spm-anak-balita');
        $this->assertAngkaBesar($kAb, '0', '1');
        $this->assertStringContainsString('0,0 %', $kAb);

        $kTk = $this->blok($html, 'spm-tk');
        $this->assertAngkaBesar($kTk, '1', '3'); // K4 0–59 bln: anak prasekolah 65 bln tidak ikut
        $this->assertStringContainsString('perlu perhatian', $kTk);
    }

    public function test_label_kartu_mengikuti_nama_indikator_klien_dan_chip_k4(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->assertOk()->getContent();

        $this->assertStringContainsString('Usia 0–5 tahun (0–59 bulan): gabungan Pelayanan Kesehatan Bayi + Anak Balita', $this->blok($html, 'spm-balita'));
        $kTk = $this->blok($html, 'spm-tk');
        $this->assertStringContainsString('SPM Tumbuh Kembang Balita', $kTk);
        $this->assertStringContainsString('Balita Dilayani Tumbuh Kembang', $kTk);
        $this->assertStringContainsString('0–59 bulan dengan min.', $kTk);
        $this->assertStringContainsString('Cakupan Balita &amp; Anak Prasekolah Dilayani SDIDTK', $this->blok($html, 'sdidtk'));
        $this->assertStringContainsString('tidak memakai tanda Sasaran Balita Kesmas', $this->blok($html, 'idl'));
        $this->assertStringContainsString('HBIG diberikan dalam periode', $this->blok($html, 'layanan'));
        $this->assertMatchesRegularExpression('/<button[^>]*data-usia="balita_0_59"[^>]*>Semua balita \(0–59\)<\/button>/', $html);

        $this->getJson(route('admin.kesmas.registri', ['usia' => 'balita_0_59']))->assertOk();
        $this->getJson(route('admin.kesmas.registri', ['usia' => 'balita_0_60']))->assertUnprocessable();
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
        $this->assertStringContainsString(e(route('admin.imunisasiDashboard', ['tahun' => 2025])), $idl);
        $this->assertStringNotContainsString('status saat ini', $idl);
    }

    public function test_halaman_memuat_kerangka_registri(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();
        $blok = $this->blok($html, 'registri');

        $this->assertStringContainsString('id="registriBody"', $blok);
        $this->assertStringContainsString('aria-live="polite"', $blok);
        $this->assertStringContainsString('<label for="registriCari"', $blok);
        $this->assertStringContainsString('<label for="registriGizi"', $blok);
        $this->assertStringContainsString(route('admin.kesmas.registri'), $html);
    }

    public function test_endpoint_registri_memakai_filter_dan_mengembalikan_bentuk_json(): void
    {
        $budi = $this->anak('Budi', '2023-06-30', ['nama_ibu' => 'Ibu Budi']);
        $this->kunjungan($budi, '2025-09-10', ['zscore_bb_u' => -2.5]);
        $this->anak('Citra', '2025-07-31');

        $hasil = $this->actingAs($this->admin)
            ->getJson(route('admin.kesmas.registri', [
                'tahun' => 2025, 'periode' => 'tahun', 'usia' => 'balita',
                'q' => '  Ibu Budi  ', 'status_gizi' => 'perhatian', 'id_kelurahan' => $this->kel->id,
            ]))
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['no', 'nama', 'nik', 'jk', 'umur_bln', 'kelurahan', 'rt', 'posyandu', 'kunjungan', 'idl', 'ibl', 'catatan', 'url_detail']], 'total', 'page', 'last_page', 'per_page'])
            ->json();

        $this->assertSame(1, $hasil['total']);
        $this->assertSame(20, $hasil['per_page']);
        $this->assertSame('Budi', $hasil['data'][0]['nama']);
        $this->assertSame('underweight', $hasil['data'][0]['kunjungan']['gizi']['kode']);
    }

    public function test_endpoint_registri_menolak_filter_tidak_valid(): void
    {
        foreach (['status_gizi' => 'gemuk', 'page' => 0, 'usia' => 'remaja', 'q' => str_repeat('a', 101)] as $key => $value) {
            $this->actingAs($this->admin)->getJson(route('admin.kesmas.registri', [$key => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }

    public function test_layanan_lingkungan_menampilkan_pembagi_terisi_dan_belum_diisi(): void
    {
        $a = $this->anak('Terisi', '2024-06-30', ['air_bersih' => 1, 'merokok_keluarga' => 1]); // 18 bln
        $this->kunjungan($a, '2025-03-01', ['kn1' => 1, 'mtbs' => 0]);
        $this->anak('Kosong', '2024-06-30');
        $b = $this->anak('Bayi', '2025-09-30', ['skrining_shk' => 'tidak_normal']); // 3 bln

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->getContent();
        $blok = $this->blok($html, 'layanan');

        $this->assertStringContainsString('KN1', $blok);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $blok);
        $xpath = new \DOMXPath($dom);
        $baris = function (string $kode) use ($xpath): string {
            $nodes = $xpath->query('//*[@data-baris="' . $kode . '"]');
            $this->assertSame(1, $nodes->length, "Baris {$kode} harus unik");

            return preg_replace('/\s+/u', ' ', $nodes->item(0)->textContent);
        };
        $kn1 = $baris('kn1');
        $this->assertStringContainsString('1 / 1 (100,0 %)', $kn1);
        $this->assertStringContainsString('2 anak belum diisi', $kn1, 'sasaran 3 − terisi 1');
        $this->assertStringContainsString('0 / 1 (0,0 %)', $baris('mtbs'));
        $this->assertStringContainsString('0 / 0 (—)', $baris('pkat'), 'pembagi 0 → strip');
        $shk = $baris('skrining_shk');
        $this->assertStringContainsString('Tidak normal 1', $shk);
        $this->assertStringContainsString('0 bayi belum diisi', $shk);
        $air = $baris('air_bersih');
        $this->assertStringContainsString('100,0 %', $air);
        $this->assertStringContainsString('2 anak belum diisi', $air);
        $this->assertStringContainsString('class="km-bar terbalik" data-baris="merokok_keluarga"', $blok);
    }

    public function test_layanan_lingkungan_tanpa_data_menampilkan_empty_state(): void
    {
        $this->anak('Polos', '2024-06-30');

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->getContent();
        $blok = $this->blok($html, 'layanan');

        $this->assertSame(3, substr_count($blok, 'Belum ada data'), 'tiga panel empty-state');
        $this->assertStringContainsString('lengkapi lewat Edit Anak', $blok);
        $this->assertStringNotContainsString('0,0 %', $blok);
        $this->assertStringContainsString('1 anak belum diisi untuk setiap layanan', $blok);
        $this->assertStringContainsString('0 bayi belum diisi untuk setiap skrining', $blok);
        $this->assertStringContainsString('1 anak belum diisi untuk setiap indikator sanitasi', $blok);
    }

    public function test_baris_penandaan_dan_peringatan_saat_belum_ada_anak_bertanda(): void
    {
        // Review Focus #5: hari pertama setelah rilis.
        // Lahir 15 Mar 2023 (33 bln pada akhir 2025): masuk sasaran 0–59 dan 12–59 bln (jadi filter tanda
        // diuji di kartu K4/anak balita juga, bukan hanya 0–72), tetapi di luar kohort Baduta 2025
        // (2023-04-01..2024-03-31). Chip IDL/IBL sengaja tidak memakai tanda (spec 2026-10-02 §6.3 butir 7)
        // dan menampilkan "0,0 %" bagi anak kohortnya; anak di kohort itu akan membuat assertion "0,0 %"
        // di bawah gagal palsu.
        $this->anak('Belum Ditandai', '2023-03-15', ['sasaran_balita_kesmas' => null]);

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->assertOk()->getContent();
        $blok = $this->blok($html, 'penandaan');

        $this->assertStringContainsString('km-penandaan--kosong', $blok);
        $this->assertStringContainsString('Belum ada anak bertanda Sasaran Balita Kesmas', $blok);
        $this->assertStringContainsString('1 belum ditandai', $blok);
        foreach (['spm-balita', 'spm-bayi', 'spm-anak-balita', 'spm-tk'] as $nama) {
            $this->assertStringNotContainsString('0,0 %', $this->blok($html, $nama), "{$nama}: pembagi 0 harus '—'");
        }
    }

    public function test_baris_penandaan_menyebut_jumlah_bertanda(): void
    {
        $this->anak('Bertanda', '2023-06-30');
        $this->anak('Belum', '2023-06-30', ['sasaran_balita_kesmas' => null]);

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->assertOk()->getContent();
        $blok = $this->blok($html, 'penandaan');

        $this->assertStringNotContainsString('km-penandaan--kosong', $blok);
        $this->assertMatchesRegularExpression('/<b class="im-num">1<\/b> dari 2 anak 0–72 bln sudah ditandai/', $blok);
        $this->assertStringNotContainsString('tidak dihitung karena', $blok, 'tanpa anak yang keluar, keterangan itu tak muncul');
    }

    public function test_baris_penandaan_menyebut_anak_bertanda_yang_dikecualikan_karena_keluar(): void
    {
        $this->anak('Aktif', '2023-06-30');
        $this->anak('Pindah', '2023-06-30', ['verif_rt_status' => 'pindah', 'verif_rt_reviu' => 'disetujui']);
        $this->anak('Tidak Aktif', '2023-06-30', ['status' => 0]);

        $html = $this->actingAs($this->admin)->get(route('admin.kesmas.dashboard', ['tahun' => 2025]))->assertOk()->getContent();
        $blok = $this->blok($html, 'penandaan');

        $this->assertMatchesRegularExpression('/<b class="im-num">1<\/b> dari 1 anak 0–72 bln sudah ditandai/', $blok, 'yang keluar tak masuk pembagi');
        $this->assertStringContainsString('2 anak bertanda tidak dihitung karena pindah/meninggal (disetujui RT) atau Tidak Aktif', $blok);
    }
}
