<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom yang berasal dari kolom-kolom terakhir file impor PD3I
 * (Google Form export):
 *  - penyakit_terkonfirmasi : nama penyakit hasil "Klasifikasi Akhir"
 *    saat status_kasus = confirmed (mis. "Campak"/"Rubella"). Dipakai
 *    dashboard untuk menghitung kasus campak vs rubella tanpa bergantung
 *    pada parsing teks hasil_lab yang rawan typo.
 *  - dengan_komplikasi : ringkasan kolom "Dengan Komplikasi" (Ya/Tidak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->string('penyakit_terkonfirmasi', 100)->nullable()->after('status_kasus');
            $table->boolean('dengan_komplikasi')->nullable()->after('komplikasi_ulkus_mukosa_mulut');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropColumn(['penyakit_terkonfirmasi', 'dengan_komplikasi']);
        });
    }
};
