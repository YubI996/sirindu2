<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    #[DataProvider('posisiProvider')]
    public function test_normalisasi_posisi_memetakan_varian_ke_kanonik(string $input, string $expected): void
    {
        $this->assertSame($expected, normalisasi_posisi($input));
    }

    public static function posisiProvider(): array
    {
        return [
            'huruf H'        => ['H', 'H'],
            'huruf h kecil'  => ['h', 'H'],
            'Bb template'    => ['Bb', 'H'],
            'berdiri'        => ['berdiri', 'H'],
            'BERDIRI kapital'=> ['BERDIRI', 'H'],
            'tinggi'         => ['tinggi', 'H'],
            'angka 2'        => ['2', 'H'],
            'huruf L'        => ['L', 'L'],
            'terlentang'     => ['terlentang', 'L'],
            'berbaring'      => ['berbaring', 'L'],
            'panjang'        => ['panjang', 'L'],
            'angka 1'        => ['1', 'L'],
            'spasi dipangkas'=> ['  berdiri  ', 'H'],
        ];
    }

    public function test_normalisasi_posisi_default_L_untuk_kosong_atau_tak_dikenali(): void
    {
        $this->assertSame('L', normalisasi_posisi(''));
        $this->assertSame('L', normalisasi_posisi(null));
        $this->assertSame('L', normalisasi_posisi('xyz'));
    }

    public function test_usia_bulan_menghitung_selisih_bulan_penuh(): void
    {
        $this->assertSame(12, usia_bulan('2020-01-15', '2021-01-15'));
        $this->assertSame(0, usia_bulan('2024-01-01', '2024-01-20'));
        $this->assertSame(34, usia_bulan('2021-03-03', '2024-01-20'));
    }

    public function test_usia_bulan_null_untuk_input_tidak_valid(): void
    {
        $this->assertNull(usia_bulan(null, '2024-01-01'));
        $this->assertNull(usia_bulan('2024-01-01', null));
        $this->assertNull(usia_bulan('0000-00-00', '2024-01-01'));
        // Tanggal acuan mendahului tanggal lahir = tidak masuk akal.
        $this->assertNull(usia_bulan('2024-06-01', '2024-01-01'));
    }
}
