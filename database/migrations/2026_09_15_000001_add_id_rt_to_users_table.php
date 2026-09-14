<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Akun peran RT: RT yang diverifikasi user ini (spec verifikasi RT §2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_rt')->nullable()->after('id_posyandu');
            $table->foreign('id_rt')->references('id')->on('rt')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_rt']);
            $table->dropColumn('id_rt');
        });
    }
};
