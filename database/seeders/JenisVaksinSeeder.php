<?php

namespace Database\Seeders;

use App\Models\JenisVaksin;
use Illuminate\Database\Seeder;

class JenisVaksinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vaksin = [
            // Imunisasi Dasar (0-11 bulan)
            [
                'kode' => 'HB0',
                'nama' => 'Hepatitis B 0',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 0,
                'usia_pemberian_max' => 7,
                'interval_hari' => null,
                'keterangan' => 'Diberikan dalam 24 jam pertama setelah lahir',
            ],
            [
                'kode' => 'BCG',
                'nama' => 'BCG',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 0,
                'usia_pemberian_max' => 30,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada usia 0-1 bulan',
            ],
            [
                'kode' => 'POLIO1',
                'nama' => 'Polio 1',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 0,
                'usia_pemberian_max' => 30,
                'interval_hari' => 28,
                'keterangan' => 'Diberikan pada usia 0-1 bulan',
            ],
            [
                'kode' => 'POLIO2',
                'nama' => 'Polio 2',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 60,
                'usia_pemberian_max' => 90,
                'interval_hari' => 28,
                'keterangan' => 'Diberikan pada usia 2-3 bulan',
            ],
            [
                'kode' => 'POLIO3',
                'nama' => 'Polio 3',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 90,
                'usia_pemberian_max' => 120,
                'interval_hari' => 28,
                'keterangan' => 'Diberikan pada usia 3-4 bulan',
            ],
            [
                'kode' => 'POLIO4',
                'nama' => 'Polio 4',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 120,
                'usia_pemberian_max' => 150,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada usia 4-5 bulan',
            ],
            [
                'kode' => 'DPT-HB-HIB1',
                'nama' => 'DPT-HB-Hib 1',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 60,
                'usia_pemberian_max' => 90,
                'interval_hari' => 28,
                'keterangan' => 'Diberikan pada usia 2-3 bulan',
            ],
            [
                'kode' => 'DPT-HB-HIB2',
                'nama' => 'DPT-HB-Hib 2',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 90,
                'usia_pemberian_max' => 120,
                'interval_hari' => 28,
                'keterangan' => 'Diberikan pada usia 3-4 bulan',
            ],
            [
                'kode' => 'DPT-HB-HIB3',
                'nama' => 'DPT-HB-Hib 3',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 120,
                'usia_pemberian_max' => 150,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada usia 4-5 bulan',
            ],
            [
                'kode' => 'IPV',
                'nama' => 'IPV (Polio Suntik)',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 120,
                'usia_pemberian_max' => 150,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada usia 4-5 bulan',
            ],
            [
                'kode' => 'CAMPAK',
                'nama' => 'Campak',
                'kategori' => 'Imunisasi Dasar',
                'usia_pemberian_min' => 270,
                'usia_pemberian_max' => 330,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada usia 9-11 bulan',
            ],

            // Imunisasi Lanjutan (Baduta)
            [
                'kode' => 'DPT-HB-HIB-LANJUTAN',
                'nama' => 'DPT-HB-Hib Lanjutan',
                'kategori' => 'Imunisasi Lanjutan',
                'usia_pemberian_min' => 540,
                'usia_pemberian_max' => 720,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada usia 18-24 bulan',
            ],
            [
                'kode' => 'MR',
                'nama' => 'MR (Measles Rubella)',
                'kategori' => 'Imunisasi Lanjutan',
                'usia_pemberian_min' => 540,
                'usia_pemberian_max' => 720,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada usia 18-24 bulan',
            ],

            // Imunisasi Anak Sekolah Dasar
            [
                'kode' => 'DT',
                'nama' => 'DT (Difteri Tetanus)',
                'kategori' => 'Imunisasi Anak Sekolah',
                'usia_pemberian_min' => 2160,
                'usia_pemberian_max' => 2520,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada kelas 1 SD (6-7 tahun)',
            ],
            [
                'kode' => 'TD',
                'nama' => 'Td (Tetanus Difteri)',
                'kategori' => 'Imunisasi Anak Sekolah',
                'usia_pemberian_min' => 2520,
                'usia_pemberian_max' => 2880,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada kelas 2 dan 5 SD',
            ],
            [
                'kode' => 'MR-SEKOLAH',
                'nama' => 'MR Anak Sekolah',
                'kategori' => 'Imunisasi Anak Sekolah',
                'usia_pemberian_min' => 2160,
                'usia_pemberian_max' => 2520,
                'interval_hari' => null,
                'keterangan' => 'Diberikan pada kelas 1 SD',
            ],
        ];

        foreach ($vaksin as $v) {
            JenisVaksin::updateOrCreate(
                ['kode' => $v['kode']],
                $v
            );
        }
    }
}
