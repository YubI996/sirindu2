<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SurveillanceCase;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SurveillanceCaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generate realistic surveillance case data for Kota Bontang
     *
     * @return void
     */
    public function run()
    {
        $this->command?->info('Seeding Surveillance Cases...');

        // Get existing reference data
        $diseases = JenisKasusEpidemiologi::where('is_active', true)->get();
        $kecamatanList = Kecamatan::all();
        $users = User::all();

        if ($diseases->isEmpty() || $kecamatanList->isEmpty() || $users->isEmpty()) {
            $this->command?->warn('Skipping SurveillanceCaseSeeder: Required reference data not found.');
            $this->command?->warn('Make sure JenisKasusEpidemiologiSeeder, KecamatanTableSeeder, and UsersTableSeeder have run first.');
            return;
        }

        // Nama-nama realistis Kota Bontang
        $namaLaki = [
            'Ahmad Fauzi', 'Muhammad Rizki', 'Andi Saputra', 'Budi Santoso', 'Dian Permana',
            'Eko Prasetyo', 'Fajar Nugroho', 'Gunawan Wibowo', 'Hendra Kusuma', 'Irfan Hakim',
            'Joko Susilo', 'Kurniawan', 'Lukman Hakim', 'Maulana Ibrahim', 'Nanda Pratama',
            'Oscar Handoko', 'Putra Ramadhan', 'Rudi Hartono', 'Syaiful Anwar', 'Taufik Hidayat',
            'Umar Faruq', 'Wahyu Adi', 'Yusuf Firmansyah', 'Zainal Abidin', 'Arif Rahman',
        ];

        $namaPerempuan = [
            'Siti Nurhaliza', 'Rina Wulandari', 'Dewi Anggraini', 'Fitri Handayani', 'Gina Maharani',
            'Hani Rahmawati', 'Indah Permata', 'Juliana Sari', 'Kartika Putri', 'Lestari Ningsih',
            'Maya Safitri', 'Nadia Kusuma', 'Oktavia Dewi', 'Putri Ayu', 'Qiara Amelia',
            'Ratna Sari', 'Suci Rahayu', 'Tri Wahyuni', 'Umi Kulsum', 'Vina Melinda',
            'Wulan Dari', 'Yanti Susanti', 'Zahra Fadillah', 'Anisa Putri', 'Bella Novita',
        ];

        $namaPelapor = [
            'dr. Andi Pratama', 'dr. Siti Rahmah', 'dr. Budi Setiawan', 'dr. Dewi Kartika',
            'dr. Eko Wahyudi', 'Ns. Fitri Ayu, S.Kep', 'Ns. Gunawan, S.Kep', 'Hendra, SKM',
            'Ir. Indra Lesmana', 'dr. Jamilah', 'dr. Kurnia Sari', 'dr. Lukman',
        ];

        $jabatanPelapor = [
            'Dokter Puskesmas', 'Petugas Surveilans', 'Epidemiolog', 'Bidan Desa',
            'Dokter IGD', 'Perawat', 'Kepala Puskesmas', 'Dokter Spesialis',
        ];

        $instansiPelapor = [
            'Puskesmas Bontang Utara I', 'Puskesmas Bontang Utara II', 'Puskesmas Bontang Selatan I',
            'Puskesmas Bontang Selatan II', 'RSUD Taman Husada Bontang', 'Klinik Pertamina',
            'Puskesmas Bontang Baru', 'Klinik Pupuk Kaltim',
        ];

        $fasyankes = [
            'RSUD Taman Husada Bontang', 'Puskesmas Bontang Utara I', 'Puskesmas Bontang Utara II',
            'Puskesmas Bontang Selatan I', 'Puskesmas Bontang Selatan II', 'Klinik Pertamina Bontang',
            'Klinik Pupuk Kaltim', 'RS Pupuk Kaltim',
        ];

        $kategoriUmurMap = [
            [0, 1, 'bayi'], [1, 5, 'balita'], [5, 12, 'anak'],
            [12, 18, 'remaja'], [18, 60, 'dewasa'], [60, 90, 'lansia'],
        ];

        $sumberPenularan = ['lokal', 'lokal', 'lokal', 'import', 'unknown']; // weighted towards lokal
        $statusKasus = ['suspected', 'suspected', 'probable', 'confirmed', 'confirmed', 'discarded'];
        $statusRawat = ['rawat_jalan', 'rawat_jalan', 'rawat_inap', 'isolasi_mandiri', 'rujuk'];
        $kondisiAkhir = ['dalam_perawatan', 'sembuh', 'sembuh', 'sembuh', 'meninggal', 'pindah'];
        $statusLab = ['belum_diperiksa', 'proses', 'positif', 'positif', 'negatif'];
        $riwayatImunisasi = ['lengkap', 'tidak_lengkap', 'tidak_tahu', 'tidak_ada'];
        $jenisSpesimen = ['Darah', 'Swab Nasofaring', 'Urine', 'Serum', 'Sputum', 'Feses'];

        $cases = [];
        $totalCases = 50;

        for ($i = 1; $i <= $totalCases; $i++) {
            // Pick random gender
            $gender = fake()->randomElement(['L', 'P']);
            $nama = $gender === 'L'
                ? fake()->randomElement($namaLaki)
                : fake()->randomElement($namaPerempuan);

            // Random age and date of birth
            $umurTahun = fake()->numberBetween(0, 80);
            $tanggalLahir = Carbon::now()->subYears($umurTahun)->subDays(fake()->numberBetween(0, 364));
            $kategoriUmur = 'dewasa';
            foreach ($kategoriUmurMap as $range) {
                if ($umurTahun >= $range[0] && $umurTahun < $range[1]) {
                    $kategoriUmur = $range[2];
                    break;
                }
            }

            // Random kecamatan -> kelurahan -> RT
            $kecamatan = $kecamatanList->random();
            $kelurahanList = Kelurahan::where('id_kecamatan', $kecamatan->id)->get();
            if ($kelurahanList->isEmpty()) continue;
            $kelurahan = $kelurahanList->random();
            $rtList = Rt::where('id_kelurahan', $kelurahan->id)->get();
            if ($rtList->isEmpty()) continue;
            $rt = $rtList->random();

            // Dates
            $tanggalOnset = Carbon::now()->subDays(fake()->numberBetween(1, 180));
            $tanggalKonsultasi = (clone $tanggalOnset)->addDays(fake()->numberBetween(0, 5));
            $tanggalLapor = (clone $tanggalKonsultasi)->addDays(fake()->numberBetween(0, 3));

            // Disease type
            $disease = $diseases->random();

            // Status
            $sKasus = fake()->randomElement($statusKasus);
            $sRawat = fake()->randomElement($statusRawat);
            $sKondisi = fake()->randomElement($kondisiAkhir);
            $sLab = fake()->randomElement($statusLab);

            // Treatment dates (for rawat_inap/isolasi_mandiri)
            $tanggalMasukRawat = null;
            $tanggalKeluarRawat = null;
            if (in_array($sRawat, ['rawat_inap', 'isolasi_mandiri'])) {
                $tanggalMasukRawat = (clone $tanggalKonsultasi);
                if ($sKondisi === 'sembuh' || $sKondisi === 'meninggal') {
                    $tanggalKeluarRawat = (clone $tanggalMasukRawat)->addDays(fake()->numberBetween(3, 21));
                    if ($tanggalKeluarRawat->isFuture()) {
                        $tanggalKeluarRawat = Carbon::now();
                    }
                }
            }

            // Lab dates
            $tanggalSpesimen = null;
            $tanggalHasilLab = null;
            $jenisSpesimenVal = null;
            $hasilLab = null;
            if ($sLab !== 'belum_diperiksa') {
                $tanggalSpesimen = (clone $tanggalOnset)->addDays(fake()->numberBetween(1, 5));
                $jenisSpesimenVal = fake()->randomElement($jenisSpesimen);
                if (in_array($sLab, ['positif', 'negatif'])) {
                    $tanggalHasilLab = (clone $tanggalSpesimen)->addDays(fake()->numberBetween(2, 7));
                    if ($tanggalHasilLab->isFuture()) {
                        $tanggalHasilLab = Carbon::now();
                    }
                    $hasilLab = $sLab === 'positif'
                        ? 'Positif ' . $disease->nama_penyakit
                        : 'Negatif - tidak ditemukan agen penyebab';
                }
            }

            // Final status dates
            $tanggalKondisi = null;
            $penyebabKematian = null;
            if (in_array($sKondisi, ['sembuh', 'meninggal'])) {
                $tanggalKondisi = $tanggalKeluarRawat ?? (clone $tanggalOnset)->addDays(fake()->numberBetween(7, 30));
                if ($tanggalKondisi->isFuture()) {
                    $tanggalKondisi = Carbon::today();
                }
                if ($sKondisi === 'meninggal') {
                    $penyebabKematian = 'Komplikasi ' . $disease->nama_penyakit;
                }
            }

            $user = $users->random();

            $cases[] = [
                'no_registrasi' => 'EPI-' . date('Y') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'nik' => fake()->numerify('647############'),
                'nama_lengkap' => $nama,
                'tanggal_lahir' => $tanggalLahir->format('Y-m-d'),
                'kategori_umur' => $kategoriUmur,
                'jenis_kelamin' => $gender,
                'alamat_lengkap' => 'Jl. ' . fake()->streetName() . ' No. ' . fake()->numberBetween(1, 200) . ', RT ' . $rt->name,
                'id_kec' => $kecamatan->id,
                'id_kel' => $kelurahan->id,
                'id_rt' => $rt->id,
                'no_telepon' => '0812' . fake()->numerify('########'),

                'nama_pelapor' => fake()->randomElement($namaPelapor),
                'jabatan_pelapor' => fake()->randomElement($jabatanPelapor),
                'instansi_pelapor' => fake()->randomElement($instansiPelapor),
                'telepon_pelapor' => '0812' . fake()->numerify('########'),

                'id_jenis_kasus' => $disease->id,
                'kode_icd10' => substr($disease->kode_penyakit, 0, 10),
                'tanggal_onset' => $tanggalOnset->format('Y-m-d'),
                'tanggal_konsultasi' => $tanggalKonsultasi->format('Y-m-d'),
                'tanggal_lapor' => $tanggalLapor->format('Y-m-d'),
                'sumber_penularan' => fake()->randomElement($sumberPenularan),
                'lokasi_penularan' => 'Kel. ' . $kelurahan->name . ', Kec. ' . $kecamatan->name,

                // Symptoms - randomized
                'gejala_demam' => fake()->boolean(70),
                'gejala_batuk' => fake()->boolean(50),
                'gejala_pilek' => fake()->boolean(40),
                'gejala_sakit_kepala' => fake()->boolean(45),
                'gejala_mual' => fake()->boolean(30),
                'gejala_muntah' => fake()->boolean(25),
                'gejala_diare' => fake()->boolean(20),
                'gejala_ruam' => fake()->boolean(15),
                'gejala_sesak_napas' => fake()->boolean(20),
                'gejala_nyeri_otot' => fake()->boolean(35),
                'gejala_nyeri_sendi' => fake()->boolean(30),
                'gejala_lemas' => fake()->boolean(60),
                'gejala_kehilangan_nafsu_makan' => fake()->boolean(40),
                'gejala_mata_merah' => fake()->boolean(10),
                'gejala_pembengkakan_kelenjar' => fake()->boolean(10),
                'gejala_kejang' => fake()->boolean(5),
                'gejala_penurunan_kesadaran' => fake()->boolean(5),

                'riwayat_perjalanan' => fake()->boolean(20) ? 'Perjalanan ke ' . fake()->city() . ' ' . fake()->numberBetween(7, 30) . ' hari sebelum onset' : null,
                'riwayat_kontak_kasus' => fake()->boolean(35),
                'riwayat_imunisasi' => fake()->randomElement($riwayatImunisasi),
                'tanggal_imunisasi_terakhir' => fake()->boolean(30) ? Carbon::now()->subYears(fake()->numberBetween(1, 10))->format('Y-m-d') : null,

                'status_lab' => $sLab,
                'tanggal_pengambilan_spesimen' => $tanggalSpesimen?->format('Y-m-d'),
                'jenis_spesimen' => $jenisSpesimenVal,
                'hasil_lab' => $hasilLab,
                'tanggal_hasil_lab' => $tanggalHasilLab?->format('Y-m-d'),

                'status_rawat' => $sRawat,
                'nama_faskes_rawat' => fake()->randomElement($fasyankes),
                'tanggal_masuk_rawat' => $tanggalMasukRawat?->format('Y-m-d'),
                'tanggal_keluar_rawat' => $tanggalKeluarRawat?->format('Y-m-d'),

                'kondisi_akhir' => $sKondisi,
                'tanggal_kondisi_akhir' => $tanggalKondisi?->format('Y-m-d'),
                'penyebab_kematian' => $penyebabKematian,

                'jumlah_kontak_serumah' => fake()->numberBetween(0, 8),
                'jumlah_kontak_diluar_rumah' => fake()->numberBetween(0, 15),
                'jumlah_kontak_bergejala' => fake()->numberBetween(0, 5),
                'tindak_lanjut_kontak' => fake()->boolean(40) ? 'Kontak erat dipantau selama 14 hari. ' . fake()->numberBetween(0, 3) . ' kontak dirujuk ke puskesmas.' : null,

                'status_kasus' => $sKasus,
                'id_petugas_input' => $user->id,
                'id_faskes_pelapor' => null,
                'catatan_tambahan' => fake()->boolean(25) ? 'Pasien memiliki komorbid ' . fake()->randomElement(['hipertensi', 'diabetes', 'asma', 'penyakit jantung', 'gagal ginjal']) : null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => $tanggalLapor,
                'updated_at' => $tanggalLapor,
            ];
        }

        // Insert in chunks
        foreach (array_chunk($cases, 25) as $chunk) {
            DB::table('surveillance_cases')->insert($chunk);
        }

        $this->command?->info("Berhasil seed {$totalCases} kasus surveillance.");
    }
}
