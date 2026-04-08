<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buat id_rt nullable agar import PD3I tidak gagal ketika data RT belum
     * ada di master data (RT membutuhkan id_posyandu sehingga tidak bisa
     * di-auto-create saat import). Field ini tetap bisa diisi manual setelah import.
     */
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            // Hapus FK dulu sebelum ubah kolom
            $table->dropForeign(['id_rt']);
            $table->unsignedBigInteger('id_rt')->nullable()->change();
            $table->foreign('id_rt')
                  ->references('id')->on('rt')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropForeign(['id_rt']);
            $table->unsignedBigInteger('id_rt')->nullable(false)->change();
            $table->foreign('id_rt')
                  ->references('id')->on('rt')
                  ->onDelete('restrict');
        });
    }
};
