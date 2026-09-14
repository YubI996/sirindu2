<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan pasangan (sama/beda) oleh RT + reviu — spec verifikasi RT §3.1.
 * Tanpa FK cascade ke anak: baris `digabung` harus bertahan sebagai riwayat setelah merge (T3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anak_tautan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak_a');
            $table->unsignedBigInteger('id_anak_b');
            $table->enum('keputusan', ['sama', 'beda']);
            $table->decimal('skor', 8, 2)->nullable();
            $table->string('via', 16)->nullable();
            $table->enum('status', ['diusulkan', 'disetujui', 'ditolak', 'digabung'])->default('diusulkan');
            $table->unsignedBigInteger('diusulkan_oleh');
            $table->timestamp('diusulkan_at');
            $table->unsignedBigInteger('ditinjau_oleh')->nullable();
            $table->timestamp('ditinjau_at')->nullable();
            $table->text('catatan')->nullable();
            $table->text('catatan_reviu')->nullable();
            $table->timestamps();

            $table->unique(['id_anak_a', 'id_anak_b']);
            $table->index('id_anak_b');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak_tautan');
    }
};
