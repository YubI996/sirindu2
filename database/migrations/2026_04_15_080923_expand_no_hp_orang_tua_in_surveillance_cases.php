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
            $table->string('no_hp_orang_tua', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->string('no_hp_orang_tua', 20)->nullable()->change();
        });
    }
};
