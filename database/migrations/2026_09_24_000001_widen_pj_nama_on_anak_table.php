<?php

use App\Models\Anak;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klien (Sep 2026) mengisi penanggung jawab anak dengan jabatan OPD
 * ("Kepala Badan … dan Seluruh Staf"), bukan nama orang. Batas 100 karakter
 * (migrasi 2026_09_14_000001) terlalu sempit untuk nama OPD panjang, jadi
 * kolom dilebarkan ke Anak::PJ_NAMA_MAKS (200). Tidak ada indeks di kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->string('pj_nama', Anak::PJ_NAMA_MAKS)->nullable()->change();
        });
    }

    /** Menyempitkan kembali memotong isi yang lebih dari 100 karakter. */
    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->string('pj_nama', 100)->nullable()->change();
        });
    }
};
