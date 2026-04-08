<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_anak', function (Blueprint $table) {
            // New columns from kohort Excel (all nullable)
            $table->string('hasil_lk', 30)->nullable()->after('lk');
            $table->string('hasil_lila', 30)->nullable()->after('lla');
            $table->decimal('zscore_bb_u', 6, 3)->nullable()->after('bb');
            $table->decimal('zscore_pb_u', 6, 3)->nullable()->after('zscore_bb_u');
            $table->decimal('zscore_bb_pb', 6, 3)->nullable()->after('zscore_pb_u');
            $table->decimal('pb_meter', 5, 3)->nullable()->after('tb');
            $table->decimal('imt', 5, 2)->nullable()->after('pb_meter');
            $table->decimal('imt_u', 6, 3)->nullable()->after('imt');
            $table->boolean('rujuk')->nullable()->after('asi');
            $table->boolean('taburia')->nullable()->after('rujuk');
            $table->boolean('popm')->nullable()->after('taburia');
            $table->boolean('makanan_pokok')->nullable()->after('popm');
            $table->boolean('mkn_kacang')->nullable()->after('makanan_pokok');
            $table->boolean('mkn_susu')->nullable()->after('mkn_kacang');
            $table->boolean('mkn_daging')->nullable()->after('mkn_susu');
            $table->boolean('mkn_telur')->nullable()->after('mkn_daging');
            $table->boolean('mkn_buah_vita')->nullable()->after('mkn_telur');
            $table->boolean('mkn_buah_lain')->nullable()->after('mkn_buah_vita');
        });
    }

    public function down(): void
    {
        Schema::table('data_anak', function (Blueprint $table) {
            $table->dropColumn([
                'hasil_lk', 'hasil_lila', 'zscore_bb_u', 'zscore_pb_u', 'zscore_bb_pb',
                'pb_meter', 'imt', 'imt_u', 'rujuk', 'taburia', 'popm',
                'makanan_pokok', 'mkn_kacang', 'mkn_susu', 'mkn_daging',
                'mkn_telur', 'mkn_buah_vita', 'mkn_buah_lain',
            ]);
        });
    }
};
