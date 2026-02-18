<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        $results = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        return count($results) > 0;
    }

    public function up(): void
    {
        if (Schema::hasTable('data_anak') && !$this->indexExists('data_anak', 'idx_data_anak_id_anak')) {
            Schema::table('data_anak', function (Blueprint $table) {
                $table->index('id_anak', 'idx_data_anak_id_anak');
            });
        }

        if (Schema::hasTable('posyandu') && !$this->indexExists('posyandu', 'idx_posyandu_id_puskesmas')) {
            Schema::table('posyandu', function (Blueprint $table) {
                $table->index('id_puskesmas', 'idx_posyandu_id_puskesmas');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('data_anak') && $this->indexExists('data_anak', 'idx_data_anak_id_anak')) {
            Schema::table('data_anak', function (Blueprint $table) {
                $table->dropIndex('idx_data_anak_id_anak');
            });
        }

        if (Schema::hasTable('posyandu') && $this->indexExists('posyandu', 'idx_posyandu_id_puskesmas')) {
            Schema::table('posyandu', function (Blueprint $table) {
                $table->dropIndex('idx_posyandu_id_puskesmas');
            });
        }
    }
};
