<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Daftar sumber yang pernah dilebur ke baris ini (spec §3.2); `sumber` tetap satu nilai. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->json('sumber_gabungan')->nullable()->after('sumber');
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropColumn('sumber_gabungan');
        });
    }
};
