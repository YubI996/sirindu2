<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom faskes_type dan id_faskes ke surveillance_cases.
     * Diperlukan untuk data scoping agar query per-faskes bisa bekerja.
     *
     * faskes_type: 'puskesmas' | 'rs'
     * id_faskes  : id puskesmas atau id RS yang menginput kasus ini
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->string('faskes_type', 20)->nullable()->after('id_rt')
                ->comment('puskesmas|rs — siapa yang menginput kasus ini');
            $table->unsignedBigInteger('id_faskes')->nullable()->after('faskes_type')
                ->comment('id of puskesmas or rs that reported this case');

            $table->index(['faskes_type', 'id_faskes'], 'idx_sc_faskes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropIndex('idx_sc_faskes');
            $table->dropColumn(['faskes_type', 'id_faskes']);
        });
    }
};
