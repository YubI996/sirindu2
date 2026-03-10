<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RumahSakitSeeder
 * Seed 5 Rumah Sakit di Kota Bontang untuk sistem surveilans epidemiologi.
 */
class RumahSakitSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding Rumah Sakit Kota Bontang...');

        // Ambil ID kecamatan Bontang Utara, Bontang Selatan, Bontang Baru
        $kecamatanMap = DB::table('kecamatan')
            ->whereIn('name', ['Bontang Utara', 'Bontang Selatan', 'Bontang Barat'])
            ->pluck('id', 'name');

        $bontangUtara   = $kecamatanMap->get('Bontang Utara');
        $bontangSelatan = $kecamatanMap->get('Bontang Selatan');
        $bontangBarat   = $kecamatanMap->get('Bontang Barat');

        $rumahSakits = [
            [
                'id_kecamatan' => $bontangSelatan,
                'name'         => 'RSUD Taman Husada Bontang',
                'kode_rs'      => 'RS-001',
                'alamat'       => 'Jl. Taman Husada No. 1, Bontang Selatan',
                'telepon'      => '(0548) 21118',
                'jenis_rs'     => 'RSUD',
                'is_active'    => true,
            ],
            [
                'id_kecamatan' => $bontangUtara,
                'name'         => 'RS Pupuk Kaltim',
                'kode_rs'      => 'RS-002',
                'alamat'       => 'Jl. James Simanjuntak No. 1, Bontang Utara',
                'telepon'      => '(0548) 41600',
                'jenis_rs'     => 'RS Swasta',
                'is_active'    => true,
            ],
            [
                'id_kecamatan' => $bontangUtara,
                'name'         => 'RS Siloam Bontang',
                'kode_rs'      => 'RS-003',
                'alamat'       => 'Jl. Raya Bontang Samarinda, Bontang Utara',
                'telepon'      => '(0548) 25555',
                'jenis_rs'     => 'RS Swasta',
                'is_active'    => true,
            ],
            [
                'id_kecamatan' => $bontangBarat,
                'name'         => 'RS Islam Bontang',
                'kode_rs'      => 'RS-004',
                'alamat'       => 'Jl. MT Haryono, Bontang Barat',
                'telepon'      => '(0548) 22345',
                'jenis_rs'     => 'RS Swasta',
                'is_active'    => true,
            ],
            [
                'id_kecamatan' => $bontangUtara,
                'name'         => 'RS Pertamina Bontang',
                'kode_rs'      => 'RS-005',
                'alamat'       => 'Komplek Pertamina, Bontang Utara',
                'telepon'      => '(0548) 41500',
                'jenis_rs'     => 'RS TNI/Polri',
                'is_active'    => true,
            ],
        ];

        foreach ($rumahSakits as $rs) {
            DB::table('rumah_sakits')->updateOrInsert(
                ['kode_rs' => $rs['kode_rs']],
                array_merge($rs, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command?->info('✓ 5 Rumah Sakit selesai di-seed.');
    }
}
