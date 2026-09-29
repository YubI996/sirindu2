<?php

namespace App\Exceptions;

/**
 * Berkas memuat tanggal yang masih bisa dua arti (05/02/2020) dan petugas memilih
 * "Otomatis". Menebak berarti menyimpan tanggal yang salah tanpa jejak, jadi import
 * dihentikan sebelum baris mana pun masuk — petugas memilih formatnya lalu mengulang.
 */
class FormatTanggalAmbigu extends \RuntimeException
{
    public function __construct(?string $contoh = null)
    {
        $nilai = $contoh ? "\"{$contoh}\"" : 'seperti "05/02/2020"';

        parent::__construct(
            "Format tanggal berkas tidak bisa dipastikan: nilai {$nilai} bisa berarti "
            .'hari/bulan maupun bulan/hari. Ulangi import dan pilih "Hari/Bulan/Tahun" '
            .'atau "Bulan/Hari/Tahun" sesuai isi berkas.'
        );
    }
}
