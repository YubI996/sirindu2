<?php

namespace Database\Seeders;

use App\Models\Puskesmas;
use App\Models\SekolahMenengahPertama;
use Illuminate\Database\Seeder;

class SekolahMenengahPertamaSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'PUSKESMAS BONTANG UTARA 1' => [
                'SMPN 1 BONTANG',
                'MTs AL-IKHLAS',
                'SMP BAHRUL ULUM',
                'SMP MUHAMMADIYAH',
                'SMP BETLEHEM',
                'SMP BINTANG',
                'SMP MONAMAS',
                'SMPLB NEGERI',
                'SMPLB PERMATA BUNDA',
                'SMPLB BORNEO',
                'SMP AR-RIYADH',
            ],
            'PUSKESMAS BONTANG UTARA 2' => [
                'SMP N 9',
                'SMP NURUL IMAN',
                'SMP LB YPK',
                'SMP Imanuel',
                'SMP I YPL',
                'MTS Al Amin',
                'SMP LB Amali',
            ],
            'PUSKESMAS BONTANG SELATAN 1' => [
                'SMP N 2 BONTANG',
                'SMP N 3 BONTANG',
                'SMP N 7 BONTANG',
                'SMP VIDATRA',
                'SMP DHBS',
                'SMP YKPP',
                'SMP YPPI',
                'MTs AS\'ADIYAH',
            ],
            'PUSKESMAS BONTANG SELATAN 2' => [
                'SMP ADVENT',
                'SMP 8',
                'MTS DDI',
            ],
            'PUSKESMAS BONTANG BARAT' => [
                'SMP Galilea',
                'MTS Al-Hafidz',
                'SMP NEGERI 5',
                'SMP NEGERI 4',
                'SMP YPK',
                'SMP ATSAQIBIYAH',
                'SMP IT YABIS',
                'SMP PERINTIS',
            ],
            'PUSKESMAS BONTANG LESTARI' => [
                'SMPN 6 Bontang',
                'SMPIT DHBS Bontang',
            ],
        ];

        $normalize = fn(string $name): string => preg_replace(
            ['/\bpuskesmas\b/i', '/\bI{2}\b/', '/\bI\b/'],
            ['', '2', '1'],
            strtoupper(trim($name))
        );

        $puskesmasMap = Puskesmas::all()->keyBy(fn($p) => trim(preg_replace('/\s+/', ' ', $normalize($p->name))));

        SekolahMenengahPertama::truncate();

        foreach ($data as $puskesmasName => $sekolahList) {
            $puskesmas = $puskesmasMap->get($puskesmasName);

            foreach ($sekolahList as $namaSekolah) {
                SekolahMenengahPertama::create([
                    'nama'         => $namaSekolah,
                    'id_puskesmas' => $puskesmas?->id,
                ]);
            }
        }
    }
}
