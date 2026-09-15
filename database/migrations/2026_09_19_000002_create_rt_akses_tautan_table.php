<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan bertoken per RT (mode B scoping akses): RT membuka /rt/akses/{token} tanpa akun.
 * Token hanya ditampilkan sekali saat dibuat; DB menyimpan sha256-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rt_akses_tautan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_rt');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('kedaluwarsa_at');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->timestamp('dicabut_at')->nullable();
            $table->timestamp('terakhir_dipakai_at')->nullable();
            $table->unsignedInteger('jumlah_pakai')->default(0);
            $table->timestamps();

            $table->foreign('id_rt')->references('id')->on('rt')->cascadeOnDelete();
            $table->index(['id_rt', 'dicabut_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rt_akses_tautan');
    }
};
