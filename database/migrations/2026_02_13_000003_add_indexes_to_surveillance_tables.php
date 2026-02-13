<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            // Composite indexes for common query patterns
            $table->index(['id_kec', 'id_kel', 'id_rt'], 'idx_geographic');
            $table->index(['status_kasus', 'tanggal_onset'], 'idx_status_onset');
            $table->index(['id_jenis_kasus', 'status_kasus'], 'idx_disease_status');
            $table->index(['kondisi_akhir', 'tanggal_kondisi_akhir'], 'idx_outcome_date');

            // Fulltext index for search functionality
            $table->fullText(['nama_lengkap', 'alamat_lengkap'], 'idx_fulltext_search');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropIndex('idx_geographic');
            $table->dropIndex('idx_status_onset');
            $table->dropIndex('idx_disease_status');
            $table->dropIndex('idx_outcome_date');
            $table->dropFullText('idx_fulltext_search');
        });
    }
};
