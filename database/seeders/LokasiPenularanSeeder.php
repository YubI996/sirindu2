<?php

namespace Database\Seeders;

use App\Models\LokasiPenularanMaster;
use Illuminate\Database\Seeder;

class LokasiPenularanSeeder extends Seeder
{
    public function run(): void
    {
        $filePath = base_path('docs/list sekolah di bontang.txt');

        if (!file_exists($filePath)) {
            $this->command->error("File not found: {$filePath}");
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $nama = trim($line);
            if ($nama === '') {
                continue;
            }

            LokasiPenularanMaster::updateOrCreate(
                ['nama' => $nama, 'kategori' => 'Sekolah'],
                [
                    'nama' => $nama,
                    'kategori' => 'Sekolah',
                    'is_custom' => false,
                ]
            );
        }
    }
}
