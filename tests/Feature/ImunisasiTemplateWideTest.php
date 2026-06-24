<?php

namespace Tests\Feature;

use Tests\TestCase;

class ImunisasiTemplateWideTest extends TestCase
{
    public function test_template_imunisasi_header_wide(): void
    {
        $path = public_path('templates/template_imunisasi.csv');
        $this->assertFileExists($path);

        $lines  = preg_split('/\R/', (string) file_get_contents($path));
        $header = collect($lines)->first(fn ($l) => trim($l) !== '' && !str_starts_with(trim($l), '#'));

        $this->assertIsString($header);
        $this->assertStringContainsString('nik_anak', $header);
        $this->assertStringContainsString('HB0', $header);
        $this->assertStringContainsString('alasan_tidak_imunisasi', $header);
        // Format long lama tidak boleh ada di header wide.
        $this->assertStringNotContainsString('kode_vaksin', $header);
    }
}
