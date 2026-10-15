<?php

namespace App\Support;

/**
 * Pengubah sel berkas Import Data Kesmas jadi nilai kolom (spec 2026-10-15-import-kesmas-design.md §3).
 * Murni: tanpa DB, config(), atau now() — daftar pilihan diteruskan pemanggil.
 * Pengubah mengembalikan null untuk sel yang TAK SAH; pemanggil memeriksa kosong() lebih dulu.
 */
final class NilaiSelKesmas
{
    private const YA = ['ya', 'y', '1', 'true'];
    private const TIDAK = ['tidak', 't', '0', 'false'];
    private const BERDIRI = ['h', 'berdiri', 'tinggi', 'tinggi badan', 'tb'];
    private const TERLENTANG = ['l', 'terlentang', 'telentang', 'berbaring', 'panjang', 'panjang badan', 'pb'];

    public static function kosong(mixed $sel): bool
    {
        return $sel === null || (is_string($sel) && trim($sel) === '');
    }

    public static function teks(mixed $sel): string
    {
        return trim((string) $sel);
    }

    public static function yaTidak(mixed $sel): ?int
    {
        if (is_bool($sel)) {
            return (int) $sel;
        }
        $v = mb_strtolower(self::teks($sel));

        return in_array($v, self::YA, true) ? 1 : (in_array($v, self::TIDAK, true) ? 0 : null);
    }

    /** @param string[] $daftar */
    public static function pilihan(mixed $sel, array $daftar): ?string
    {
        $v = mb_strtolower(self::teks($sel));
        foreach ($daftar as $opsi) {
            if (mb_strtolower($opsi) === $v) {
                return $opsi;
            }
        }

        return null;
    }

    /** @param array<string,string> $kunciLabel kunci => label, mis. config('kesmas.skrining') */
    public static function pilihanBerkunci(mixed $sel, array $kunciLabel): ?string
    {
        $v = mb_strtolower(self::teks($sel));
        foreach ($kunciLabel as $kunci => $label) {
            if ($v === mb_strtolower($kunci) || $v === mb_strtolower($label)) {
                return $kunci;
            }
        }

        return null;
    }

    /** Koma desimal diterima ('3,25'). Titik selalu desimal — '3.250' = 3,25. */
    public static function angka(mixed $sel): ?float
    {
        if (is_int($sel) || is_float($sel)) {
            return (float) $sel;
        }
        $v = str_replace(' ', '', self::teks($sel));
        if (preg_match('/^-?\d+,\d+$/', $v)) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    /** NIK dari sel: sel angka Excel ditulis ulang tanpa notasi ilmiah, selain digit dibuang. */
    public static function nik(mixed $sel): string
    {
        if (is_int($sel) || is_float($sel)) {
            return sprintf('%.0f', $sel);
        }

        return preg_replace('/\D/', '', self::teks($sel)) ?? '';
    }

    public static function jk(mixed $sel): ?string
    {
        $v = mb_strtolower(self::teks($sel));

        return match (true) {
            in_array($v, ['l', 'laki-laki', 'laki laki', '1'], true) => 'L',
            in_array($v, ['p', 'perempuan', '2'], true) => 'P',
            default => null,
        };
    }

    /** 'H' = berdiri, 'L' = terlentang (kanonik z_score()). Kosong/tak dikenal → null, BUKAN default 'L'. */
    public static function posisi(mixed $sel): ?string
    {
        $v = mb_strtolower(self::teks($sel));

        return in_array($v, self::BERDIRI, true) ? 'H' : (in_array($v, self::TERLENTANG, true) ? 'L' : null);
    }
}
