<?php

namespace Database\Seeders;

use App\Models\JenisVaksin;
use App\Models\KelompokVaksin;
use Illuminate\Database\Seeder;

class UpdateJenisVaksinKelompokSeeder extends Seeder
{
    public function run(): void
    {
        $idl = KelompokVaksin::where('kode', 'IDL')->first();
        $ibl = KelompokVaksin::where('kode', 'IBL')->first();
        $isl = KelompokVaksin::where('kode', 'ISL')->first();

        if (!$idl || !$ibl || !$isl) {
            $this->command->error('Kelompok vaksin belum di-seed. Jalankan KelompokVaksinSeeder terlebih dahulu.');
            return;
        }

        // (1) Assign existing 11 IDL vaccines
        $idlKodes = ['HB0', 'BCG', 'POLIO1', 'POLIO2', 'POLIO3', 'POLIO4', 'DPT-HB-HIB1', 'DPT-HB-HIB2', 'DPT-HB-HIB3', 'IPV', 'CAMPAK'];
        JenisVaksin::whereIn('kode', $idlKodes)->update(['id_kelompok_vaksin' => $idl->id]);

        // (2) Add 15 new vaccine records with correct kelompok assignments
        $newVaccines = [
            // IDL - new additions
            [
                'kode' => 'IPV2',
                'nama' => 'IPV 2',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 270,
                'usia_pemberian_max' => 270,
                'interval_hari' => null,
                'keterangan' => 'IPV dosis 2, diberikan pada usia 9 bulan',
                'id_kelompok_vaksin' => $idl->id,
            ],
            [
                'kode' => 'PCV1',
                'nama' => 'PCV 1',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 60,
                'usia_pemberian_max' => 60,
                'interval_hari' => 28,
                'keterangan' => 'Pneumococcal dosis 1, diberikan pada usia 2 bulan',
                'id_kelompok_vaksin' => $idl->id,
            ],
            [
                'kode' => 'PCV2',
                'nama' => 'PCV 2',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 90,
                'usia_pemberian_max' => 90,
                'interval_hari' => null,
                'keterangan' => 'Pneumococcal dosis 2, diberikan pada usia 3 bulan',
                'id_kelompok_vaksin' => $idl->id,
            ],
            [
                'kode' => 'RV1',
                'nama' => 'Rotavirus 1',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 60,
                'usia_pemberian_max' => 60,
                'interval_hari' => 28,
                'keterangan' => 'Rotavirus dosis 1, diberikan pada usia 2 bulan',
                'id_kelompok_vaksin' => $idl->id,
            ],
            [
                'kode' => 'RV2',
                'nama' => 'Rotavirus 2',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 90,
                'usia_pemberian_max' => 90,
                'interval_hari' => 28,
                'keterangan' => 'Rotavirus dosis 2, diberikan pada usia 3 bulan',
                'id_kelompok_vaksin' => $idl->id,
            ],
            [
                'kode' => 'RV3',
                'nama' => 'Rotavirus 3',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 120,
                'usia_pemberian_max' => 120,
                'interval_hari' => null,
                'keterangan' => 'Rotavirus dosis 3, diberikan pada usia 4 bulan',
                'id_kelompok_vaksin' => $idl->id,
            ],

            // IBL - new additions
            [
                'kode' => 'PCV3',
                'nama' => 'PCV 3 (Booster)',
                'kategori' => 'Booster',
                'usia_pemberian_min' => 360,
                'usia_pemberian_max' => 360,
                'interval_hari' => null,
                'keterangan' => 'Pneumococcal booster, diberikan pada usia 12 bulan',
                'id_kelompok_vaksin' => $ibl->id,
            ],
            [
                'kode' => 'DPT-HB-HIB4',
                'nama' => 'DPT-HB-Hib 4 (Booster)',
                'kategori' => 'Booster',
                'usia_pemberian_min' => 540,
                'usia_pemberian_max' => 540,
                'interval_hari' => null,
                'keterangan' => 'Booster pentavalen, diberikan pada usia 18 bulan',
                'id_kelompok_vaksin' => $ibl->id,
            ],
            [
                'kode' => 'MR2',
                'nama' => 'Campak-Rubella (MR) 2',
                'kategori' => 'Booster',
                'usia_pemberian_min' => 540,
                'usia_pemberian_max' => 540,
                'interval_hari' => null,
                'keterangan' => 'MR dosis 2, diberikan pada usia 18 bulan',
                'id_kelompok_vaksin' => $ibl->id,
            ],

            // ISL - new additions
            [
                'kode' => 'MR3',
                'nama' => 'Campak-Rubella (MR) 3',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 2520,
                'usia_pemberian_max' => 2520,
                'interval_hari' => null,
                'keterangan' => 'MR dosis 3, diberikan pada kelas 1 SD (Agustus)',
                'id_kelompok_vaksin' => $isl->id,
            ],
            [
                'kode' => 'DT',
                'nama' => 'DT',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 2520,
                'usia_pemberian_max' => 2520,
                'interval_hari' => null,
                'keterangan' => 'Difteri-Tetanus, diberikan pada kelas 1 SD (November)',
                'id_kelompok_vaksin' => $isl->id,
            ],
            [
                'kode' => 'TD1',
                'nama' => 'Td 1',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 2880,
                'usia_pemberian_max' => 2880,
                'interval_hari' => null,
                'keterangan' => 'Tetanus-difteri dosis 1, diberikan pada kelas 2 SD (November)',
                'id_kelompok_vaksin' => $isl->id,
            ],
            [
                'kode' => 'TD2',
                'nama' => 'Td 2',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 3960,
                'usia_pemberian_max' => 3960,
                'interval_hari' => null,
                'keterangan' => 'Tetanus-difteri dosis 2, diberikan pada kelas 5 SD (November)',
                'id_kelompok_vaksin' => $isl->id,
            ],
            [
                'kode' => 'HPV1',
                'nama' => 'HPV 1',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 3960,
                'usia_pemberian_max' => 3960,
                'interval_hari' => null,
                'keterangan' => 'HPV dosis 1, khusus perempuan, diberikan pada kelas 5 SD (Agustus)',
                'id_kelompok_vaksin' => $isl->id,
            ],
            [
                'kode' => 'HPV2',
                'nama' => 'HPV 2',
                'kategori' => 'Tambahan',
                'usia_pemberian_min' => 4320,
                'usia_pemberian_max' => 4320,
                'interval_hari' => null,
                'keterangan' => 'HPV dosis 2, khusus perempuan, diberikan pada kelas 6 SD (Agustus)',
                'id_kelompok_vaksin' => $isl->id,
            ],
        ];

        foreach ($newVaccines as $v) {
            JenisVaksin::updateOrCreate(
                ['kode' => $v['kode']],
                $v
            );
        }
    }
}
