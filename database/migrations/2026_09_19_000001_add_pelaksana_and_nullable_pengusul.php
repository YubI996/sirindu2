<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scoping akses RT (Sept 2026): satu akun per kelurahan atau tautan bertoken tanpa akun.
 * `pelaksana` = nama orang yang mengisi (ditanya sekali per sesi); `diusulkan_oleh`
 * jadi nullable karena mode tautan tidak punya user.
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
        });
    }

    public function down(): void
    {
        Schema::table('verifikasi_anak', function (Blueprint $table) {
            $table->dropColumn('pelaksana');
        });
        Schema::table('anak_tautan', function (Blueprint $table) {
            $table->dropColumn('pelaksana');
        });
    }
};
