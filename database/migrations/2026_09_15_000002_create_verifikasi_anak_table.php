<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat usulan verifikasi RT per anak (spec §3.1). Satu baris per usulan;
 * baris terbaru yang bukan `bukan_rt_ini` = yang berlaku (didenormalisasi ke anak.verif_rt_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifikasi_anak', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak');
            $table->unsignedBigInteger('id_rt');
            $table->enum('status', ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal', 'bukan_rt_ini']);
            $table->unsignedBigInteger('klaim_id_rt')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('diusulkan_oleh');
            $table->timestamp('diusulkan_at');
            $table->enum('reviu', ['diusulkan', 'disetujui', 'ditolak'])->default('diusulkan');
            $table->unsignedBigInteger('ditinjau_oleh')->nullable();
            $table->timestamp('ditinjau_at')->nullable();
            $table->text('catatan_reviu')->nullable();
            $table->timestamps();

            $table->foreign('id_anak')->references('id')->on('anak')->cascadeOnDelete();
            $table->foreign('id_rt')->references('id')->on('rt')->cascadeOnDelete();
            $table->index(['id_anak', 'id']);
            $table->index(['reviu', 'id_rt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifikasi_anak');
    }
};
