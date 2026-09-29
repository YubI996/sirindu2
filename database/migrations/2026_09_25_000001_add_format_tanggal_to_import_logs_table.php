<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Format penanggalan yang dipakai berkas import: 'auto' (disimpulkan dari isi
 * berkas), 'dmy', atau 'mdy'. Disimpan di log — bukan sekadar dioper ke job —
 * supaya reimport memakai pembacaan yang sama dan riwayat bisa menjawab
 * "berkas itu dulu dibaca sebagai format apa".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->string('format_tanggal', 10)->default('auto')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn('format_tanggal');
        });
    }
};
