<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveillance_case_spesimen', function (Blueprint $table) {
            // Menggantikan FK id_jenis_kasus_terkonfirmasi dengan field teks statis
            // agar Campak dan Rubella bisa dipilih terpisah dan "Negatif" bisa menjadi opsi
            $table->string('penyakit_terkonfirmasi', 50)->nullable()->after('status_pemeriksaan');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_case_spesimen', function (Blueprint $table) {
            $table->dropColumn('penyakit_terkonfirmasi');
        });
    }
};
