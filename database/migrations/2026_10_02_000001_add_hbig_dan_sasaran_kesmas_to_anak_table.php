<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md §4.
 * Keduanya nullable TANPA DEFAULT:
 * - tgl_hbig: NULL = belum diisi.
 * - sasaran_balita_kesmas: NULL = belum pernah ditandai, 0 = dilepas, 1 = sasaran. Dasbor Kesmas
 *   hanya menghitung 1 (opt-in). DEFAULT 1 memasukkan ±15 rb anak lama ke sasaran tanpa keputusan
 *   siapa pun; DEFAULT 0 menghapus beda "belum ditandai" vs "dilepas" yang dipakai perintah
 *   kesmas:tandai-sasaran.
 * Tanpa after(): kolom ditambahkan di ujung supaya ALTER di prod cepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->date('tgl_hbig')->nullable();
            $table->boolean('sasaran_balita_kesmas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropColumn(['tgl_hbig', 'sasaran_balita_kesmas']);
        });
    }
};
