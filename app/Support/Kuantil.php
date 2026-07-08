<?php

namespace App\Support;

/**
 * Pembagian nilai wilayah menjadi tiga kelas (tertil) untuk pewarnaan peta:
 * kelas 0 = terendah (hijau), 1 = tengah (kuning), 2 = tertinggi (merah).
 */
class Kuantil
{
    /**
     * Dua titik potong tertil dari daftar nilai. Daftar kosong → [].
     *
     * @param array<int|float> $nilai
     * @return array{0:float,1:float}|array{}
     */
    public static function ambangTertil(array $nilai): array
    {
        if (empty($nilai)) {
            return [];
        }

        $urut = array_values($nilai);
        sort($urut, SORT_NUMERIC);
        $n = count($urut);

        $iBawah = (int) floor($n / 3);
        $iAtas  = (int) floor(2 * $n / 3);
        // Jaga indeks dalam rentang untuk n kecil.
        $iBawah = min($iBawah, $n - 1);
        $iAtas  = min($iAtas, $n - 1);

        return [(float) $urut[$iBawah], (float) $urut[$iAtas]];
    }

    /**
     * Kelas tertil sebuah nilai terhadap ambang [bawah, atas].
     * Ambang kosong → 0.
     */
    public static function kelas(float $nilai, array $ambang): int
    {
        if (count($ambang) < 2) {
            return 0;
        }
        if ($nilai <= $ambang[0]) {
            return 0;
        }
        if ($nilai <= $ambang[1]) {
            return 1;
        }
        return 2;
    }
}
