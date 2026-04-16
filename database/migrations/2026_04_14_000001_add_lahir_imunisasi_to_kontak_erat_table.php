<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveillance_case_kontak_erat', function (Blueprint $table) {
            $table->date('tanggal_lahir')->nullable()->after('hubungan');
            $table->tinyInteger('jumlah_imunisasi_campak_rubella')->unsigned()->nullable()->after('ada_gejala');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_case_kontak_erat', function (Blueprint $table) {
            $table->dropColumn(['tanggal_lahir', 'jumlah_imunisasi_campak_rubella']);
        });
    }
};
