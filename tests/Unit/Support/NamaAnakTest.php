<?php

namespace Tests\Unit\Support;

use App\Support\NamaAnak;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nama anak wajib: terisi dan memuat minimal satu huruf. Penanda kosong dari Excel/berkas
 * ("0", "-", "#N/A", spasi tak terlihat) tidak boleh lolos sebagai nama.
 */
class NamaAnakTest extends TestCase
{
    #[DataProvider('namaSah')]
    public function test_nama_sah(string $nama): void
    {
        $this->assertTrue(NamaAnak::sah($nama), "'{$nama}' seharusnya nama yang sah");
        $this->assertFalse(NamaAnak::kosong($nama));
    }

    public static function namaSah(): array
    {
        return [
            'biasa'            => ['Ani Wijaya'],
            'huruf kapital'    => ['MUHAMMAD AL-FATIH'],
            'apostrof'         => ["Nur'aini"],
            'ada angka'        => ['Zaid 2'],
            'huruf non-ASCII'  => ['Ånders'],
            'spasi di tepi'    => ['  Budi  '],
        ];
    }

    #[DataProvider('namaTidakSah')]
    public function test_nama_tidak_sah(?string $nama): void
    {
        $this->assertFalse(NamaAnak::sah($nama), var_export($nama, true) . ' bukan nama yang sah');
        $this->assertTrue(NamaAnak::kosong($nama));
    }

    public static function namaTidakSah(): array
    {
        return [
            'null'               => [null],
            'kosong'             => [''],
            'spasi'              => ['   '],
            'nol'                => ['0'],
            'strip'              => ['-'],
            'dua strip'          => ['--'],
            'hanya angka'        => ['12345'],
            'galat Excel N/A'    => ['#N/A'],
            'galat Excel REF'    => ['#REF!'],
            'galat Excel VALUE'  => ['#VALUE!'],
            'spasi tak terlihat' => ["\u{00A0}\u{00A0}"],
        ];
    }
}
