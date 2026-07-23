<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field "Tanggal Konsultasi" dihapus dari form PD3I (permintaan client 2026-07-23).
 * Kolomnya dipertahankan untuk data historis, export, dan importer yang masih
 * mengisinya, tetapi harus nullable — tanpa ini setiap kasus baru dari form
 * gagal disimpan karena kolom NOT NULL tidak lagi dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->date('tanggal_konsultasi')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Baris dengan nilai NULL diisi mundur dari tanggal_lapor/tanggal_onset
        // agar constraint NOT NULL bisa dipasang kembali.
        \DB::table('surveillance_cases')
            ->whereNull('tanggal_konsultasi')
            ->update(['tanggal_konsultasi' => \DB::raw('COALESCE(tanggal_lapor, tanggal_onset)')]);

        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->date('tanggal_konsultasi')->nullable(false)->change();
        });
    }
};
