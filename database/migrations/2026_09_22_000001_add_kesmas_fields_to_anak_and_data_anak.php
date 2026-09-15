<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data Kesmas anak — spec docs/superpowers/specs/2026-09-15-data-kesmas-design.md §2.
 * Semua kolom nullable TANPA default: NULL = belum diisi. DDL klien memberi
 * default YA/TIDAK/BELUM; kalau diterapkan, ±15.000 anak lama langsung tercatat
 * "punya air bersih / tidak PJB" tanpa pernah ditanya.
 *
 * Kolom riwayat lahir yang SUDAH ada dan dipakai ulang (jangan dibuat lagi):
 * imd, usia_kehamilan_lahir, penolong_lahir, komplikasi_persalinan, bbl, pbl, lk_lahir.
 */
return new class extends Migration
{
    private const SKRINING = ['normal', 'tidak_normal', 'belum'];

    private const ANAK = [
        'no_id_epus', 'fktp_bpjs', 'air_bersih', 'jamban_sehat', 'merokok_keluarga', 'status_tk_paud',
        'penyakit_penyerta', 'pjb', 'riwayat_kek_ibu', 'tempat_bersalin', 'jenis_persalinan',
        'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b', 'komplikasi_neonatal',
    ];

    private const DATA_ANAK = [
        'tgl_penanda_ckg', 'mtbm', 'mtbs', 'skrining_atresia_bilier', 'kn1', 'kn3', 'pkat', 'oralit_zinc',
        'pemeriksaan_gigi', 'rujukan', 'mt_pangan_lokal', 'catatan_pengukuran', 'pemeriksaan_lainnya',
        'pola_makan', 'pola_asuh', 'intervensi',
    ];

    public function up(): void
    {
        Schema::table('anak', function (Blueprint $t) {
            // Kesmas & lingkungan
            $t->string('no_id_epus', 50)->nullable();
            $t->string('fktp_bpjs', 100)->nullable();
            $t->boolean('air_bersih')->nullable();
            $t->boolean('jamban_sehat')->nullable();
            $t->boolean('merokok_keluarga')->nullable();
            $t->string('status_tk_paud', 100)->nullable();
            $t->string('penyakit_penyerta', 255)->nullable();
            $t->string('pjb', 100)->nullable();
            // Riwayat lahir & skrining neonatal
            $t->boolean('riwayat_kek_ibu')->nullable();
            $t->string('tempat_bersalin', 150)->nullable();
            $t->string('jenis_persalinan', 50)->nullable();
            $t->enum('skrining_shk', self::SKRINING)->nullable();
            $t->enum('skrining_shak', self::SKRINING)->nullable();
            $t->enum('skrining_g6pd', self::SKRINING)->nullable();
            $t->enum('pemeriksaan_hepatitis_b', ['reaktif', 'non_reaktif', 'belum'])->nullable();
            $t->text('komplikasi_neonatal')->nullable();
        });

        Schema::table('data_anak', function (Blueprint $t) {
            $t->date('tgl_penanda_ckg')->nullable();
            foreach (['mtbm', 'mtbs', 'skrining_atresia_bilier', 'kn1', 'kn3', 'pkat', 'oralit_zinc'] as $k) {
                $t->boolean($k)->nullable();
            }
            $t->string('pemeriksaan_gigi', 150)->nullable();
            $t->string('rujukan', 150)->nullable();
            $t->string('mt_pangan_lokal', 100)->nullable();
            foreach (['catatan_pengukuran', 'pemeriksaan_lainnya', 'pola_makan', 'pola_asuh', 'intervensi'] as $k) {
                $t->text($k)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('anak', fn (Blueprint $t) => $t->dropColumn(self::ANAK));
        Schema::table('data_anak', fn (Blueprint $t) => $t->dropColumn(self::DATA_ANAK));
    }
};
