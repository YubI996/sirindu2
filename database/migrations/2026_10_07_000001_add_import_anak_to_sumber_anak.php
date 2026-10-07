<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `anak.sumber` mendapat nilai `import_anak`: anak yang DIBUAT oleh AnakImport (template_anak).
 *
 * Sebelumnya mereka berlabel 'manual' (bawaan kolom), sama dengan anak yang diketik petugas lewat form,
 * sehingga laporan "berapa anak dari import" hanya bisa ditebak dari awalan nomor registrasi `IMP-`.
 *
 * Backfill: `sumber = 'manual'` DAN `no LIKE 'IMP-%'`. Awalan itu hanya dibuat AnakImport (form tambah anak
 * dan import lain tak memakainya). Anak Operasi Timbang/Capil yang nomornya tertimpa IMP- oleh bug jalur NIK
 * lama bersumber lain, jadi tidak ikut tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE anak MODIFY sumber ENUM('operasi_timbang','manual','capil','dummy','import_anak') NOT NULL DEFAULT 'manual'");

        DB::table('anak')
            ->where('sumber', 'manual')
            ->where('no', 'like', 'IMP-%')
            ->update(['sumber' => 'import_anak']);
    }

    public function down(): void
    {
        DB::table('anak')->where('sumber', 'import_anak')->update(['sumber' => 'manual']);

        DB::statement("ALTER TABLE anak MODIFY sumber ENUM('operasi_timbang','manual','capil','dummy') NOT NULL DEFAULT 'manual'");
    }
};
