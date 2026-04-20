<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE data_anak MODIFY COLUMN `hasil_lila` VARCHAR(100) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE data_anak MODIFY COLUMN `hasil_lila` VARCHAR(30) NULL");
    }
};
