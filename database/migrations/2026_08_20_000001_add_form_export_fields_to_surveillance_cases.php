<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field yang diminta klien pada reviu formulir Agustus 2026 (FP-1, DIF-1, PERT-01).
 *
 * Semua kolom di sini lahir dari coretan pada hasil export: isian yang dicetak
 * kosong karena aplikasi memang belum pernah menanyakannya. Sisanya (mayoritas
 * koreksi) cuma salah pemetaan blade dan tidak butuh kolom baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            // DIF-1 II.13 "Pekerjaan" — "Usul tambah Bag A".
            $table->string('pekerjaan', 150)->nullable()->after('tempat_kerja_sekolah');

            // DIF-1 III.3b "Sakit Tenggorokan" & PERT-01 "Batuk rejan" —
            // "Bag D tambahkan jika belum ada di checklist".
            $table->boolean('gejala_sakit_tenggorokan')->default(false)->after('gejala_apnea');
            $table->boolean('gejala_batuk_rejan')->default(false)->after('gejala_sakit_tenggorokan');
            $table->date('tanggal_sakit_tenggorokan')->nullable()->after('tanggal_apnea');
            $table->date('tanggal_batuk_rejan')->nullable()->after('tanggal_sakit_tenggorokan');

            // FP-1 III kolom "Gangguan rasa raba" — "Perlu ditambahkan di form".
            $table->enum('rasa_raba_tungkai_kanan', ['ya', 'tidak'])->nullable()->after('tanda_lengan_kiri');
            $table->enum('rasa_raba_tungkai_kiri', ['ya', 'tidak'])->nullable()->after('rasa_raba_tungkai_kanan');
            $table->enum('rasa_raba_lengan_kanan', ['ya', 'tidak'])->nullable()->after('rasa_raba_tungkai_kiri');
            $table->enum('rasa_raba_lengan_kiri', ['ya', 'tidak'])->nullable()->after('rasa_raba_lengan_kanan');

            // DIF-1 IV.1 "Tracheostomi" — "Bag D3 tambahkan keterangan centang".
            $table->enum('tracheostomi', ['ya', 'tidak'])->nullable()->after('dosis_ads');

            // PERT-01 "Nomor Rekam Medik" — "Bag G tambahkan".
            $table->string('no_rekam_medik', 50)->nullable()->after('nama_faskes_rawat');
        });
    }

    public function down(): void
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropColumn([
                'pekerjaan',
                'gejala_sakit_tenggorokan',
                'gejala_batuk_rejan',
                'tanggal_sakit_tenggorokan',
                'tanggal_batuk_rejan',
                'rasa_raba_tungkai_kanan',
                'rasa_raba_tungkai_kiri',
                'rasa_raba_lengan_kanan',
                'rasa_raba_lengan_kiri',
                'tracheostomi',
                'no_rekam_medik',
            ]);
        });
    }
};
