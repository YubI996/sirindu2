<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('surveillance_cases', function (Blueprint $table) {
            // Primary key
            $table->id();

            // ===== CATEGORY A: PATIENT IDENTITY (12 fields) =====
            $table->string('no_registrasi', 50)->unique();
            $table->string('nik', 16);
            $table->string('nama_lengkap', 255);
            $table->date('tanggal_lahir');
            $table->integer('umur')->storedAs('TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE())');
            $table->enum('kategori_umur', ['bayi', 'balita', 'anak', 'remaja', 'dewasa', 'lansia']);
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->text('alamat_lengkap');
            $table->unsignedBigInteger('id_kec');
            $table->unsignedBigInteger('id_kel');
            $table->unsignedInteger('id_rt');
            $table->string('no_telepon', 20)->nullable();

            // ===== CATEGORY B: REPORTER IDENTITY (4 fields) =====
            $table->string('nama_pelapor', 255);
            $table->string('jabatan_pelapor', 100)->nullable();
            $table->string('instansi_pelapor', 255)->nullable();
            $table->string('telepon_pelapor', 20)->nullable();

            // ===== CATEGORY C: CASE DATA (7 fields) =====
            $table->unsignedBigInteger('id_jenis_kasus');
            $table->string('kode_icd10', 10)->nullable();
            $table->date('tanggal_onset');
            $table->date('tanggal_konsultasi');
            $table->date('tanggal_lapor');
            $table->enum('sumber_penularan', ['lokal', 'import', 'unknown'])->default('unknown');
            $table->text('lokasi_penularan')->nullable();

            // ===== CATEGORY D: CLINICAL SYMPTOMS (17 fields - boolean) =====
            $table->boolean('gejala_demam')->default(false);
            $table->boolean('gejala_batuk')->default(false);
            $table->boolean('gejala_pilek')->default(false);
            $table->boolean('gejala_sakit_kepala')->default(false);
            $table->boolean('gejala_mual')->default(false);
            $table->boolean('gejala_muntah')->default(false);
            $table->boolean('gejala_diare')->default(false);
            $table->boolean('gejala_ruam')->default(false);
            $table->boolean('gejala_sesak_napas')->default(false);
            $table->boolean('gejala_nyeri_otot')->default(false);
            $table->boolean('gejala_nyeri_sendi')->default(false);
            $table->boolean('gejala_lemas')->default(false);
            $table->boolean('gejala_kehilangan_nafsu_makan')->default(false);
            $table->boolean('gejala_mata_merah')->default(false);
            $table->boolean('gejala_pembengkakan_kelenjar')->default(false);
            $table->boolean('gejala_kejang')->default(false);
            $table->boolean('gejala_penurunan_kesadaran')->default(false);

            // ===== CATEGORY E: HISTORY (4 fields) =====
            $table->text('riwayat_perjalanan')->nullable();
            $table->boolean('riwayat_kontak_kasus')->default(false);
            $table->enum('riwayat_imunisasi', ['lengkap', 'tidak_lengkap', 'tidak_tahu', 'tidak_ada'])->default('tidak_tahu');
            $table->date('tanggal_imunisasi_terakhir')->nullable();

            // ===== CATEGORY F: LABORATORY (5 fields) =====
            $table->enum('status_lab', ['belum_diperiksa', 'proses', 'positif', 'negatif'])->default('belum_diperiksa');
            $table->date('tanggal_pengambilan_spesimen')->nullable();
            $table->string('jenis_spesimen', 100)->nullable();
            $table->text('hasil_lab')->nullable();
            $table->date('tanggal_hasil_lab')->nullable();

            // ===== CATEGORY G: MANAGEMENT (5 fields) =====
            $table->enum('status_rawat', ['rawat_jalan', 'rawat_inap', 'isolasi_mandiri', 'rujuk']);
            $table->string('nama_faskes_rawat', 255);
            $table->date('tanggal_masuk_rawat')->nullable();
            $table->date('tanggal_keluar_rawat')->nullable();
            $table->integer('lama_rawat')->nullable()->storedAs('DATEDIFF(tanggal_keluar_rawat, tanggal_masuk_rawat)');

            // ===== CATEGORY H: FINAL STATUS (3 fields) =====
            $table->enum('kondisi_akhir', ['sembuh', 'meninggal', 'dalam_perawatan', 'pindah', 'unknown'])->default('dalam_perawatan');
            $table->date('tanggal_kondisi_akhir')->nullable();
            $table->text('penyebab_kematian')->nullable();

            // ===== CATEGORY I: CONTACT INVESTIGATION (4 fields) =====
            $table->integer('jumlah_kontak_serumah')->default(0);
            $table->integer('jumlah_kontak_diluar_rumah')->default(0);
            $table->integer('jumlah_kontak_bergejala')->default(0);
            $table->text('tindak_lanjut_kontak')->nullable();

            // ===== CATEGORY J: METADATA (5 fields + audit) =====
            $table->enum('status_kasus', ['suspected', 'probable', 'confirmed', 'discarded'])->default('suspected');
            $table->unsignedBigInteger('id_petugas_input');
            $table->unsignedBigInteger('id_faskes_pelapor')->nullable();
            $table->text('catatan_tambahan')->nullable();

            // Audit fields
            $table->timestamps();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');

            // ===== FOREIGN KEYS =====
            $table->foreign('id_kec')
                  ->references('id')->on('kecamatan')
                  ->onDelete('restrict');

            $table->foreign('id_kel')
                  ->references('id')->on('kelurahan')
                  ->onDelete('restrict');

            $table->foreign('id_rt')
                  ->references('id')->on('rt')
                  ->onDelete('restrict');

            $table->foreign('id_jenis_kasus')
                  ->references('id')->on('jenis_kasus_epidemiologi')
                  ->onDelete('restrict');

            $table->foreign('id_petugas_input')
                  ->references('id')->on('users')
                  ->onDelete('restrict');

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('restrict');

            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->onDelete('restrict');

            // ===== BASIC INDEXES =====
            $table->index('no_registrasi', 'idx_no_registrasi');
            $table->index('nik', 'idx_nik');
            $table->index('tanggal_onset', 'idx_tanggal_onset');
            $table->index('tanggal_lapor', 'idx_tanggal_lapor');
            $table->index('status_kasus', 'idx_status_kasus');
            $table->index('id_jenis_kasus', 'idx_jenis_kasus');
            $table->index('status_rawat', 'idx_status_rawat');
            $table->index('kondisi_akhir', 'idx_kondisi_akhir');
            $table->index('status_lab', 'idx_lab_status');
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('surveillance_cases');
    }
};
