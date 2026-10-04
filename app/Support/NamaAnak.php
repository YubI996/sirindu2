<?php

namespace App\Support;

/**
 * Nama anak yang sah untuk import: terisi dan memuat minimal satu huruf.
 *
 * Berkas Excel/CSV sering memakai penanda kosong ("0", "-", "#N/A", spasi tak terlihat) di sel nama.
 * Penanda itu bukan nama dan tak boleh dipakai mencocokkan maupun membuat anak.
 */
final class NamaAnak
{
    public static function sah(?string $nama): bool
    {
        $nama = trim((string) $nama);

        if ($nama === '' || $nama[0] === '#') {
            return false;   // '#N/A', '#REF!', '#VALUE!' = galat rumus Excel
        }

        return preg_match('/\p{L}/u', $nama) === 1;
    }

    public static function kosong(?string $nama): bool
    {
        return ! self::sah($nama);
    }
}
