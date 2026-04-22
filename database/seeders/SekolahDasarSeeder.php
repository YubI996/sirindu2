<?php

namespace Database\Seeders;

use App\Models\Puskesmas;
use App\Models\SekolahDasar;
use Illuminate\Database\Seeder;

class SekolahDasarSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'PUSKESMAS BONTANG UTARA 1' => [
                'SDN 001 BU',
                'SDN 002 BU',
                'SDN 003 BU',
                'SDN 006 BU',
                'SDN 008 BU',
                'SDN 010 BU',
                'MI JAMI\'ATUL QURRA',
                'SD BINTANG',
                'SD BETLEHEM',
                'SDLB NEGERI',
                'SDLB PERMATA BUNDA',
                'SDLB BORNEO',
                'MI AR-RIYADH',
            ],
            'PUSKESMAS BONTANG UTARA 2' => [
                'SDN 07',
                'SDN 05',
                'SDN 09',
                'SDN 04',
                'SD Nurul Fatah',
                'SD Nurul Iman',
                'SD Darul Ulum',
                'SD LB YPK',
                'SDN 011',
                'SD I YPL',
                'SD Imanuel',
                'SD LB Amali',
            ],
            'PUSKESMAS BONTANG SELATAN 1' => [
                'SD N 001 BTG SELATAN',
                'SD N 002 BTG SELATAN',
                'SD N 009 BTG SELATAN',
                'SD N 012 BTG SELATAN',
                'SD N 013 BTG SELATAN',
                'SD VIDATRA',
                'SD CAHAYA FIKRI',
                'SD ASYAMIL',
                'SD YPPI TANJUNG LAUT',
                'SD YPPI MALAHING',
                'SD MUHAMMADIYAH',
                'MI AS\'ADIYAH',
            ],
            'PUSKESMAS BONTANG SELATAN 2' => [
                'SDN 03',
                'SDN 05',
                'SDN 06',
                'SDN 10',
                'SDN 11',
                'SD ADVENT',
                'SD AISYIYAH',
                'SD SUMBER KASIH',
                'SD TUNAS BANGSA',
                'MI DDI',
            ],
            'PUSKESMAS BONTANG BARAT' => [
                'SD 2 YPK',
                'SD 1 YPK',
                'SD KREATIF MUH',
                'SD IT YABIS',
                'SD ALAM BAITURRAHMAN',
                'SD NEGERI 004',
                'SD NEGERI 001',
                'SD NEGERI 002',
                'SD GALILEA',
                'SD SANTA THERESIA',
            ],
            'PUSKESMAS BONTANG LESTARI' => [
                'SDN 004 Bontang Selatan',
                'SDN 007 Bontang Selatan',
                'SDN 014 Bontang Selatan',
                'SDN 015 Bontang Selatan',
                'SDN 016 Bontang Selatan',
                'SD YPPI Teluk Kadere',
                'MI Al Hijrah',
            ],
        ];

        $normalize = fn(string $name): string => preg_replace(
            ['/\bpuskesmas\b/i', '/\bI{2}\b/', '/\bI\b/'],
            ['', '2', '1'],
            strtoupper(trim($name))
        );

        $puskesmasMap = Puskesmas::all()->keyBy(fn($p) => trim(preg_replace('/\s+/', ' ', $normalize($p->name))));

        SekolahDasar::truncate();

        foreach ($data as $puskesmasName => $sekolahList) {
            $puskesmas = $puskesmasMap->get($puskesmasName);

            foreach ($sekolahList as $namaSekolah) {
                SekolahDasar::create([
                    'nama'         => $namaSekolah,
                    'id_puskesmas' => $puskesmas?->id,
                ]);
            }
        }
    }
}
