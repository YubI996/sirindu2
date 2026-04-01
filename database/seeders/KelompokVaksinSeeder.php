<?php

namespace Database\Seeders;

use App\Models\KelompokVaksin;
use Illuminate\Database\Seeder;

class KelompokVaksinSeeder extends Seeder
{
    public function run(): void
    {
        $kelompok = [
            [
                'kode' => 'IDL',
                'nama' => 'Imunisasi Dasar Lengkap',
                'usia_pemberian_min' => 0,
                'usia_pemberian_max' => 11,
                'batas_usia_kejar' => 60,
                'keterangan' => 'Imunisasi dasar untuk bayi usia 0-11 bulan, dengan masa kejar hingga 5 tahun',
            ],
            [
                'kode' => 'IBL',
                'nama' => 'Imunisasi Booster Lengkap',
                'usia_pemberian_min' => 12,
                'usia_pemberian_max' => 23,
                'batas_usia_kejar' => 60,
                'keterangan' => 'Imunisasi booster untuk anak usia 12-23 bulan, dengan masa kejar hingga 5 tahun',
            ],
            [
                'kode' => 'ISL',
                'nama' => 'Imunisasi Sekolah Lengkap',
                'usia_pemberian_min' => 84,
                'usia_pemberian_max' => 144,
                'batas_usia_kejar' => null,
                'keterangan' => 'Imunisasi untuk anak usia sekolah dasar (kelas 1-6 SD), tanpa masa kejar',
            ],
        ];

        foreach ($kelompok as $k) {
            KelompokVaksin::updateOrCreate(
                ['kode' => $k['kode']],
                $k
            );
        }
    }
}
