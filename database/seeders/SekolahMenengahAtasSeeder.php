<?php

namespace Database\Seeders;

use App\Models\Puskesmas;
use App\Models\SekolahMenengahAtas;
use Illuminate\Database\Seeder;

class SekolahMenengahAtasSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'PUSKESMAS BONTANG UTARA 1' => [
                'SMAN 1 BONTANG',
                'MAN BONTANG',
                'SMK CENDIKIA',
                'SMK PUTRA BANGSA',
                'SMA BAHRUL ULUM',
                'SMA MONAMAS',
                'SMK MUHAMMADIYAH',
                'SMK RIGOMASI',
                'SMALB NEGERI',
                'SMALB PERMATA BUNDA',
                'SMALB BORNEO',
                'SMKN 1 BONTANG',
                'SMA ALL TRUCK',
                'SMA HIDAYATULLAH',
            ],
            'PUSKESMAS BONTANG UTARA 2' => [
                'SMA LB YPK',
                'SMA LB Amali',
            ],
            'PUSKESMAS BONTANG SELATAN 1' => [
                'SMA N 2 BONTANG',
                'SMK N 2 BONTANG',
                'SMA VIDATRA',
                'SMA DHBS',
                'SMK YKPP',
                'SMK MARITIM',
            ],
            'PUSKESMAS BONTANG SELATAN 2' => [
                'SMA TUNAS BANGSA',
                'Ma ddi',
            ],
            'PUSKESMAS BONTANG BARAT' => [
                'SMA YPK',
                'SMK Galilea',
                'SMA NEGERI 3',
                'SMK NEGERI 3',
                'SMA IT YABIS',
                'SMK NUSANTARA',
                'SMK IT BANI',
            ],
            'PUSKESMAS BONTANG LESTARI' => [
                'SMKN 4 Bontang',
                'SMAIT DHBS Bontang',
            ],
        ];

        $normalize = fn(string $name): string => preg_replace(
            ['/\bpuskesmas\b/i', '/\bI{2}\b/', '/\bI\b/'],
            ['', '2', '1'],
            strtoupper(trim($name))
        );

        $puskesmasMap = Puskesmas::all()->keyBy(fn($p) => trim(preg_replace('/\s+/', ' ', $normalize($p->name))));

        SekolahMenengahAtas::truncate();

        foreach ($data as $puskesmasName => $sekolahList) {
            $puskesmas = $puskesmasMap->get($puskesmasName);

            foreach ($sekolahList as $namaSekolah) {
                SekolahMenengahAtas::create([
                    'nama'         => $namaSekolah,
                    'id_puskesmas' => $puskesmas?->id,
                ]);
            }
        }
    }
}
