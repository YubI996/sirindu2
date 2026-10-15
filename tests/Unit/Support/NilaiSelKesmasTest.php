<?php

namespace Tests\Unit\Support;

use App\Support\NilaiSelKesmas as N;
use PHPUnit\Framework\TestCase;

class NilaiSelKesmasTest extends TestCase
{
    public function test_kosong_mengenali_null_string_kosong_dan_spasi(): void
    {
        $this->assertTrue(N::kosong(null));
        $this->assertTrue(N::kosong(''));
        $this->assertTrue(N::kosong('   '));
        $this->assertFalse(N::kosong(0));
        $this->assertFalse(N::kosong('0'));
    }

    public function test_ya_tidak_tanpa_beda_huruf(): void
    {
        foreach (['Ya', 'YA', 'y', '1', 1, 'true'] as $v) {
            $this->assertSame(1, N::yaTidak($v), var_export($v, true));
        }
        foreach (['Tidak', 'tidak', 'T', '0', 0, 'false'] as $v) {
            $this->assertSame(0, N::yaTidak($v), var_export($v, true));
        }
        $this->assertNull(N::yaTidak('mungkin'));
    }

    public function test_pilihan_dicocokkan_tanpa_beda_huruf_dan_disimpan_dengan_ejaan_daftar(): void
    {
        $this->assertSame('Bidan', N::pilihan(' bidan ', ['Dokter Umum', 'Bidan']));
        $this->assertNull(N::pilihan('Dukun', ['Dokter Umum', 'Bidan']));
    }

    public function test_pilihan_berkunci_menerima_kunci_atau_label(): void
    {
        $skrining = ['belum' => 'Belum', 'normal' => 'Normal', 'tidak_normal' => 'Tidak normal'];
        $this->assertSame('tidak_normal', N::pilihanBerkunci('Tidak Normal', $skrining));
        $this->assertSame('tidak_normal', N::pilihanBerkunci('tidak_normal', $skrining));
        $this->assertNull(N::pilihanBerkunci('abnormal', $skrining));
    }

    public function test_angka_menerima_koma_desimal_dan_sel_angka(): void
    {
        $this->assertSame(3.25, N::angka('3,25'));
        $this->assertSame(10.5, N::angka(10.5));
        $this->assertSame(3250.0, N::angka(3250));
        $this->assertSame(3.25, N::angka('3.250'));   // titik dibaca desimal — rentang gram yang menolaknya (KesmasImportKunjunganTest)
        $this->assertNull(N::angka('sepuluh'));
    }

    public function test_nik_dari_sel_angka_excel_tidak_jadi_notasi_ilmiah(): void
    {
        $this->assertSame('6474010101230001', N::nik(6474010101230001.0));
        $this->assertSame('6474010101230001', N::nik(6474010101230001));
        $this->assertSame('6474010101230001', N::nik(" '6474010101230001 "));
        $this->assertSame('', N::nik(null));
        $this->assertSame('', N::nik('-'));
    }

    public function test_jk_dan_posisi(): void
    {
        $this->assertSame('L', N::jk('l'));
        $this->assertSame('P', N::jk('Perempuan'));
        $this->assertNull(N::jk('X'));
        $this->assertSame('H', N::posisi('Berdiri'));
        $this->assertSame('L', N::posisi('terlentang'));
        $this->assertNull(N::posisi('duduk'));
        $this->assertNull(N::posisi(''));
    }
}
