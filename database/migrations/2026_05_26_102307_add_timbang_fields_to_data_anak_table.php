<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_anak', function (Blueprint $table) {
            $table->tinyInteger('pitting_edema')->nullable()->after('lk');
            $table->boolean('asi_bulan_0')->nullable()->after('asi');
            $table->boolean('asi_bulan_1')->nullable()->after('asi_bulan_0');
            $table->boolean('asi_bulan_2')->nullable()->after('asi_bulan_1');
            $table->boolean('asi_bulan_3')->nullable()->after('asi_bulan_2');
            $table->boolean('asi_bulan_4')->nullable()->after('asi_bulan_3');
            $table->boolean('asi_bulan_5')->nullable()->after('asi_bulan_4');
            $table->boolean('asi_bulan_6')->nullable()->after('asi_bulan_5');
            $table->boolean('kelas_ibu_balita')->nullable()->after('vit_a');
            $table->boolean('mbg')->nullable()->after('kelas_ibu_balita');
        });
    }

    public function down(): void
    {
        Schema::table('data_anak', function (Blueprint $table) {
            $table->dropColumn([
                'pitting_edema',
                'asi_bulan_0', 'asi_bulan_1', 'asi_bulan_2', 'asi_bulan_3',
                'asi_bulan_4', 'asi_bulan_5', 'asi_bulan_6',
                'kelas_ibu_balita', 'mbg',
            ]);
        });
    }
};
