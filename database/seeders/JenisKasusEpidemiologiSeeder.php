<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JenisKasusEpidemiologiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $diseases = [
            [
                'kode_penyakit' => 'CAMPAK_RUBELLA',
                'nama_penyakit' => 'Suspek Campak Rubella',
                'kategori' => 'PD3I',
                'deskripsi' => 'Kasus suspek campak dan rubella yang memerlukan investigasi dan konfirmasi laboratorium',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'DIFTERI_OBS',
                'nama_penyakit' => 'Observasi Difteri',
                'kategori' => 'PD3I',
                'deskripsi' => 'Kasus observasi difteri untuk pemantauan dan penanganan dini',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'AFP',
                'nama_penyakit' => 'AFP (Acute Flaccid Paralysis)',
                'kategori' => 'PD3I',
                'deskripsi' => 'Acute Flaccid Paralysis - indikator surveillance polio',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'PERTUSIS',
                'nama_penyakit' => 'Suspek Pertusis',
                'kategori' => 'PD3I',
                'deskripsi' => 'Suspek pertusis (batuk rejan) yang memerlukan konfirmasi',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'TETANUS_NEO',
                'nama_penyakit' => 'Suspek Tetanus Neonatorum',
                'kategori' => 'PD3I',
                'deskripsi' => 'Suspek tetanus pada bayi baru lahir',
                'is_active' => true
            ],
        ];

        DB::table('jenis_kasus_epidemiologi')->insert($diseases);
    }
}
