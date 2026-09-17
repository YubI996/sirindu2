<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan klien (Sep 2026): penanggung jawab anak cukup nama, tanpa NIP.
 * Kolom pj_nip (ditambahkan 2026_09_20_000001) dibuang beserta isinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('anak', 'pj_nip')) {
            Schema::table('anak', fn (Blueprint $table) => $table->dropColumn('pj_nip'));
        }
    }

    public function down(): void
    {
        Schema::table('anak', function (Blueprint $table) {
            $table->string('pj_nip', 18)->nullable()->after('pj_nama');
        });
    }
};
