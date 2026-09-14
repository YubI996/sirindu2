<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Denormalisasi usulan verifikasi RT terakhir (spec §3.2) — untuk badge/filter tanpa join. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->enum('verif_rt_status', ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal'])->nullable()->after('pj_updated_at');
            $table->enum('verif_rt_reviu', ['diusulkan', 'disetujui', 'ditolak'])->nullable()->after('verif_rt_status');
            $table->timestamp('verif_rt_at')->nullable()->after('verif_rt_reviu');
            $table->index('verif_rt_status');
        });
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->dropIndex(['verif_rt_status']);
            $table->dropColumn(['verif_rt_status', 'verif_rt_reviu', 'verif_rt_at']);
        });
    }
};
