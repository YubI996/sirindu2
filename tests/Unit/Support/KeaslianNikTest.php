<?php

namespace Tests\Unit\Support;

use App\Support\KeaslianNik;
use PHPUnit\Framework\TestCase;

/**
 * Skor "seberapa real" sebuah NIK untuk anak tertentu. Dipakai importer untuk memutuskan NIK mana
 * yang bertahan saat dua baris ternyata anak yang sama: yang lebih real menggantikan yang lain.
 *
 * Petunjuk terkuat: bagian tanggal lahir di dalam NIK (DDMMYY, perempuan DD+40) harus cocok dengan
 * tanggal lahir anak. NIK ibu yang terselip di kolom NIK anak punya tanggal lahir ibu, jadi kalah.
 */
class KeaslianNikTest extends TestCase
{
    // Anak perempuan lahir 10-03-2021 -> bagian tanggal "500321" (10+40, 03, 21)
    private const SESUAI_P   = '6474015003210001';
    // NIK ibu (perempuan lahir 20-03-1987 -> "600387") yang terselip di kolom anak
    private const NIK_IBU    = '6474016003870006';
    private const DUMMY      = '6474005003219001';   // digit ke-13 = 9
    private const POTONGAN   = '647401500321000';    // 15 digit

    public function test_nik_yang_tanggal_lahirnya_cocok_paling_real(): void
    {
        $sesuai = KeaslianNik::skor(self::SESUAI_P, '2021-03-10', 2);

        $this->assertGreaterThan(KeaslianNik::skor(self::NIK_IBU, '2021-03-10', 2), $sesuai, 'NIK ibu kalah dari NIK anak sendiri');
        $this->assertSame(KeaslianNik::SESUAI, $sesuai);
        $this->assertSame(KeaslianNik::TAK_SESUAI, KeaslianNik::skor(self::NIK_IBU, '2021-03-10', 2));
    }

    public function test_urutan_keaslian_sesuai_lebih_tinggi_dari_tak_sesuai_potongan_dummy_kosong(): void
    {
        $skor = fn (?string $nik) => KeaslianNik::skor($nik, '2021-03-10', 2);

        $this->assertGreaterThan($skor(self::NIK_IBU), $skor(self::SESUAI_P));
        $this->assertGreaterThan($skor(self::POTONGAN), $skor(self::NIK_IBU), '16 digit tak sesuai tetap lebih real dari 15 digit');
        $this->assertGreaterThan($skor(self::DUMMY), $skor(self::POTONGAN), '15 digit lebih real dari NIK dummy buatan sistem');
        $this->assertGreaterThan($skor(''), $skor(self::DUMMY));
    }

    public function test_laki_laki_memakai_tanggal_apa_adanya_bukan_plus_40(): void
    {
        // Laki-laki lahir 10-03-2021 -> "100321"
        $this->assertSame(KeaslianNik::SESUAI, KeaslianNik::skor('6474011003210001', '2021-03-10', 1));
        // NIK yang sama untuk anak perempuan tidak cocok (harusnya 50...)
        $this->assertSame(KeaslianNik::TAK_SESUAI, KeaslianNik::skor('6474011003210001', '2021-03-10', 2));
    }

    public function test_tanpa_pembanding_nik_16_digit_bernilai_netral_di_antara_tak_sesuai_dan_sesuai(): void
    {
        $skor = KeaslianNik::skor(self::SESUAI_P, null, null);

        $this->assertSame(KeaslianNik::TANPA_PEMBANDING, $skor);
        $this->assertGreaterThan(KeaslianNik::TAK_SESUAI, $skor);
        $this->assertLessThan(KeaslianNik::SESUAI, $skor);
    }

    public function test_bukan_angka_atau_kosong_nol(): void
    {
        $this->assertSame(0, KeaslianNik::skor(null, '2021-03-10', 2));
        $this->assertSame(0, KeaslianNik::skor('', '2021-03-10', 2));
        $this->assertSame(0, KeaslianNik::skor('64740150032100AB', '2021-03-10', 2));
    }

    public function test_bulan_tidak_masuk_akal_dianggap_tak_sesuai(): void
    {
        // bagian tanggal "501321" -> bulan 13 mustahil
        $this->assertSame(KeaslianNik::TAK_SESUAI, KeaslianNik::skor('6474015013210001', '2021-03-10', 2));
    }
}
