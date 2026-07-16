<?php

namespace Tests\Unit\Traits;

use App\Traits\ResolvesRumahSakit;
use PHPUnit\Framework\TestCase;

/**
 * Menguji pencocokan nama faskes pelapor (instansi_pelapor) ke master Rumah Sakit.
 *
 * Fokus: normalisasi token ("RSUD Taman Husada" == "RSUD Taman Husada Bontang")
 * TANPA fuzzy longgar — nama RS yang tak ada di master harus null, bukan tertaut
 * ke RS lain (false-positive = kebocoran scoping data antar-RS).
 */
class ResolvesRumahSakitTest extends TestCase
{
    /**
     * Harness: kelas anonim yang memakai trait, dengan cache di-set manual
     * agar tak butuh database.
     */
    private function harness(): object
    {
        return new class {
            use ResolvesRumahSakit;

            public function __construct()
            {
                // Master RS yang SUDAH dikoreksi ke realita Bontang:
                // id 3 = Amalia, id 5 = Badak LNG (Siloam/Pertamina tidak nyata).
                $this->rsCache = $this->buildRsCache([
                    1 => 'RSUD Taman Husada Bontang',
                    2 => 'RS Pupuk Kaltim',
                    3 => 'RS Amalia',
                    4 => 'RS Islam Bontang',
                    5 => 'RS Badak LNG',
                ]);
            }

            public function normalize(string $name): string
            {
                return $this->normalizeRs($name);
            }

            public function resolve(?string $name): ?int
            {
                return $this->resolveRumahSakit($name);
            }
        };
    }

    public function test_exact_match(): void
    {
        $h = $this->harness();
        $this->assertSame(4, $h->resolve('RS Islam Bontang'));
        $this->assertSame(2, $h->resolve('RS Pupuk Kaltim'));
    }

    public function test_normalisasi_suffix_bontang(): void
    {
        // Data pakai "RSUD Taman Husada", master "RSUD Taman Husada Bontang"
        $h = $this->harness();
        $this->assertSame(1, $h->resolve('RSUD Taman Husada'));
    }

    public function test_amalia_dan_badak_cocok(): void
    {
        $h = $this->harness();
        $this->assertSame(3, $h->resolve('RS Amalia'));
        // Urutan token beda: data "RS LNG Badak" vs master "RS Badak LNG"
        $this->assertSame(5, $h->resolve('RS LNG Badak'));
    }

    public function test_siloam_pertamina_tidak_nyata_null(): void
    {
        // Siloam/Pertamina bukan RS nyata di Bontang → tak ada di master → null
        $h = $this->harness();
        $this->assertNull($h->resolve('RS Siloam Bontang'));
        $this->assertNull($h->resolve('RS Pertamina Bontang'));
    }

    public function test_puskesmas_bukan_rs_null(): void
    {
        $h = $this->harness();
        $this->assertNull($h->resolve('Bontang Utara 2'));
        $this->assertNull($h->resolve('Bontang Lestari'));
    }

    public function test_kosong_null(): void
    {
        $h = $this->harness();
        $this->assertNull($h->resolve(''));
        $this->assertNull($h->resolve(null));
        $this->assertNull($h->resolve('   '));
    }

    public function test_case_insensitive_dan_spasi(): void
    {
        $h = $this->harness();
        $this->assertSame(4, $h->resolve('  rs   islam   bontang '));
    }
}
