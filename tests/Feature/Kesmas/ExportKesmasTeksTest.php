<?php

namespace Tests\Feature\Kesmas;

use App\Exports\KesmasExport;
use Tests\TestCase;

/** Tipe sel hasil KesmasExport::rapikan() — murni, tanpa DB. */
class ExportKesmasTeksTest extends TestCase
{
    public function test_kolom_teks_menjadi_string_termasuk_angka_dan_nol(): void
    {
        $baris = KesmasExport::rapikan(['6474010101250001', 0, '=1+1', null, 7], []);

        $this->assertSame(['6474010101250001', '0', '=1+1', null, '7'], $baris);
    }

    public function test_kolom_angka_menjadi_numerik_dan_nol_tetap_nol(): void
    {
        $angka = KesmasExport::indeksKolom(['A', 'C']); // indeks 0 dan 2

        $baris = KesmasExport::rapikan(['3.20', 'x', '0.00', '2026', 5], $angka);

        $this->assertSame(3.2, $baris[0]);
        $this->assertSame('x', $baris[1], 'bukan kolom angka → string');
        $this->assertSame(0.0, $baris[2], 'nol tetap angka, bukan kosong');
        $this->assertSame('2026', $baris[3]);
    }

    public function test_nilai_bukan_angka_di_kolom_angka_tetap_string_bukan_nol(): void
    {
        $baris = KesmasExport::rapikan(['abc', null], KesmasExport::indeksKolom(['A', 'B']));

        $this->assertSame('abc', $baris[0]);
        $this->assertNull($baris[1]);
    }
}
