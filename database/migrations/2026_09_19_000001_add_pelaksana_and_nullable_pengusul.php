<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scoping akses RT (Sept 2026): satu akun per kelurahan atau tautan bertoken tanpa akun.
 * `pelaksana` = nama orang yang mengisi (ditanya sekali per sesi); `diusulkan_oleh`
 * jadi nullable karena mode tautan tidak punya user. `anak_tautan.id_rt` = RT yang memutus
 * (dulu diturunkan dari users.id_rt pengusul — tak ada lagi untuk akun kelurahan/tautan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifikasi_anak', function (Blueprint $table) {
            $table->string('pelaksana', 100)->nullable()->after('catatan');
            $table->unsignedBigInteger('diusulkan_oleh')->nullable()->change();
        });
        Schema::table('anak_tautan', function (Blueprint $table) {
            $table->string('pelaksana', 100)->nullable()->after('catatan');
            $table->unsignedBigInteger('diusulkan_oleh')->nullable()->change();
            $table->unsignedBigInteger('id_rt')->nullable()->after('via');
            $table->foreign('id_rt')->references('id')->on('rt')->nullOnDelete();
        });

        // Isi id_rt tautan lama dari RT akun pengusulnya
        DB::statement('UPDATE anak_tautan t JOIN users u ON u.id = t.diusulkan_oleh SET t.id_rt = u.id_rt WHERE t.id_rt IS NULL');
    }

    public function down(): void
    {
        Schema::table('verifikasi_anak', function (Blueprint $table) {
            $table->dropColumn('pelaksana');
        });
        Schema::table('anak_tautan', function (Blueprint $table) {
            $table->dropForeign(['id_rt']);
            $table->dropColumn(['pelaksana', 'id_rt']);
        });
    }
};
