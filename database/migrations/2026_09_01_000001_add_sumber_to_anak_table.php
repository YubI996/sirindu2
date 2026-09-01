<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci dashboard Gizi & Timbang ke populasi Operasi Timbang (OT) supaya
 * statistiknya tidak bergeser saat ada anak baru terdaftar (kelahiran baru,
 * import Capil berikutnya, dst).
 *
 * Backfill SENGAJA tidak memakai created_at: data OT di server tidak selalu
 * masuk lewat Eloquent (mis. dump SQL di-restore langsung) — created_at bisa
 * NULL (kolom timestamps() tanpa DEFAULT CURRENT_TIMESTAMP) atau membawa
 * nilai asli dari staging yang tanggalnya tak pasti. ImunisasiImport TIDAK
 * PERNAH membuat baris anak baru (hanya mencocokkan anak yang sudah ada —
 * lihat ResolvesAnakByTwoOfThree), jadi seluruh baris anak yang sudah ada
 * saat migration ini jalan aman ditandai 'operasi_timbang' — keputusan
 * eksplisit pemilik data, bukan tebakan dari kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->enum('sumber', ['operasi_timbang', 'manual', 'capil', 'dummy'])
                ->default('manual')
                ->after('id');
        });

        DB::table('anak')->update(['sumber' => 'operasi_timbang']);
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
