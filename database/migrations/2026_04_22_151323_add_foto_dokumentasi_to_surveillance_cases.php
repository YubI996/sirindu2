<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->string('foto_dokumentasi')->nullable()->after('catatan_tambahan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropColumn('foto_dokumentasi');
        });
    }
};
