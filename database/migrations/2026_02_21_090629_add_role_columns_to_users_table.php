<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom role, faskes_type, id_rs ke tabel users
     * untuk mendukung sistem multi-role per modul SIRINDU.
     *
     * Role values:
     *   imunisasi_superadmin   — Dinkes: full access modul imunisasi
     *   imunisasi_faskes       — Puskesmas/RS: input & edit data imunisasi faskes sendiri
     *   surveilans_superadmin  — Dinkes: full access modul surveilans
     *   surveilans_puskesmas   — Puskesmas: input + dashboard scoped ke puskesmas
     *   surveilans_rs          — RS: input + dashboard scoped ke RS
     *   superadmin             — Sistem: akses penuh semua modul
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Role utama user dalam sistem
            $table->string('role', 50)->nullable()->after('type')
                ->comment('imunisasi_superadmin|imunisasi_faskes|surveilans_superadmin|surveilans_puskesmas|surveilans_rs|superadmin');

            // Tipe faskes: dinkes, puskesmas, rs
            $table->string('faskes_type', 20)->nullable()->after('role')
                ->comment('dinkes|puskesmas|rs');

            // FK ke rumah_sakits (untuk user RS)
            $table->unsignedBigInteger('id_rs')->nullable()->after('id_puskesmas');
            $table->foreign('id_rs')->references('id')->on('rumah_sakits')->nullOnDelete();

            // Index untuk query filtering
            $table->index('role');
            $table->index(['faskes_type', 'id_puskesmas']);
            $table->index(['faskes_type', 'id_rs']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_rs']);
            $table->dropIndex(['role']);
            $table->dropIndex(['faskes_type', 'id_puskesmas']);
            $table->dropIndex(['faskes_type', 'id_rs']);
            $table->dropColumn(['role', 'faskes_type', 'id_rs']);
        });
    }
};
