<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_vaksin', function (Blueprint $table) {
            $table->foreignId('id_kelompok_vaksin')
                ->nullable()
                ->after('keterangan')
                ->constrained('kelompok_vaksin')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jenis_vaksin', function (Blueprint $table) {
            $table->dropForeign(['id_kelompok_vaksin']);
            $table->dropColumn('id_kelompok_vaksin');
        });
    }
};
