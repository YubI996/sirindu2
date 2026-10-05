<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Penggabungan dua baris anak (IdentitasMergeService) bisa memindahkan tanda Sasaran Balita Kesmas
 * dari baris yang dilebur ke baris yang dipertahankan. Perubahan itu harus tercatat di
 * sasaran_kesmas_log dengan sumbernya sendiri, supaya "kenapa anak ini mendadak dihitung?" terjawab.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE sasaran_kesmas_log MODIFY sumber ENUM('form_tambah','form_edit','perintah','batal','gabung') NOT NULL");
    }

    /** Gagal bila sudah ada baris 'gabung' — jejak audit sengaja tidak dihapus diam-diam. */
    public function down(): void
    {
        DB::statement("ALTER TABLE sasaran_kesmas_log MODIFY sumber ENUM('form_tambah','form_edit','perintah','batal') NOT NULL");
    }
};
