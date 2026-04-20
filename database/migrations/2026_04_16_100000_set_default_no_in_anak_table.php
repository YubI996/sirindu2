<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE anak MODIFY COLUMN `no` VARCHAR(255) NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE anak MODIFY COLUMN `no` VARCHAR(255) NOT NULL");
    }
};
