<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanggung jawab (PJ) per anak bermasalah gizi — diisi dari modal daftar
 * anak di dasbor Operasi Timbang (stunting / gizi buruk / underweight).
 * Satu PJ per anak, apa pun kategorinya; nama bebas (kader/bidan/petugas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->string('pj_nama', 100)->nullable()->after('catatan');
            $table->unsignedBigInteger('pj_updated_by')->nullable()->after('pj_nama');
            $table->timestamp('pj_updated_at')->nullable()->after('pj_updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropColumn(['pj_nama', 'pj_updated_by', 'pj_updated_at']);
        });
    }
};
