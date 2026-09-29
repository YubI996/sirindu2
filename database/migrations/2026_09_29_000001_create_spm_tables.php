<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dasbor & Master Data SPM — spec docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §2.
 *
 * tw1..tw4 SENGAJA nullable tanpa DEFAULT: NULL = triwulan belum dilaporkan,
 * 0 = capaiannya benar-benar nol. Jangan menambah default atau backfill 0 —
 * dasbor akan melaporkan kegagalan program yang tidak pernah terjadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spm_kategori', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 200);
            $table->string('satuan', 50);
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('urutan');
        });

        Schema::create('spm_capaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kategori')->constrained('spm_kategori')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->decimal('sasaran', 14, 2);
            $table->decimal('tw1', 14, 2)->nullable();
            $table->decimal('tw2', 14, 2)->nullable();
            $table->decimal('tw3', 14, 2)->nullable();
            $table->decimal('tw4', 14, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['id_kategori', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spm_capaian');
        Schema::dropIfExists('spm_kategori');
    }
};
