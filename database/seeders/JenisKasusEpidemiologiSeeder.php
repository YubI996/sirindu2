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
            // ===== PRIORITY SURVEILLANCE DISEASES (as specified) =====
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

            // ===== ADDITIONAL COMMON SURVEILLANCE DISEASES =====
            [
                'kode_penyakit' => 'DBD',
                'nama_penyakit' => 'Demam Berdarah Dengue',
                'kategori' => 'vector_borne',
                'deskripsi' => 'Demam berdarah dengue (DBD) yang ditularkan melalui nyamuk Aedes',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'CHIKUNGUNYA',
                'nama_penyakit' => 'Chikungunya',
                'kategori' => 'vector_borne',
                'deskripsi' => 'Penyakit virus yang ditularkan melalui nyamuk Aedes',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'MALARIA',
                'nama_penyakit' => 'Malaria',
                'kategori' => 'vector_borne',
                'deskripsi' => 'Penyakit yang ditularkan melalui gigitan nyamuk Anopheles',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'TB',
                'nama_penyakit' => 'Tuberkulosis',
                'kategori' => 'menular_langsung',
                'deskripsi' => 'Tuberkulosis (TB) - penyakit menular yang umumnya menyerang paru-paru',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'HEPATITIS',
                'nama_penyakit' => 'Hepatitis',
                'kategori' => 'menular_langsung',
                'deskripsi' => 'Peradangan hati yang dapat disebabkan virus hepatitis A, B, C, D, atau E',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'HIV',
                'nama_penyakit' => 'HIV/AIDS',
                'kategori' => 'menular_langsung',
                'deskripsi' => 'Human Immunodeficiency Virus dan Acquired Immunodeficiency Syndrome',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'DIARE',
                'nama_penyakit' => 'Diare Akut',
                'kategori' => 'menular_langsung',
                'deskripsi' => 'Diare akut yang dapat menyebabkan dehidrasi',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'PNEUMONIA',
                'nama_penyakit' => 'Pneumonia',
                'kategori' => 'menular_langsung',
                'deskripsi' => 'Infeksi yang menyebabkan peradangan pada kantong udara di paru-paru',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'RABIES',
                'nama_penyakit' => 'Rabies',
                'kategori' => 'zoonosis',
                'deskripsi' => 'Penyakit virus yang ditularkan melalui gigitan hewan yang terinfeksi',
                'is_active' => true
            ],
            [
                'kode_penyakit' => 'COVID19',
                'nama_penyakit' => 'COVID-19',
                'kategori' => 'menular_langsung',
                'deskripsi' => 'Coronavirus Disease 2019 yang disebabkan virus SARS-CoV-2',
                'is_active' => true
            ],
        ];

        DB::table('jenis_kasus_epidemiologi')->insert($diseases);
    }
}
