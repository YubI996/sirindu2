<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recreates surveillance_cases table with the new schema if the old schema
 * (from branch fitur-epidemiologi) is detected. The two schemas have
 * incompatible column names and enum values, so ALTER TABLE is not viable.
 *
 * Old schema indicator: column 'id_petugas_input' (only exists in old branch).
 * New schema indicator: column 'deleted_at' (soft deletes, only in new branch).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Already on new schema — nothing to do
        if (Schema::hasTable('surveillance_cases') && Schema::hasColumn('surveillance_cases', 'deleted_at')) {
            return;
        }

        // Drop old schema table if it exists (old schema has id_petugas_input or telepon_pelapor)
        if (Schema::hasTable('surveillance_cases')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Schema::drop('surveillance_cases');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Create with new schema
        Schema::create('surveillance_cases', function (Blueprint $table) {
            $table->id();

            // === SECTION A: IDENTITAS PENDERITA ===
            $table->string('no_registrasi', 30)->unique();
            $table->string('nama_lengkap', 100);
            $table->string('nik', 16)->nullable();
            $table->date('tanggal_lahir');
            $table->unsignedTinyInteger('umur_tahun')->virtualAs('TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE())');
            $table->unsignedTinyInteger('umur_bulan')->virtualAs('TIMESTAMPDIFF(MONTH, tanggal_lahir, CURDATE()) % 12');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('alamat_lengkap', 255);
            $table->foreignId('id_kec')->nullable()->constrained('kecamatan');
            $table->foreignId('id_kel')->nullable()->constrained('kelurahan');
            $table->foreignId('id_rt')->nullable()->constrained('rt');
            $table->string('nama_orang_tua', 100)->nullable();
            $table->string('pekerjaan', 100)->nullable();
            $table->enum('kategori_umur', ['bayi', 'balita', 'anak', 'remaja', 'dewasa', 'lansia'])->nullable();

            // === SECTION B: PELAPOR ===
            $table->string('nama_pelapor', 100)->nullable();
            $table->string('jabatan_pelapor', 100)->nullable();
            $table->string('instansi_pelapor', 150)->nullable();
            $table->string('telp_pelapor', 20)->nullable();

            // === SECTION C: DATA KASUS ===
            $table->foreignId('id_jenis_kasus')->constrained('jenis_kasus_epidemiologi');
            $table->date('tanggal_onset');
            $table->date('tanggal_konsultasi')->nullable();
            $table->date('tanggal_lapor');
            $table->string('tempat_berobat', 150)->nullable();
            $table->string('diagnosa_awal', 200)->nullable();
            $table->text('catatan_kasus')->nullable();

            // === SECTION D: GEJALA KLINIS ===
            $table->boolean('gejala_demam')->default(false);
            $table->boolean('gejala_ruam')->default(false);
            $table->boolean('gejala_batuk')->default(false);
            $table->boolean('gejala_pilek')->default(false);
            $table->boolean('gejala_konjungtivitis')->default(false);
            $table->boolean('gejala_sesak_napas')->default(false);
            $table->boolean('gejala_nyeri_tenggorokan')->default(false);
            $table->boolean('gejala_membran_tenggorokan')->default(false);
            $table->boolean('gejala_kejang')->default(false);
            $table->boolean('gejala_lumpuh_layuh')->default(false);
            $table->boolean('gejala_kaku_rahang')->default(false);
            $table->boolean('gejala_spasme')->default(false);
            $table->boolean('gejala_tali_pusat')->default(false);
            $table->boolean('gejala_diare')->default(false);
            $table->boolean('gejala_muntah')->default(false);
            $table->boolean('gejala_pendarahan')->default(false);
            $table->boolean('gejala_nyeri_sendi')->default(false);
            $table->decimal('suhu_tubuh', 4, 1)->nullable();
            $table->text('gejala_lainnya')->nullable();

            // === SECTION E: RIWAYAT ===
            $table->text('riwayat_imunisasi')->nullable();
            $table->text('riwayat_perjalanan')->nullable();
            $table->text('riwayat_kontak')->nullable();
            $table->text('riwayat_penyakit_dahulu')->nullable();

            // === SECTION F: LABORATORIUM ===
            $table->enum('status_lab', ['belum', 'pending', 'positif', 'negatif', 'tidak_dilakukan'])->default('belum');
            $table->string('jenis_pemeriksaan_lab', 200)->nullable();
            $table->date('tanggal_pengambilan_sampel')->nullable();
            $table->date('tanggal_hasil_lab')->nullable();
            $table->text('hasil_lab')->nullable();

            // === SECTION G: TATA LAKSANA ===
            $table->enum('status_rawat', ['rawat_jalan', 'rawat_inap', 'rujukan', 'tidak_berobat'])->default('rawat_jalan');
            $table->string('nama_faskes', 150)->nullable();
            $table->date('tanggal_masuk_rs')->nullable();
            $table->date('tanggal_keluar_rs')->nullable();
            $table->unsignedSmallInteger('lama_rawat')->virtualAs('CASE WHEN tanggal_masuk_rs IS NOT NULL AND tanggal_keluar_rs IS NOT NULL THEN DATEDIFF(tanggal_keluar_rs, tanggal_masuk_rs) ELSE NULL END');
            $table->text('terapi_pengobatan')->nullable();

            // === SECTION H: STATUS AKHIR ===
            $table->enum('kondisi_akhir', ['sembuh', 'dalam_perawatan', 'meninggal', 'tidak_diketahui'])->default('dalam_perawatan');
            $table->date('tanggal_kondisi_akhir')->nullable();
            $table->string('penyebab_kematian', 200)->nullable();

            // === SECTION I: KONTAK ===
            $table->unsignedSmallInteger('jumlah_kontak_erat')->default(0);
            $table->unsignedSmallInteger('kontak_dipantau')->default(0);
            $table->unsignedSmallInteger('kontak_positif')->default(0);
            $table->text('keterangan_kontak')->nullable();

            // === SECTION J: KLASIFIKASI KASUS ===
            $table->enum('status_kasus', ['suspek', 'probable', 'konfirmasi', 'discarded'])->default('suspek');

            // Metadata
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveillance_cases');
    }
};
