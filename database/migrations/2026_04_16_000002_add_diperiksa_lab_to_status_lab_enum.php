<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah nilai 'diperiksa_lab' dan 'tidak_diperiksa_lab' ke ENUM status_lab
        DB::statement("ALTER TABLE surveillance_cases MODIFY COLUMN status_lab
            ENUM('belum_diperiksa','proses','positif','negatif','diperiksa_lab','tidak_diperiksa_lab')
            NOT NULL DEFAULT 'belum_diperiksa'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE surveillance_cases MODIFY COLUMN status_lab
            ENUM('belum_diperiksa','proses','positif','negatif')
            NOT NULL DEFAULT 'belum_diperiksa'");
    }
};
