<?php

namespace Tests\Feature\Imunisasi;

use App\Models\User;
use App\Services\ImunisasiStatusService;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mengunci perbaikan audit WCAG dasbor imunisasi. Yang diuji hanya markup/semantik yang bisa
 * dipastikan dari HTML server; kontras warna, reflow 320px, dan urutan Tab tetap harus dilihat di browser.
 */
class DasborImunisasiAksesibilitasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
    }

    private function html(): string
    {
        $admin = User::factory()->create(['type' => 1]);

        return $this->actingAs($admin)
            ->get(route('admin.imunisasiDashboard'))
            ->assertOk()
            ->getContent();
    }

    public function test_halaman_punya_h1_tunggal_dan_h2_bagian(): void
    {
        $html = $this->html();

        $this->assertSame(1, preg_match_all('/<h1[\s>]/', $html), 'harus ada tepat satu <h1>');
        $this->assertStringContainsString('<h2>Data sasaran</h2>', $html);
        // "Wilayah" ada di dalam bagian ber-h2 → bukan h2 lagi.
        $this->assertStringNotContainsString('<h2>Wilayah</h2>', $html);
    }

    public function test_tab_hari_memakai_pola_aria_tab(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertMatchesRegularExpression('/id="btnHariIni"[^>]*aria-selected="true"[^>]*aria-controls="tabelHariIni"/', $html);
        $this->assertMatchesRegularExpression('/id="btnBesok"[^>]*aria-selected="false"[^>]*aria-controls="tabelBesok"/', $html);
        $this->assertMatchesRegularExpression('/id="tabelHariIni"[^>]*role="tabpanel"[^>]*aria-labelledby="btnHariIni"/', $html);
    }

    public function test_tab_jenis_imunisasi_bukan_tombol_palsu(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('aria-current="page">Imunisasi Rutin', $html);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*class="im-tab/', $html);
    }

    public function test_tombol_penjelasan_adalah_button_dengan_aria_expanded_dan_nama(): void
    {
        $html = $this->html();

        // Tombol "?" asli, tertutup default, terhubung ke bodinya, bernama jelas.
        $this->assertMatchesRegularExpression(
            '/<button type="button" class="im-help-btn" aria-expanded="false" aria-controls="bantuan-sasaran"\s+aria-label="Penjelasan: Data sasaran"/',
            $html
        );
        $this->assertMatchesRegularExpression('/<div class="im-help-body" id="bantuan-sasaran" hidden>/', $html);
        // Glyph "?" tidak dibaca; tidak ada tooltip hover/atribut title sebagai pembawa informasi.
        $this->assertStringContainsString('<span aria-hidden="true">?</span>', $html);
        $this->assertStringNotContainsString('data-toggle="tooltip"', $html);
    }

    public function test_catatan_metode_inti_tetap_terlihat_walau_detail_dilipat(): void
    {
        $html = $this->html();

        // Kalimat pencegah salah tafsir TIDAK boleh ikut tersembunyi di balik "?".
        $this->assertStringContainsString('tidak serta-merta berarti penurunan kinerja layanan', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<div class="im-help-body"[^>]*>(?:(?!<\/div>).)*serta-merta(?:(?!<\/div>).)*<\/div>/s',
            $html
        );
    }

    public function test_ikon_ligature_disembunyikan_dari_pembaca_layar(): void
    {
        $this->assertStringContainsString('aria-hidden="true">filter_alt</span>', $this->html());
    }

    public function test_istilah_inggris_on_track_diganti(): void
    {
        $this->assertStringNotContainsString('On track', $this->html());
    }

    public function test_wadah_scroll_tabel_bisa_difokus_keyboard(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression('/class="im-scroll" tabindex="0" role="region" aria-label="Tabel rincian per puskesmas"/', $html);
        $this->assertStringContainsString('<caption class="im-sr">Capaian IDL per puskesmas', $html);
    }

    public function test_script_aksesibilitas_dimuat(): void
    {
        $this->assertStringContainsString('js/dasbor-a11y.js', $this->html());
    }

    public function test_chartjs_dipin_versinya(): void
    {
        // Skrip grafik hanya dimuat bila ada data, jadi dicek dari sumber blade.
        $blade = file_get_contents(resource_path('views/admin/imunisasi/dashboard.blade.php'));

        $this->assertStringNotContainsString('npm/chart.js"', $blade, 'Chart.js tanpa versi bisa berubah tanpa pemberitahuan');
        $this->assertMatchesRegularExpression('#npm/chart\.js@4\.\d+\.\d+/#', $blade);
    }

    public function test_canvas_grafik_punya_nama_dan_alternatif_teks(): void
    {
        // Data grafik bergantung isi DB; tes ini mengunci kontraknya lewat sumber blade.
        $blade = file_get_contents(resource_path('views/admin/imunisasi/dashboard.blade.php'));

        foreach (['chartAlasan', 'chartKorelasi'] as $id) {
            $this->assertMatchesRegularExpression('/<canvas id="'.$id.'"[^>]*role="img"[^>]*aria-label="[^"]+"/', $blade, "$id wajib role=img + aria-label");
        }
        // Padanan tabel untuk scatter yang tooltipnya hanya hover.
        $this->assertStringContainsString('Lihat data dalam tabel', $blade);
    }

    public function test_token_warna_kontras_tidak_kembali_ke_nilai_lama(): void
    {
        $css = file_get_contents(public_path('css/dasbor-base.css'));

        // --faint dipakai untuk TEKS: nilai lama L=0.62 hanya 3,6:1 di putih.
        $this->assertDoesNotMatchRegularExpression('/--faint:oklch\(0\.62/', $css);
        // Batas kontrol form lama (L=0.84) hanya 1,6:1 di putih.
        $this->assertDoesNotMatchRegularExpression('/\.im-filter select\{[^}]*border:1px solid oklch\(0\.84/', $css);
    }
}
