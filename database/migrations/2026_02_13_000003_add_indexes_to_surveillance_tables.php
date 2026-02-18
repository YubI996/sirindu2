<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->index(['id_kec', 'id_kel', 'id_rt'], 'idx_geographic');
            $table->index(['status_kasus', 'tanggal_onset'], 'idx_status_onset');
            $table->index(['id_jenis_kasus', 'status_kasus'], 'idx_disease_status');
            $table->index(['kondisi_akhir', 'tanggal_kondisi_akhir'], 'idx_outcome_date');
            $table->index('tanggal_lapor', 'idx_tanggal_lapor');
        });
    }

    public function down()
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropIndex('idx_geographic');
            $table->dropIndex('idx_status_onset');
            $table->dropIndex('idx_disease_status');
            $table->dropIndex('idx_outcome_date');
            $table->dropIndex('idx_tanggal_lapor');
        });
    }
};
