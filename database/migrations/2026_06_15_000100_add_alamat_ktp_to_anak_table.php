<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket A — dua alamat sasaran.
 *
 * Kolom terstruktur (id_kec/id_kel/id_rt/id_posyandu/id_puskesmas + teks `alamat`)
 * = alamat DOMISILI (operasional, dipakai algoritma). Kolom baru `alamat_ktp`
 * = teks bebas alamat KTP (boleh luar Bontang, tidak dipakai algoritma).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->text('alamat_ktp')->nullable()->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropColumn('alamat_ktp');
        });
    }
};
