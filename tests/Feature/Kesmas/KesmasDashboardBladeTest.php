<?php
// tests/Feature/Kesmas/KesmasDashboardBladeTest.php

namespace Tests\Feature\Kesmas;

use Tests\TestCase;

/**
 * Mengunci jebakan yang pernah memakan waktu di proyek ini (lihat CLAUDE.md):
 * @section satu baris tanpa spasi, atribut Bootstrap 5, jQuery :hidden pada <option>,
 * label tanpa pasangan id, dan wilayah aria-live untuk tabel yang dimuat JS.
 */
class KesmasDashboardBladeTest extends TestCase
{
    private const VIEWS = [
        'resources/views/admin/kesmas/dashboard.blade.php',
        'resources/views/admin/kesmas/partials/_filter.blade.php',
        'resources/views/admin/kesmas/partials/_registri.blade.php',
        'resources/views/admin/kesmas/partials/_spm.blade.php',
        'resources/views/admin/kesmas/partials/_sdidtk-ckg-idl.blade.php',
        'resources/views/admin/kesmas/partials/_layanan.blade.php',
    ];

    private function semua(): string
    {
        return implode("\n", array_map(fn ($v) => file_get_contents(base_path($v)), self::VIEWS));
    }

    public function test_section_satu_baris_selalu_berspasi(): void
    {
        $this->assertDoesNotMatchRegularExpression("/@section\\('[a-z-]+'\\)\\S.*@endsection/", $this->semua(),
            "@section('x')isi@endsection tanpa spasi membuat @endsection tidak diparse (lihat CLAUDE.md).");
    }

    public function test_tanpa_atribut_bootstrap_5_dan_jquery_hidden_pada_option(): void
    {
        $html = $this->semua();
        $this->assertStringNotContainsString('data-bs-', $html, 'Proyek memakai Bootstrap 4 (data-toggle).');
        $this->assertDoesNotMatchRegularExpression("/\.is\('(:hidden|:visible)'\)|option:(hidden|visible)/", $html, '<option> tidak punya box model — cek validitas dari data-kec.');
    }

    public function test_setiap_label_for_punya_id_pasangan(): void
    {
        $html = $this->semua();
        preg_match_all('/<label for="([^"]+)"/', $html, $labels);
        $this->assertNotEmpty($labels[1]);
        foreach ($labels[1] as $id) {
            $this->assertMatchesRegularExpression('/\bid="' . preg_quote($id, '/') . '"/', $html, "label for=\"{$id}\" tanpa kontrol ber-id yang sama");
        }
        foreach (['filterTahun', 'filterPeriode', 'filterKec', 'filterKel', 'filterRt', 'filterPos', 'filterPkm', 'registriCari', 'registriGizi'] as $id) {
            $this->assertContains($id, $labels[1], "Kontrol #{$id} harus punya <label for>");
        }
    }

    public function test_tabel_registri_aria_live_dan_tanpa_required(): void
    {
        $html = $this->semua();
        $this->assertStringContainsString('id="registriBody" aria-live="polite"', $html);
        $this->assertStringNotContainsString(' required', $html, 'Filter GET tidak boleh punya required (tak ada partial validasi accordion di sini).');
    }

    public function test_blok_ditutup_komentar_penanda(): void
    {
        $html = $this->semua();
        foreach (['spm-balita', 'spm-bayi', 'spm-anak-balita', 'spm-tk', 'sdidtk', 'ckg', 'idl', 'layanan', 'registri'] as $nama) {
            $this->assertStringContainsString('data-blok="' . $nama . '"', $html);
            $this->assertStringContainsString('<!-- /' . $nama . ' -->', $html, "Blok {$nama} harus ditutup komentar penanda (dipakai tes blok())");
        }
    }
}
