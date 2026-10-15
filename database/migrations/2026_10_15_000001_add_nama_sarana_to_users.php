<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Peran sarkes (sarana kesehatan swasta): belum ada master klinik, jadi namanya teks bebas.
            $table->string('nama_sarana', 150)->nullable()->after('id_rs');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nama_sarana');
        });
    }
};
