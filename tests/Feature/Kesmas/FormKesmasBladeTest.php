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
    /** Partial yang ada; Task 6 menambahkan 'form-layanan-kesmas'. */
    private const PARTIAL = ['form-kesmas', 'form-riwayat-lahir'];

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
