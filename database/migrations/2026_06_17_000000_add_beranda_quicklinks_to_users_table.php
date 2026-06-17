<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Daftar key quicklink beranda yang dipilih user untuk ditampilkan.
            // NULL = default (tampilkan semua quicklink yang tersedia untuk role-nya).
            $table->text('beranda_quicklinks')->nullable()->after('id_posyandu');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('beranda_quicklinks');
        });
    }
};
