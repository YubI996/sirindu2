<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->date('tanggal_batuk')->nullable()->after('tanggal_demam');
            $table->date('tanggal_pilek')->nullable()->after('tanggal_batuk');
            $table->date('tanggal_sakit_kepala')->nullable()->after('tanggal_pilek');
            $table->date('tanggal_mual')->nullable()->after('tanggal_sakit_kepala');
            $table->date('tanggal_muntah')->nullable()->after('tanggal_mual');
            $table->date('tanggal_diare')->nullable()->after('tanggal_muntah');
            $table->date('tanggal_ruam')->nullable()->after('tanggal_diare');
            $table->date('tanggal_nyeri_otot')->nullable()->after('tanggal_ruam');
            $table->date('tanggal_nyeri_sendi')->nullable()->after('tanggal_nyeri_otot');
            $table->date('tanggal_lemas')->nullable()->after('tanggal_nyeri_sendi');
            $table->date('tanggal_kehilangan_nafsu_makan')->nullable()->after('tanggal_lemas');
            $table->date('tanggal_mata_merah')->nullable()->after('tanggal_kehilangan_nafsu_makan');
            $table->date('tanggal_pembengkakan_kelenjar')->nullable()->after('tanggal_mata_merah');
            $table->date('tanggal_kejang')->nullable()->after('tanggal_pembengkakan_kelenjar');
            $table->date('tanggal_penurunan_kesadaran')->nullable()->after('tanggal_kejang');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropColumn([
                'tanggal_batuk', 'tanggal_pilek', 'tanggal_sakit_kepala',
                'tanggal_mual', 'tanggal_muntah', 'tanggal_diare', 'tanggal_ruam',
                'tanggal_nyeri_otot', 'tanggal_nyeri_sendi', 'tanggal_lemas',
                'tanggal_kehilangan_nafsu_makan', 'tanggal_mata_merah',
                'tanggal_pembengkakan_kelenjar', 'tanggal_kejang',
                'tanggal_penurunan_kesadaran',
            ]);
        });
    }
};
