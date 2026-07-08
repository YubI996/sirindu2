<?php

namespace Tests\Unit\Support;

use App\Support\Kuantil;
use PHPUnit\Framework\TestCase;

class KuantilTest extends TestCase
{
    public function test_ambang_tertil_membagi_sembilan_nilai_jadi_tiga(): void
    {
        // 1..9: tertil di indeks 3 dan 6 (nilai 4 dan 7).
        $this->assertSame([4.0, 7.0], Kuantil::ambangTertil([9, 1, 8, 2, 7, 3, 6, 4, 5]));
    }

    public function test_ambang_tertil_daftar_kosong_kembalikan_kosong(): void
    {
        $this->assertSame([], Kuantil::ambangTertil([]));
    }

    public function test_kelas_membagi_hijau_kuning_merah(): void
    {
        $ambang = [4.0, 7.0];
        $this->assertSame(0, Kuantil::kelas(2, $ambang));   // <= batas bawah → hijau
        $this->assertSame(0, Kuantil::kelas(4, $ambang));   // tepat batas bawah → hijau
        $this->assertSame(1, Kuantil::kelas(5, $ambang));   // tengah → kuning
        $this->assertSame(1, Kuantil::kelas(7, $ambang));   // tepat batas atas → kuning
        $this->assertSame(2, Kuantil::kelas(8, $ambang));   // > batas atas → merah
    }

    public function test_kelas_ambang_kosong_selalu_nol(): void
    {
        $this->assertSame(0, Kuantil::kelas(99, []));
    }

    public function test_ambang_tertil_nilai_seri_tidak_error(): void
    {
        // Semua sama → kedua batas sama; kelas apa pun jadi 0/1 tapi tidak error.
        $this->assertSame([5.0, 5.0], Kuantil::ambangTertil([5, 5, 5, 5]));
    }
}
