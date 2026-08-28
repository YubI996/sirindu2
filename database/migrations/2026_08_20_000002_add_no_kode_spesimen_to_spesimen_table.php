<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DIF-1 III.7 "No. Kode Spesimen" — dicetak sebagai garis kosong karena nomor
 * kode dari lab belum punya tempat penyimpanan. Melekat per baris spesimen,
 * bukan per kasus (satu kasus bisa punya beberapa spesimen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveillance_case_spesimen', function (Blueprint $table) {
            $table->string('no_kode_spesimen', 50)->nullable()->after('jenis_spesimen');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_case_spesimen', function (Blueprint $table) {
            $table->dropColumn('no_kode_spesimen');
        });
    }
};
