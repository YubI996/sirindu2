<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum alteration requires raw SQL
        DB::statement("ALTER TABLE imunisasi MODIFY COLUMN status ENUM('belum','sudah','terlambat','kadaluarsa','tidak_relevan') NOT NULL DEFAULT 'belum'");

        Schema::table('imunisasi', function (Blueprint $table) {
            $table->boolean('is_kejar')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('imunisasi', function (Blueprint $table) {
            $table->dropColumn('is_kejar');
        });

        DB::statement("ALTER TABLE imunisasi MODIFY COLUMN status ENUM('belum','sudah','terlambat') NOT NULL DEFAULT 'belum'");
    }
};
