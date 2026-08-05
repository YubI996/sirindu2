<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Samakan nama puskesmas di master dengan bentuk yang dipakai seluruh data kasus.
 *
 * Master memakai angka Romawi ("Bontang Utara I"), sedangkan semua data surveilans
 * (instansi_pelapor & wilker_puskesmas) memakai angka Arab ("Bontang Utara 1").
 * Akibatnya kasus yang diinput user puskesmas (instansi_pelapor = nama master, Romawi)
 * memecah baris dasbor "Per Faskes Pelapor" yang dikelompokkan dari teks mentah.
 *
 * Scoping tetap aman: WilkerPuskesmas::normalizeName mengubah Romawi & Arab ke bentuk
 * sama, jadi rename ini tidak mengubah hasil pencocokan wilker.
 */
return new class extends Migration
{
    private const MAP = [
        'Bontang Utara I'    => 'Bontang Utara 1',
        'Bontang Utara II'   => 'Bontang Utara 2',
        'Bontang Selatan I'  => 'Bontang Selatan 1',
        'Bontang Selatan II' => 'Bontang Selatan 2',
    ];

    public function up(): void
    {
        foreach (self::MAP as $roman => $arab) {
            DB::table('puskesmas')->where('name', $roman)->update(['name' => $arab]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $roman => $arab) {
            DB::table('puskesmas')->where('name', $arab)->update(['name' => $roman]);
        }
    }
};
