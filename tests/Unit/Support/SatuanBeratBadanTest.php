<?php

namespace Tests\Unit\Support;

use App\Support\SatuanBeratBadan as S;
use PHPUnit\Framework\TestCase;

/** Satuan input BB form pengukuran (spec 2026-10-02 §5.5): < 2 bulan gram, selain itu kg. */
class SatuanBeratBadanTest extends TestCase
{
    public function test_batas_gram_dua_bulan_tanpa_luapan_akhir_bulan(): void
    {
        $this->assertSame('2026-03-15', S::batasGram('2026-01-15'));
        $this->assertSame('2027-02-28', S::batasGram('2026-12-31'), '31 Des + 2 bln = akhir Februari, bukan 3 Maret');
        $this->assertSame('2028-02-29', S::batasGram('2027-12-31'), 'Februari kabisat');
        $this->assertSame('2024-04-29', S::batasGram('2024-02-29'));
        $this->assertSame('2026-02-28', S::batasGram('2025-12-30'));
        $this->assertSame('2025-03-10', S::batasGram('2025-01-10 00:00:00'), 'datetime dari DB diterima');
    }

    public function test_sehari_sebelum_batas_gram_tepat_batas_kg(): void
    {
        $this->assertSame(S::GRAM, S::untuk('2026-12-31', '2027-02-27'));
        $this->assertSame(S::KG, S::untuk('2026-12-31', '2027-02-28'), '"< 2 bulan" = belum genap 2 bulan');
        $this->assertSame(S::GRAM, S::untuk('2025-01-10', '2025-01-10'), 'hari lahir');
        $this->assertSame(S::KG, S::untuk('2025-01-10', '2025-06-10'));
    }

    public function test_tanggal_tak_sah_jatuh_ke_kg_perilaku_lama(): void
    {
        $this->assertSame('', S::batasGram('0000-00-00'));
        $this->assertSame('', S::batasGram(null));
        $this->assertSame(S::KG, S::untuk('0000-00-00', '2025-02-10'));
        $this->assertSame(S::KG, S::untuk('2025-01-10', ''));
        $this->assertSame(S::KG, S::untuk('2025-01-10', '10/02/2025'));
    }

    public function test_konversi_dan_tampilan(): void
    {
        $this->assertSame(3.25, S::keKg(3250, S::GRAM));
        $this->assertSame(7.5, S::keKg(7.5, S::KG));
        $this->assertSame(3200, S::untukTampil(3.2000000476837, S::GRAM), 'sisa FLOAT MySQL dibuang');
        $this->assertSame(3255, S::untukTampil(3.255, S::GRAM));
        $this->assertSame(7.5, S::untukTampil(7.5, S::KG));
        $this->assertSame('gram', S::label(S::GRAM));
        $this->assertSame('kg', S::label(S::KG));
    }

    public function test_rentang_tidak_beririsan_sehingga_salah_satuan_pasti_ditolak(): void
    {
        $this->assertTrue(S::dalamRentang(300, S::GRAM));
        $this->assertTrue(S::dalamRentang(8000, S::GRAM));
        $this->assertFalse(S::dalamRentang(3.25, S::GRAM), 'kg diketik di kolom gram');
        $this->assertFalse(S::dalamRentang(8001, S::GRAM));
        $this->assertTrue(S::dalamRentang(1, S::KG));
        $this->assertTrue(S::dalamRentang(150, S::KG));
        $this->assertFalse(S::dalamRentang(3250, S::KG), 'gram diketik di kolom kg');
        $this->assertFalse(S::dalamRentang(0, S::KG));
        $this->assertGreaterThan(S::RENTANG[S::KG][1], S::RENTANG[S::GRAM][0]);
    }

    public function test_sama_dalam_toleransi_setengah_gram(): void
    {
        $this->assertTrue(S::sama(3.25, 3.2500004));
        $this->assertTrue(S::sama(0.0, 0.0));
        $this->assertFalse(S::sama(3.25, 3.251));
    }
}
