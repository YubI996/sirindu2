<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_vaksin', function (Blueprint $table) {
            $table->integer('catchup_max_hari')->nullable()->after('interval_hari');
            $table->boolean('bisa_dikejar')->default(true)->after('catchup_max_hari');
        });
    }

    public function down(): void
    {
        Schema::table('jenis_vaksin', function (Blueprint $table) {
            $table->dropColumn(['catchup_max_hari', 'bisa_dikejar']);
        });
    }
};
