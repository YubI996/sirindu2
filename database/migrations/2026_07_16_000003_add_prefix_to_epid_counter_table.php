<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jadikan counter epidemiologi ber-sequence per PREFIX per TAHUN.
 *
 * Penomoran nyata di lapangan: `[prefix]-1710[YY][NNN]` dengan tiap penyakit
 * punya deret sendiri yang mulai dari 1 — C-171026001..217 dan D-171026001..002
 * hidup berdampingan. Counter lama hanya unik per `tahun` (satu deret dipakai
 * bersama semua penyakit), sehingga tak mungkin mencerminkan penomoran asli.
 *
 * Prefix '' = AFP/Polio (memang tanpa prefix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epid_counter', function (Blueprint $table) {
            $table->string('prefix', 4)->default('')->after('tahun');
            $table->dropUnique('epid_counter_tahun_unique');
            $table->unique(['tahun', 'prefix'], 'epid_counter_tahun_prefix_unique');
        });

        // Baris lama = counter GLOBAL per tahun; semantiknya tidak memetakan ke
        // prefix mana pun (membiarkannya berarti ia diam-diam jadi counter AFP).
        // Aman dikosongkan: EpidCounter::getNextSequence menyelaraskan diri dari
        // nomor terpakai di surveillance_cases, jadi counter terbangun ulang sendiri.
        DB::table('epid_counter')->delete();
    }

    public function down(): void
    {
        // Wajib dikosongkan dulu: setelah pemakaian, satu tahun punya banyak baris
        // (satu per prefix) sehingga unique('tahun') tidak bisa dipasang kembali.
        DB::table('epid_counter')->delete();

        Schema::table('epid_counter', function (Blueprint $table) {
            $table->dropUnique('epid_counter_tahun_prefix_unique');
            $table->dropColumn('prefix');
            $table->unique('tahun', 'epid_counter_tahun_unique');
        });
    }
};
