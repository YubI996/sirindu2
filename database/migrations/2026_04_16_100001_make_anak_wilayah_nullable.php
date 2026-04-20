<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom-kolom ini tidak tersedia di file kohort, harus nullable
        DB::statement("ALTER TABLE anak
            MODIFY COLUMN `id_kec`    BIGINT       NULL,
            MODIFY COLUMN `id_kel`    BIGINT       NULL,
            MODIFY COLUMN `id_rt`     INT          NULL,
            MODIFY COLUMN `nama_ibu`  VARCHAR(255) NULL,
            MODIFY COLUMN `nama_ayah` VARCHAR(255) NULL
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE anak
            MODIFY COLUMN `id_kec`    BIGINT       NOT NULL,
            MODIFY COLUMN `id_kel`    BIGINT       NOT NULL,
            MODIFY COLUMN `id_rt`     INT          NOT NULL,
            MODIFY COLUMN `nama_ibu`  VARCHAR(255) NOT NULL,
            MODIFY COLUMN `nama_ayah` VARCHAR(255) NOT NULL
        ");
    }
};
