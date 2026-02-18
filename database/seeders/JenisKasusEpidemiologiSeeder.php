<?php

namespace Database\Seeders;

use App\Models\JenisKasusEpidemiologi;
use Illuminate\Database\Seeder;

class JenisKasusEpidemiologiSeeder extends Seeder
{
    public function run()
    {
        $data = [
            // PD3I - Penyakit Yang Dapat Dicegah Dengan Imunisasi (Priority)
            ['kode_penyakit' => 'EPI-001', 'nama_penyakit' => 'Suspek Campak Rubella',       'kategori' => 'PD3I',             'keterangan' => 'Measles/Rubella suspect case'],
            ['kode_penyakit' => 'EPI-002', 'nama_penyakit' => 'Observasi Difteri',            'kategori' => 'PD3I',             'keterangan' => 'Diphtheria observation'],
            ['kode_penyakit' => 'EPI-003', 'nama_penyakit' => 'AFP (Acute Flaccid Paralysis)','kategori' => 'PD3I',             'keterangan' => 'Kelumpuhan Layuh Mendadak - Polio suspect'],
            ['kode_penyakit' => 'EPI-004', 'nama_penyakit' => 'Suspek Pertusis',              'kategori' => 'PD3I',             'keterangan' => 'Whooping cough suspect'],
            ['kode_penyakit' => 'EPI-005', 'nama_penyakit' => 'Suspek Tetanus Neonatorum',   'kategori' => 'PD3I',             'keterangan' => 'Neonatal tetanus suspect'],
            // Menular Langsung
            ['kode_penyakit' => 'EPI-006', 'nama_penyakit' => 'Tuberkulosis (TB)',            'kategori' => 'menular_langsung', 'keterangan' => null],
            ['kode_penyakit' => 'EPI-007', 'nama_penyakit' => 'HIV/AIDS',                    'kategori' => 'menular_langsung', 'keterangan' => null],
            ['kode_penyakit' => 'EPI-008', 'nama_penyakit' => 'Hepatitis',                   'kategori' => 'menular_langsung', 'keterangan' => null],
            ['kode_penyakit' => 'EPI-009', 'nama_penyakit' => 'Diare Akut',                  'kategori' => 'menular_langsung', 'keterangan' => null],
            ['kode_penyakit' => 'EPI-010', 'nama_penyakit' => 'Pneumonia',                   'kategori' => 'menular_langsung', 'keterangan' => null],
            // Vector Borne
            ['kode_penyakit' => 'EPI-011', 'nama_penyakit' => 'Demam Berdarah Dengue (DBD)', 'kategori' => 'vector_borne',     'keterangan' => null],
            ['kode_penyakit' => 'EPI-012', 'nama_penyakit' => 'Chikungunya',                 'kategori' => 'vector_borne',     'keterangan' => null],
            ['kode_penyakit' => 'EPI-013', 'nama_penyakit' => 'Malaria',                     'kategori' => 'vector_borne',     'keterangan' => null],
            // Zoonosis
            ['kode_penyakit' => 'EPI-014', 'nama_penyakit' => 'Rabies',                      'kategori' => 'zoonosis',         'keterangan' => null],
            // Lainnya
            ['kode_penyakit' => 'EPI-015', 'nama_penyakit' => 'COVID-19',                    'kategori' => 'lainnya',          'keterangan' => null],
        ];

        foreach ($data as $item) {
            JenisKasusEpidemiologi::firstOrCreate(
                ['kode_penyakit' => $item['kode_penyakit']],
                array_merge($item, ['is_active' => true])
            );
        }
    }
}
