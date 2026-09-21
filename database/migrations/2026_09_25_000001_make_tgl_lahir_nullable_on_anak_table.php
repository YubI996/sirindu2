<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dasbor Kesmas (spec docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md §2.3)
 * mengasumsikan `anak.tgl_lahir` bisa NULL untuk anak yang tanggal lahirnya belum
 * diketahui — KesmasDashboardService::sasaranSub() mengecualikannya lewat
 * `whereNotNull('a.tgl_lahir')`. Kolom ini NOT NULL sejak migrasi awal
 * (2022_08_02_011516_create_anak_table) dan belum pernah dilonggarkan, sehingga
 * skenario itu sebelumnya tidak bisa terjadi di database sama sekali —
 * ditemukan saat menjalankan KesmasDashboardServiceTest (Task 3, 2026-09-21):
 * fixture `tgl_lahir => null` gagal INSERT (constraint violation), bukan gagal
 * di logika service.
 *
 * Perubahan ini hanya melonggarkan constraint (NOT NULL → NULLABLE); tidak
 * mengubah satu pun baris yang ada (semua anak lama sudah mengisi tgl_lahir).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE anak MODIFY COLUMN `tgl_lahir` DATE NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE anak MODIFY COLUMN `tgl_lahir` DATE NOT NULL');
    }
};
