<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun peran rt lingkup kelurahan (mode A scoping akses): id_rt kosong DAN rt_sekelurahan=1
 * → memilih RT sekelurahan saat masuk. Penanda eksplisit, supaya akun RT yang RT-nya
 * terhapus (id_rt jadi NULL lewat nullOnDelete) tidak diam-diam naik jadi akun kelurahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('rt_sekelurahan')->default(false)->after('id_rt');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rt_sekelurahan');
        });
    }
};
