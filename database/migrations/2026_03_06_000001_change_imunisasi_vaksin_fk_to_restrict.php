<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing FK if it exists (may have been removed by prior migrations)
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'imunisasi'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );
        $fkNames = array_map(fn($fk) => $fk->CONSTRAINT_NAME, $fks);

        Schema::table('imunisasi', function (Blueprint $table) use ($fkNames) {
            if (in_array('imunisasi_id_jenis_vaksin_foreign', $fkNames)) {
                $table->dropForeign(['id_jenis_vaksin']);
            }
            $table->foreign('id_jenis_vaksin')
                  ->references('id')->on('jenis_vaksin')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('imunisasi', function (Blueprint $table) {
            $table->dropForeign(['id_jenis_vaksin']);
            $table->foreign('id_jenis_vaksin')
                  ->references('id')->on('jenis_vaksin')
                  ->onDelete('cascade');
        });
    }
};
