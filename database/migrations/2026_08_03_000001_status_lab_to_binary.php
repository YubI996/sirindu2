<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sederhanakan status_lab menjadi biner: `diperiksa` / `tidak`.
 * Granularitas hasil (positif/negatif) tetap ada di baris spesimen & status_kasus.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Longgarkan ke VARCHAR agar bisa menulis nilai baru.
        DB::statement("ALTER TABLE surveillance_cases MODIFY status_lab VARCHAR(30) NULL");
        // 2) Petakan nilai lama → biner.
        DB::statement("UPDATE surveillance_cases SET status_lab =
            CASE WHEN status_lab IN ('positif','negatif','proses','diperiksa_lab')
                 THEN 'diperiksa' ELSE 'tidak' END");
        // 3) Kunci jadi ENUM biner.
        DB::statement("ALTER TABLE surveillance_cases
            MODIFY status_lab ENUM('diperiksa','tidak') NOT NULL DEFAULT 'tidak'");
    }

    public function down(): void
    {
        // Balikan best-effort — granularitas positif/negatif tidak bisa dipulihkan.
        DB::statement("ALTER TABLE surveillance_cases MODIFY status_lab VARCHAR(30) NULL");
        DB::statement("UPDATE surveillance_cases SET status_lab =
            CASE WHEN status_lab = 'diperiksa' THEN 'diperiksa_lab' ELSE 'tidak_diperiksa_lab' END");
        DB::statement("ALTER TABLE surveillance_cases
            MODIFY status_lab ENUM('belum_diperiksa','proses','positif','negatif','diperiksa_lab','tidak_diperiksa_lab')
            NOT NULL DEFAULT 'belum_diperiksa'");
    }
};
