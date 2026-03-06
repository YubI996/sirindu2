<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds all fields from Google Form epidemiologi surveillance response columns.
     */
    public function up()
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {

            // ===== IDENTITAS PASIEN — Tambahan =====
            $table->string('tempat_kerja_sekolah', 255)->nullable()->after('no_telepon')
                  ->comment('Tempat Kerja / Sekolah / PAUD / TPA');
            $table->string('nama_orang_tua', 255)->nullable()->after('tempat_kerja_sekolah');
            $table->string('no_hp_orang_tua', 20)->nullable()->after('nama_orang_tua');
            $table->string('provinsi', 100)->nullable()->after('no_hp_orang_tua')
                  ->comment('Default: Kalimantan Timur');
            $table->string('kab_kota', 100)->nullable()->after('provinsi')
                  ->comment('Default: Kota Balikpapan');

            // ===== PELAPOR & PENYIDIKAN — Tambahan =====
            $table->string('wilker_puskesmas', 255)->nullable()->after('telepon_pelapor')
                  ->comment('Wilker Puskesmas sesuai lokasi kelurahan kasus');
            $table->date('tanggal_terima_laporan')->nullable()->after('wilker_puskesmas');
            $table->date('tanggal_penyidikan')->nullable()->after('tanggal_terima_laporan');

            // ===== GEJALA KLINIS — Tambahan =====
            $table->date('tanggal_demam')->nullable()->after('gejala_penurunan_kesadaran')
                  ->comment('Tanggal mulai demam');
            $table->boolean('gejala_adenopathy')->default(false)->after('tanggal_demam');
            $table->boolean('gejala_arthralgia')->default(false)->after('gejala_adenopathy');
            $table->boolean('gejala_kehamilan')->default(false)->after('gejala_arthralgia')
                  ->comment('Kehamilan sebagai gejala/faktor risiko');
            $table->text('gejala_lainnya')->nullable()->after('gejala_kehamilan')
                  ->comment('Gejala lain dalam teks bebas');

            // Gejala spesifik Difteri
            $table->date('tanggal_leher_bengkak')->nullable()->after('gejala_lainnya');
            $table->date('tanggal_sesak_nafas')->nullable()->after('tanggal_leher_bengkak');
            $table->date('tanggal_pseudomembran')->nullable()->after('tanggal_sesak_nafas');

            // Pertusis
            $table->date('tanggal_apnea')->nullable()->after('tanggal_pseudomembran');

            // ===== KOMPLIKASI (semua boolean) =====
            $table->boolean('komplikasi_diare')->default(false)->after('tanggal_apnea');
            $table->boolean('komplikasi_kebutaan')->default(false)->after('komplikasi_diare');
            $table->boolean('komplikasi_pneumonia')->default(false)->after('komplikasi_kebutaan');
            $table->boolean('komplikasi_malnutrisi')->default(false)->after('komplikasi_pneumonia');
            $table->boolean('komplikasi_bronchopneumonia')->default(false)->after('komplikasi_malnutrisi');
            $table->boolean('komplikasi_otitis_media')->default(false)->after('komplikasi_bronchopneumonia');
            $table->boolean('komplikasi_encephalitis')->default(false)->after('komplikasi_otitis_media');
            $table->boolean('komplikasi_ulkus_mukosa_mulut')->default(false)->after('komplikasi_encephalitis');

            // ===== VITAMIN A & STATUS GIZI =====
            $table->enum('vitamin_a', ['ya', 'tidak', 'tidak_tahu'])->nullable()->after('komplikasi_ulkus_mukosa_mulut')
                  ->comment('Apakah pasien diberikan Vitamin A?');
            $table->decimal('berat_badan', 5, 1)->nullable()->after('vitamin_a')
                  ->comment('Berat Badan (Kg)');
            $table->decimal('tinggi_badan', 5, 1)->nullable()->after('berat_badan')
                  ->comment('Tinggi Badan (CM)');
            $table->enum('status_gizi', ['baik', 'kurang', 'buruk', 'lebih'])->nullable()->after('tinggi_badan');

            // ===== PENGOBATAN / TERAPI =====
            $table->string('jenis_antibiotik', 255)->nullable()->after('status_gizi')
                  ->comment('Jenis Antibiotik yang diberikan');
            $table->string('dosis_ads', 255)->nullable()->after('jenis_antibiotik')
                  ->comment('Pemberian Dosis Anti Difteri Serum (ADS)');
            $table->text('obat_lainnya')->nullable()->after('dosis_ads');

            // ===== AFP/POLIO — Riwayat Sakit =====
            $table->enum('kelumpuhan_akut', ['ya', 'tidak'])->nullable()->after('obat_lainnya')
                  ->comment('Apakah kelemahan/kelumpuhan sifatnya akut (1-14 hari)?');
            $table->enum('kelumpuhan_flaccid', ['ya', 'tidak'])->nullable()->after('kelumpuhan_akut')
                  ->comment('Apakah kelemahan/kelumpuhan sifatnya layuh (flaccid)?');
            $table->enum('kelumpuhan_rudapaksa', ['ya', 'tidak'])->nullable()->after('kelumpuhan_flaccid')
                  ->comment('Apakah kelemahan/kelumpuhan disebabkan rudapaksa?');

            // ===== DIAGNOSIS & PEMERIKSAAN FISIK =====
            $table->string('diagnosis', 255)->nullable()->after('kelumpuhan_rudapaksa');
            $table->string('tanda_tungkai_kanan', 255)->nullable()->after('diagnosis');
            $table->string('tanda_tungkai_kiri', 255)->nullable()->after('tanda_tungkai_kanan');
            $table->string('tanda_lengan_kanan', 255)->nullable()->after('tanda_tungkai_kiri');
            $table->string('tanda_lengan_kiri', 255)->nullable()->after('tanda_lengan_kanan');
            $table->unsignedTinyInteger('kekuatan_otot')->nullable()->after('tanda_lengan_kiri')
                  ->comment('0-5 scale');
            $table->text('lokasi_kelemahan_lain')->nullable()->after('kekuatan_otot');
            $table->text('tanda_penyakit_observasi')->nullable()->after('lokasi_kelemahan_lain')
                  ->comment('Tanda penyakit yang dapat diobservasi');

            // ===== KONTAK POLIO =====
            $table->enum('kontak_polio_oral', ['ya', 'tidak', 'tidak_tahu'])->nullable()->after('tanda_penyakit_observasi')
                  ->comment('Dalam 75 hari terakhir, kontak dengan anak yang baru imunisasi polio oral?');

            // ===== SANITASI (AFP specific) =====
            $table->enum('jamban_sendiri', ['ya', 'tidak'])->nullable()->after('kontak_polio_oral');
            $table->enum('jamban_saluran_kedap', ['ya', 'tidak'])->nullable()->after('jamban_sendiri');
            $table->string('jenis_jamban', 100)->nullable()->after('jamban_saluran_kedap');
            $table->enum('selalu_gunakan_jamban', ['ya', 'tidak', 'kadang_kadang'])->nullable()->after('jenis_jamban');
            $table->string('pembuangan_diapers', 255)->nullable()->after('selalu_gunakan_jamban')
                  ->comment('Pembuangan diapers jika kasus AFP masih menggunakan diapers');

            // ===== DOKTER =====
            $table->string('nama_dokter', 255)->nullable()->after('pembuangan_diapers');
            $table->string('no_telp_dokter', 20)->nullable()->after('nama_dokter');
            $table->string('diagnosis_dokter', 255)->nullable()->after('no_telp_dokter');

            // ===== RIWAYAT IMUNISASI DETAIL =====
            $table->string('imunisasi_1', 255)->nullable()->after('tanggal_imunisasi_terakhir')
                  ->comment('MR1 - 9 bulan / DPT-HB-Hib 1,2,3 / OPV1');
            $table->string('imunisasi_2', 255)->nullable()->after('imunisasi_1')
                  ->comment('MR2 - 18 bulan / DPT-HB-Hib Booster / OPV2');
            $table->string('imunisasi_3', 255)->nullable()->after('imunisasi_2')
                  ->comment('MR3 - kelas 1 SD / DT kelas 1 / OPV2');
            $table->string('imunisasi_4', 255)->nullable()->after('imunisasi_3')
                  ->comment('MMR / TD kelas 2 dan 5');
            $table->string('imunisasi_5', 255)->nullable()->after('imunisasi_4')
                  ->comment('Tambahan: kampanye/ORI/SUBPIN/PIN');
            $table->string('sumber_informasi_imunisasi', 255)->nullable()->after('imunisasi_5');
            $table->text('alasan_imunisasi_tidak_lengkap')->nullable()->after('sumber_informasi_imunisasi');

            // ===== TEMPAT BEROBAT =====
            $table->text('tempat_berobat')->nullable()->after('diagnosis_dokter')
                  ->comment('JSON array of tempat berobat checkboxes');
            $table->string('nama_rs', 255)->nullable()->after('tempat_berobat');
            $table->date('tanggal_kunjungan_rs')->nullable()->after('nama_rs');
            $table->string('nama_fktp', 255)->nullable()->after('tanggal_kunjungan_rs')
                  ->comment('Puskesmas / Klinik / Praktek Dokter');
            $table->date('tanggal_kunjungan_fktp')->nullable()->after('nama_fktp');
            $table->string('nama_pengobatan_tradisional', 255)->nullable()->after('tanggal_kunjungan_fktp');
            $table->date('tanggal_kunjungan_tradisional')->nullable()->after('nama_pengobatan_tradisional');

            // ===== LABORATORIUM — Spesimen Tambahan =====
            $table->string('jenis_spesimen_2', 100)->nullable()->after('tanggal_hasil_lab');
            $table->date('tanggal_spesimen_2')->nullable()->after('jenis_spesimen_2');
            $table->string('jenis_spesimen_3', 100)->nullable()->after('tanggal_spesimen_2');
            $table->date('tanggal_spesimen_3')->nullable()->after('jenis_spesimen_3');

            // ===== KONTAK & PERJALANAN — Tambahan =====
            $table->enum('keluarga_sakit_sama', ['ya', 'tidak'])->nullable()->after('tindak_lanjut_kontak')
                  ->comment('Apakah ada anggota keluarga/masyarakat sekitar yang sakit sama?');
            $table->unsignedInteger('jumlah_keluarga_sakit')->nullable()->after('keluarga_sakit_sama');
            $table->enum('riwayat_bepergian', ['ya', 'tidak'])->nullable()->after('jumlah_keluarga_sakit')
                  ->comment('Sebelum sakit, pernah bepergian ke luar kab/prov/negeri?');
            $table->string('lokasi_bepergian', 255)->nullable()->after('riwayat_bepergian');
            $table->date('tanggal_bepergian')->nullable()->after('lokasi_bepergian');

            // ===== TETANUS NEONATORUM =====
            $table->string('lama_tinggal_desa', 100)->nullable()->after('tanggal_bepergian')
                  ->comment('Sudah berapa lama Ibu tinggal di desa ini?');
            $table->enum('bayi_lahir_hidup', ['ya', 'tidak'])->nullable()->after('lama_tinggal_desa');
            $table->unsignedInteger('umur_bayi_meninggal_hari')->nullable()->after('bayi_lahir_hidup');
            $table->enum('bayi_menangis_lahir', ['ya', 'tidak', 'tidak_tahu'])->nullable()->after('umur_bayi_meninggal_hari');
            $table->enum('tanda_kelahiran_hidup', ['ya', 'tidak', 'tidak_tahu'])->nullable()->after('bayi_menangis_lahir')
                  ->comment('Apakah terlihat tanda-tanda kelahiran hidup (gerakan)?');
            $table->enum('bayi_bisa_menyusu', ['ya', 'tidak', 'tidak_tahu'])->nullable()->after('tanda_kelahiran_hidup');
            $table->enum('bayi_mulut_mencucu', ['ya', 'tidak', 'tidak_tahu'])->nullable()->after('bayi_bisa_menyusu')
                  ->comment('Apakah 3 hari kemudian mulut bayi mencucu dan tidak bisa menyusu?');
            $table->enum('bayi_mudah_kejang', ['ya', 'tidak', 'tidak_tahu'])->nullable()->after('bayi_mulut_mencucu')
                  ->comment('Apakah bayi mudah kejang jika disentuh/terkena sinar/bunyi?');
            $table->unsignedTinyInteger('jumlah_kunjungan_anc')->nullable()->after('bayi_mudah_kejang')
                  ->comment('Berapa kali kunjungan ibu hamil (ANC)?');
            $table->string('tempat_pemeriksaan_hamil', 255)->nullable()->after('jumlah_kunjungan_anc');
            $table->string('pemeriksa_kehamilan', 255)->nullable()->after('tempat_pemeriksaan_hamil')
                  ->comment('Bidan, Dokter, dll');
            $table->string('tempat_persalinan', 255)->nullable()->after('pemeriksa_kehamilan');
            $table->unsignedTinyInteger('usia_kehamilan_bulan')->nullable()->after('tempat_persalinan');
            $table->string('penolong_persalinan', 255)->nullable()->after('usia_kehamilan_bulan');
            $table->string('alat_potong_tali_pusat', 255)->nullable()->after('penolong_persalinan');
            $table->string('perawatan_tali_pusat', 255)->nullable()->after('alat_potong_tali_pusat');
            $table->string('keadaan_ibu_saat_ini', 255)->nullable()->after('perawatan_tali_pusat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('surveillance_cases', function (Blueprint $table) {
            $table->dropColumn([
                // Identitas pasien tambahan
                'tempat_kerja_sekolah', 'nama_orang_tua', 'no_hp_orang_tua', 'provinsi', 'kab_kota',
                // Pelapor & Penyidikan
                'wilker_puskesmas', 'tanggal_terima_laporan', 'tanggal_penyidikan',
                // Gejala tambahan
                'tanggal_demam', 'gejala_adenopathy', 'gejala_arthralgia', 'gejala_kehamilan', 'gejala_lainnya',
                'tanggal_leher_bengkak', 'tanggal_sesak_nafas', 'tanggal_pseudomembran', 'tanggal_apnea',
                // Komplikasi
                'komplikasi_diare', 'komplikasi_kebutaan', 'komplikasi_pneumonia', 'komplikasi_malnutrisi',
                'komplikasi_bronchopneumonia', 'komplikasi_otitis_media', 'komplikasi_encephalitis',
                'komplikasi_ulkus_mukosa_mulut',
                // Vitamin A & Gizi
                'vitamin_a', 'berat_badan', 'tinggi_badan', 'status_gizi',
                // Pengobatan
                'jenis_antibiotik', 'dosis_ads', 'obat_lainnya',
                // AFP/Polio
                'kelumpuhan_akut', 'kelumpuhan_flaccid', 'kelumpuhan_rudapaksa',
                // Diagnosis & Pemeriksaan
                'diagnosis', 'tanda_tungkai_kanan', 'tanda_tungkai_kiri', 'tanda_lengan_kanan',
                'tanda_lengan_kiri', 'kekuatan_otot', 'lokasi_kelemahan_lain', 'tanda_penyakit_observasi',
                // Kontak Polio
                'kontak_polio_oral',
                // Sanitasi
                'jamban_sendiri', 'jamban_saluran_kedap', 'jenis_jamban', 'selalu_gunakan_jamban',
                'pembuangan_diapers',
                // Dokter
                'nama_dokter', 'no_telp_dokter', 'diagnosis_dokter',
                // Tempat Berobat
                'tempat_berobat', 'nama_rs', 'tanggal_kunjungan_rs', 'nama_fktp',
                'tanggal_kunjungan_fktp', 'nama_pengobatan_tradisional', 'tanggal_kunjungan_tradisional',
                // Imunisasi detail
                'imunisasi_1', 'imunisasi_2', 'imunisasi_3', 'imunisasi_4', 'imunisasi_5',
                'sumber_informasi_imunisasi', 'alasan_imunisasi_tidak_lengkap',
                // Lab tambahan
                'jenis_spesimen_2', 'tanggal_spesimen_2', 'jenis_spesimen_3', 'tanggal_spesimen_3',
                // Kontak & Perjalanan
                'keluarga_sakit_sama', 'jumlah_keluarga_sakit', 'riwayat_bepergian',
                'lokasi_bepergian', 'tanggal_bepergian',
                // Tetanus Neonatorum
                'lama_tinggal_desa', 'bayi_lahir_hidup', 'umur_bayi_meninggal_hari',
                'bayi_menangis_lahir', 'tanda_kelahiran_hidup', 'bayi_bisa_menyusu',
                'bayi_mulut_mencucu', 'bayi_mudah_kejang', 'jumlah_kunjungan_anc',
                'tempat_pemeriksaan_hamil', 'pemeriksa_kehamilan', 'tempat_persalinan',
                'usia_kehamilan_bulan', 'penolong_persalinan', 'alat_potong_tali_pusat',
                'perawatan_tali_pusat', 'keadaan_ibu_saat_ini',
            ]);
        });
    }
};
