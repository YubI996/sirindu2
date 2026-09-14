<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hasil pindai kemiripan (precompute, diisi ulang penuh oleh identitas:pindai) — spec verifikasi RT §3.1. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anak_kandidat', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_anak_a');
            $table->unsignedBigInteger('id_anak_b');
            $table->decimal('skor', 8, 2);
            $table->string('via', 16);
            $table->decimal('child_sim', 5, 2);
            $table->decimal('parent_sim', 5, 2);
            $table->timestamp('dipindai_at');

            $table->unique(['id_anak_a', 'id_anak_b']);
            $table->index('id_anak_b');
            $table->foreign('id_anak_a')->references('id')->on('anak')->cascadeOnDelete();
            $table->foreign('id_anak_b')->references('id')->on('anak')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anak_kandidat');
    }
};
