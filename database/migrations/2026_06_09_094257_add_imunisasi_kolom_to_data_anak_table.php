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
        Schema::table('data_anak', function (Blueprint $table) {
            $table->string('imunisasi_terakhir')->nullable()->after('mbg');
            $table->string('alasan_tidak_imunisasi')->nullable()->after('imunisasi_terakhir');
        });
    }

    public function down(): void
    {
        Schema::table('data_anak', function (Blueprint $table) {
            $table->dropColumn(['imunisasi_terakhir', 'alasan_tidak_imunisasi']);
        });
    }
};
