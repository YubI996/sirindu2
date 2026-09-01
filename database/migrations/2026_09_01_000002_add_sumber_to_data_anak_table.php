<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lihat 2026_09_01_000001_add_sumber_to_anak_table.php untuk latar lengkap
 * soal kenapa backfill tidak memakai created_at.
 *
 * Baris placeholder ImunisasiImport::writeAlasanTidakImunisasi() punya
 * tanda isi yang tetap (bukan metadata infrastruktur) apa pun cara baris
 * itu masuk ke database: bb=0, tb=0, lla=0, lk=0, dan
 * alasan_tidak_imunisasi TERISI — sengaja dibuat begitu di kode importer,
 * jadi tandanya bertahan lewat dump SQL sekalipun. Baris data_anak lainnya
 * (yg punya pengukuran BB/TB sungguhan) ditandai 'operasi_timbang', sesuai
 * keputusan eksplisit pemilik data: seluruh data anak selain import
 * imunisasi dianggap data OT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_anak', function (Blueprint $table) {
            $table->enum('sumber', ['operasi_timbang', 'manual', 'capil', 'dummy', 'imunisasi'])
                ->default('manual')
                ->after('id');
        });

        DB::table('data_anak')
            ->where('bb', 0)
            ->where('tb', 0)
            ->where('lla', 0)
            ->where('lk', 0)
            ->whereNotNull('alasan_tidak_imunisasi')
            ->update(['sumber' => 'imunisasi']);

        DB::table('data_anak')
            ->where(function ($w) {
                $w->where('bb', '!=', 0)
                  ->orWhere('tb', '!=', 0)
                  ->orWhere('lla', '!=', 0)
                  ->orWhere('lk', '!=', 0)
                  ->orWhereNull('alasan_tidak_imunisasi');
            })
            ->update(['sumber' => 'operasi_timbang']);
    }

    public function down(): void
    {
        Schema::table('data_anak', function (Blueprint $table) {
            $table->dropColumn('sumber');
        });
    }
};
