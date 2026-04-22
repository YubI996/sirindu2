<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->boolean('gejala_pseudomembran')->default(false)->after('gejala_penurunan_kesadaran');
            $table->boolean('gejala_leher_bengkak')->default(false)->after('gejala_pseudomembran');
            $table->boolean('gejala_apnea')->default(false)->after('gejala_leher_bengkak');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropColumn(['gejala_pseudomembran', 'gejala_leher_bengkak', 'gejala_apnea']);
        });
    }
};
