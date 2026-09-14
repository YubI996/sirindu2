<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jejak penggabungan dua baris anak (spec verifikasi RT §3.1) — snapshot cukup untuk membatalkan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anak_merge_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_dipertahankan')->index();
            $table->unsignedBigInteger('id_dihapus')->index();
            $table->unsignedBigInteger('id_tautan')->nullable();
            $table->json('snapshot');
            $table->unsignedBigInteger('oleh');
            $table->unsignedBigInteger('dibatalkan_oleh')->nullable();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak_merge_log');
    }
};
