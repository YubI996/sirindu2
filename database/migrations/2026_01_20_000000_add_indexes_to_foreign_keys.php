<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->index('id_kec');
            $table->index('id_kel');
            $table->index('id_rt');
            $table->index('id_posyandu');
            $table->index('id_puskesmas');
        });

        Schema::table('posyandu', function (Blueprint $table) {
            $table->index('id_puskesmas');
        });

        Schema::table('puskesmas', function (Blueprint $table) {
            $table->index('id_kecamatan');
        });

        Schema::table('kelurahan', function (Blueprint $table) {
            $table->index('id_kecamatan');
        });

        Schema::table('rt', function (Blueprint $table) {
            $table->index('id_posyandu');
            $table->index('id_kelurahan');
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropIndex(['id_kec']);
            $table->dropIndex(['id_kel']);
            $table->dropIndex(['id_rt']);
            $table->dropIndex(['id_posyandu']);
            $table->dropIndex(['id_puskesmas']);
        });

        Schema::table('posyandu', function (Blueprint $table) {
            $table->dropIndex(['id_puskesmas']);
        });

        Schema::table('puskesmas', function (Blueprint $table) {
            $table->dropIndex(['id_kecamatan']);
        });

        Schema::table('kelurahan', function (Blueprint $table) {
            $table->dropIndex(['id_kecamatan']);
        });

        Schema::table('rt', function (Blueprint $table) {
            $table->dropIndex(['id_posyandu']);
            $table->dropIndex(['id_kelurahan']);
        });
    }
};
