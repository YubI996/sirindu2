<?php

namespace Tests\Unit\Imports;

use App\Imports\Pd3iImport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Menguji pemetaan kolom "Klasifikasi Akhir" (kolom terakhir file impor PD3I)
 * menjadi status_kasus + penyakit_terkonfirmasi.
 */
class Pd3iImportKlasifikasiTest extends TestCase
{
    private function resolve(?string $value): array
    {
        $method = new ReflectionMethod(Pd3iImport::class, 'resolveKlasifikasi');
        $method->setAccessible(true);
        return $method->invoke(null, $value);
    }

    public function test_nama_penyakit_jadi_confirmed(): void
    {
        $this->assertSame(['status' => 'confirmed', 'penyakit' => 'Campak'], $this->resolve('Campak'));
        $this->assertSame(['status' => 'confirmed', 'penyakit' => 'Rubella'], $this->resolve('Rubella'));
    }

    public function test_discarded(): void
    {
        $this->assertSame(['status' => 'discarded', 'penyakit' => null], $this->resolve('Discarded'));
        $this->assertSame(['status' => 'discarded', 'penyakit' => null], $this->resolve('Bukan Kasus'));
    }

    public function test_kosong_dan_na_status_null_agar_tak_menimpa(): void
    {
        // status null = sinyal "tidak ada info" → pemanggil tidak menimpa status lama
        $this->assertSame(['status' => null, 'penyakit' => null], $this->resolve(''));
        $this->assertSame(['status' => null, 'penyakit' => null], $this->resolve(null));
        $this->assertSame(['status' => null, 'penyakit' => null], $this->resolve('#N/A'));
    }

    public function test_nilai_tak_dikenal_status_null(): void
    {
        $this->assertSame(['status' => null, 'penyakit' => null], $this->resolve('Entah Apa'));
    }

    public function test_case_insensitive_dan_spasi(): void
    {
        $this->assertSame(['status' => 'confirmed', 'penyakit' => 'Campak'], $this->resolve('  campak  '));
        $this->assertSame(['status' => 'discarded', 'penyakit' => null], $this->resolve('DISCARDED'));
    }
}
