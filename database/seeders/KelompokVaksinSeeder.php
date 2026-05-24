<?php

namespace Database\Seeders;

use App\Models\JenisVaksin;
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
                'keterangan' => 'Imunisasi dasar bayi 0–11 bulan, kejar hingga 5 tahun',
            ],
            [
                'kode' => 'IBL',
                'nama' => 'Imunisasi Booster Lengkap',
                'usia_pemberian_min' => 12,
                'usia_pemberian_max' => 23,
                'batas_usia_kejar' => 60,
                'keterangan' => 'Imunisasi booster anak 12–23 bulan, kejar hingga 5 tahun',
            ],
            [
                'kode' => 'ISL',
                'nama' => 'Imunisasi Sekolah Lengkap',
                'usia_pemberian_min' => 84,
                'usia_pemberian_max' => 144,
                'batas_usia_kejar' => null,
                'keterangan' => 'BIAS kelas 1–5 SD (usia 6–12 tahun)',
            ],
        ];

        foreach ($kelompok as $k) {
            KelompokVaksin::updateOrCreate(['kode' => $k['kode']], $k);
        }

        // Assign vaccines to their kelompok
        $idl = KelompokVaksin::where('kode', 'IDL')->first();
        $ibl = KelompokVaksin::where('kode', 'IBL')->first();
        $isl = KelompokVaksin::where('kode', 'ISL')->first();

        if ($idl) {
            $idlKodes = [
                'HB0', 'BCG',
                'POLIO1', 'POLIO2', 'POLIO3', 'POLIO4',
                'IPV1', 'IPV2',
                'DPT-HB-HIB1', 'DPT-HB-HIB2', 'DPT-HB-HIB3',
                'PCV1', 'PCV2',
                'RV1', 'RV2',
                'MR1',
            ];
            JenisVaksin::whereIn('kode', $idlKodes)->update(['id_kelompok_vaksin' => $idl->id]);
        }

        if ($ibl) {
            $iblKodes = ['PCV3', 'MR2', 'DPT-HB-HIB4'];
            JenisVaksin::whereIn('kode', $iblKodes)->update(['id_kelompok_vaksin' => $ibl->id]);
        }

        if ($isl) {
            $islKodes = ['DT', 'TD', 'MR-SEKOLAH', 'HPV1', 'HPV2'];
            JenisVaksin::whereIn('kode', $islKodes)->update(['id_kelompok_vaksin' => $isl->id]);
        }
    }
}
