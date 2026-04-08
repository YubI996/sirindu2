<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadikan tanggal_lahir dan kategori_umur nullable.
     *
     * Alasan: Data PD3I dari Google Form sering tidak mengisi tanggal lahir.
     * Sebelumnya fallback ke '1900-01-01' yang membuat kategori_umur selalu 'lansia'.
     * Dengan nullable, data yang benar-benar tidak diketahui dibiarkan null
     * daripada mengisi nilai palsu yang menyesatkan laporan.
     *
     * Data lama dengan '1900-01-01' dibiarkan — tidak di-backfill.
     */
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->date('tanggal_lahir')->nullable()->change();
            $table->enum('kategori_umur', ['bayi', 'balita', 'anak', 'remaja', 'dewasa', 'lansia'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            // Kembalikan ke NOT NULL dengan default 'dewasa' sebagai fallback
            $table->date('tanggal_lahir')->nullable(false)->default('1900-01-01')->change();
            $table->enum('kategori_umur', ['bayi', 'balita', 'anak', 'remaja', 'dewasa', 'lansia'])->nullable(false)->default('dewasa')->change();
        });
    }
};
