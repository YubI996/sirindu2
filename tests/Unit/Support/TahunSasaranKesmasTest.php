<?php

namespace Tests\Unit\Support;

use App\Support\TahunSasaranKesmas;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Rumus Kesmas dari lembar klien (spec 2026-10-02 §5.4): tahun lahir + n, kalender murni. */
class TahunSasaranKesmasTest extends TestCase
{
    public function test_enam_tahap_mengikuti_rumus_tahun_lahir_plus_n(): void
    {
        $this->assertSame([
            'idl'    => ['label' => 'IDL', 'tahun' => 2026],
            '24_bln' => ['label' => 'Sasaran 24 bulan', 'tahun' => 2027],
            'ibl'    => ['label' => 'IBL', 'tahun' => 2028],
            '48_bln' => ['label' => 'Sasaran 48 bulan', 'tahun' => 2029],
            '60_bln' => ['label' => 'Sasaran 60 bulan', 'tahun' => 2030],
            '72_bln' => ['label' => 'Sasaran 72 bulan', 'tahun' => 2031],
        ], TahunSasaranKesmas::coba('2025-05-10')->semua());
    }

    public function test_hanya_tahun_kalender_yang_menentukan_bukan_bulan_lahir(): void
    {
        // Beda sengaja dari KohortImunisasi (cut-off 1 April): anak lahir Januari dan Desember
        // di tahun yang sama mendapat tahun sasaran yang sama.
        $this->assertSame(2026, TahunSasaranKesmas::coba('2025-01-01')->tahun('idl'));
        $this->assertSame(2026, TahunSasaranKesmas::coba('2025-12-31')->tahun('idl'));
        $this->assertSame(2028, TahunSasaranKesmas::coba('2025-01-01')->tahun('ibl'));
    }

    public function test_menerima_datetime_dari_database(): void
    {
        $this->assertSame(2026, TahunSasaranKesmas::coba('2025-03-01 00:00:00')->tahun('idl'));
    }

    public function test_tanggal_tak_sah_menghasilkan_null(): void
    {
        foreach ([null, '', '   ', '0000-00-00', '2025-02-30', '10/05/2025', 'bukan tanggal', '1899-12-31'] as $tgl) {
            $this->assertNull(TahunSasaranKesmas::coba($tgl), var_export($tgl, true));
        }
    }

    public function test_tahap_asing_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TahunSasaranKesmas::coba('2025-05-10')->tahun('ibl2');
    }

    public function test_urutan_tahap_tetap(): void
    {
        $this->assertSame(['idl', '24_bln', 'ibl', '48_bln', '60_bln', '72_bln'], array_keys(TahunSasaranKesmas::TAHAP));
    }
}
