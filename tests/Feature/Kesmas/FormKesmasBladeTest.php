<?php

namespace Tests\Feature\Kesmas;

use Tests\TestCase;

/**
 * Mengunci pola partial form Kesmas (spec §3.1): di-include di form yang benar; TIDAK ada
 * `required`/`min`/`max` di dalam kartu collapse (panel tertutup tak bisa difokus → submit
 * mati senyap); Bootstrap 4 (`data-toggle`); tanpa `@section('x')isi@endsection` tanpa spasi.
 */
class FormKesmasBladeTest extends TestCase
{
    private const PARTIAL = ['form-kesmas', 'form-riwayat-lahir', 'form-layanan-kesmas'];

    private function sumber(string $relatif): string
    {
        return file_get_contents(resource_path("views/admin/anak/$relatif.blade.php"));
    }

    public function test_partial_anak_diinclude_di_create_dan_edit(): void
    {
        foreach (['create', 'edit'] as $view) {
            $src = $this->sumber($view);
            $this->assertStringContainsString("admin.anak.partials.form-kesmas", $src, "$view tidak meng-include form-kesmas");
            $this->assertStringContainsString("admin.anak.partials.form-riwayat-lahir", $src, "$view tidak meng-include form-riwayat-lahir");
        }
    }

    public function test_partial_layanan_diinclude_di_tambah_pengukuran_dan_edit_per_kunjungan(): void
    {
        $this->assertStringContainsString('admin.anak.partials.form-layanan-kesmas', $this->sumber('data-anak'));
        $this->assertStringContainsString('admin.anak.partials.form-layanan-kesmas', $this->sumber('edit'));
        $this->assertStringNotContainsString('form-layanan-kesmas', $this->sumber('create'));
        // edit merender satu form per kunjungan → awalan id harus dari id kunjungan
        $pola = "/form-layanan-kesmas',\s*\['data' => \\\$data,\s*'p' => 'k'\s*\.\s*\\\$data->id\s*\.\s*'_'\]/";
        $this->assertMatchesRegularExpression($pola, $this->sumber('edit'));
    }

    public function test_partial_layanan_memakai_hidden_dan_checkbox_berpasangan(): void
    {
        $src = $this->sumber('partials/form-layanan-kesmas');
        $this->assertStringContainsString('<input type="hidden" name="{{ $f }}" value="0">', $src);
        $this->assertStringContainsString('type="checkbox" name="{{ $f }}" id="{{ $p }}{{ $f }}" value="1"', $src);
    }

    public function test_partial_tanpa_required_dan_memakai_bootstrap4(): void
    {
        foreach (self::PARTIAL as $p) {
            // komentar Blade dibuang dulu agar kata di dalam komentar tidak ikut terdeteksi
            $src = preg_replace('/\{\{--.*?--\}\}/s', '', $this->sumber("partials/$p"));
            $this->assertDoesNotMatchRegularExpression('/\brequired\b/', $src, "$p memuat `required` di dalam kartu collapse");
            $this->assertDoesNotMatchRegularExpression('/\b(min|max)="/', $src, "$p memuat min/max HTML yang memblokir submit senyap");
            $this->assertStringNotContainsString('data-bs-toggle', $src, "$p memakai atribut Bootstrap 5");
            $this->assertStringContainsString('data-toggle="collapse"', $src, "$p bukan kartu collapse");
            $this->assertDoesNotMatchRegularExpression("/@section\('[a-z-]+'\)\S.*@endsection/", $src);
        }
    }
}
