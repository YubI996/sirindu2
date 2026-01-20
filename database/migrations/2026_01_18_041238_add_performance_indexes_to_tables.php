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
        // Add indexes to anak table for foreign key columns
        Schema::table('anak', function (Blueprint $table) {
            $table->index('id_kec', 'idx_anak_id_kec');
            $table->index('id_kel', 'idx_anak_id_kel');
            $table->index('id_rt', 'idx_anak_id_rt');
            $table->index('id_puskesmas', 'idx_anak_id_puskesmas');
            $table->index('id_posyandu', 'idx_anak_id_posyandu');
            $table->index('tgl_lahir', 'idx_anak_tgl_lahir');
        });

        // Add indexes to data_anak table for frequently queried columns
        Schema::table('data_anak', function (Blueprint $table) {
            $table->index('id_anak', 'idx_data_anak_id_anak');
            $table->index('tgl_kunjungan', 'idx_data_anak_tgl_kunjungan');
            $table->index(['id_anak', 'tgl_kunjungan'], 'idx_data_anak_id_tgl');
            $table->index('bln', 'idx_data_anak_bln');
        });

        // Add indexes to imunisasi table
        Schema::table('imunisasi', function (Blueprint $table) {
            $table->index('id_anak', 'idx_imunisasi_id_anak');
            $table->index('id_jenis_vaksin', 'idx_imunisasi_id_jenis_vaksin');
            $table->index('status', 'idx_imunisasi_status');
            $table->index(['id_anak', 'status'], 'idx_imunisasi_id_anak_status');
        });

        // Add composite indexes to z_score table for frequently used WHERE conditions
        Schema::table('z_score', function (Blueprint $table) {
            $table->index(['jenis_tbl', 'jk', 'acuan', 'var'], 'idx_zscore_lookup');
            $table->index(['jenis_tbl', 'jk', 'acuan'], 'idx_zscore_partial');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop indexes from anak table
        Schema::table('anak', function (Blueprint $table) {
            $table->dropIndex('idx_anak_id_kec');
            $table->dropIndex('idx_anak_id_kel');
            $table->dropIndex('idx_anak_id_rt');
            $table->dropIndex('idx_anak_id_puskesmas');
            $table->dropIndex('idx_anak_id_posyandu');
            $table->dropIndex('idx_anak_tgl_lahir');
        });

        // Drop indexes from data_anak table
        Schema::table('data_anak', function (Blueprint $table) {
            $table->dropIndex('idx_data_anak_id_anak');
            $table->dropIndex('idx_data_anak_tgl_kunjungan');
            $table->dropIndex('idx_data_anak_id_tgl');
            $table->dropIndex('idx_data_anak_bln');
        });

        // Drop indexes from imunisasi table
        Schema::table('imunisasi', function (Blueprint $table) {
            $table->dropIndex('idx_imunisasi_id_anak');
            $table->dropIndex('idx_imunisasi_id_jenis_vaksin');
            $table->dropIndex('idx_imunisasi_status');
            $table->dropIndex('idx_imunisasi_id_anak_status');
        });

        // Drop indexes from z_score table
        Schema::table('z_score', function (Blueprint $table) {
            $table->dropIndex('idx_zscore_lookup');
            $table->dropIndex('idx_zscore_partial');
        });
    }
};
