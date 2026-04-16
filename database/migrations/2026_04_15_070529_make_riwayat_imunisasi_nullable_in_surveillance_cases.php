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
            $table->enum('riwayat_imunisasi', ['lengkap', 'tidak_lengkap', 'tidak_tahu', 'tidak_ada'])
                  ->nullable()
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->enum('riwayat_imunisasi', ['lengkap', 'tidak_lengkap', 'tidak_tahu', 'tidak_ada'])
                  ->nullable(false)
                  ->default('tidak_tahu')
                  ->change();
        });
    }
};
