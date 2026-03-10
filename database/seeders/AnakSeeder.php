<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\Puskesmas;
use App\Models\Posyandu;
use Carbon\Carbon;

class AnakSeeder extends Seeder
{
    /**
     * Seed anak table with realistic child data for Kota Bontang.
     */
    public function run(): void
    {
        if (DB::table('anak')->count() > 0) {
            $this->command?->info('anak table already has data, skipping.');
            return;
        }

        $kecamatanList = Kecamatan::all();
        if ($kecamatanList->isEmpty()) {
            $this->command?->warn('Skipping AnakSeeder: kecamatan data not found.');
            return;
        }

        $this->command?->info('Seeding Anak (children) data...');

        $namaLaki = [
            'Ahmad Faiz', 'Muhammad Arkan', 'Andi Rizky', 'Bima Sakti', 'Daffa Pratama',
            'Eka Putra', 'Farel Prayoga', 'Galang Ramadhan', 'Hafiz Nugroho', 'Ilham Maulana',
            'Jihan Akbar', 'Kenji Saputra', 'Luthfi Hakim', 'Naufal Ibrahim', 'Omar Faruq',
            'Prasetyo Adi', 'Raffa Ananda', 'Satria Wibowo', 'Thoriq Firmansyah', 'Umar Zaki',
        ];

        $namaPerempuan = [
            'Aisyah Putri', 'Bunga Citra', 'Cantika Dewi', 'Dinda Ayu', 'Elsa Maharani',
            'Fatimah Zahra', 'Gita Nirmala', 'Hana Safira', 'Intan Permata', 'Jasmine Azzahra',
            'Kayla Rahmawati', 'Luna Safitri', 'Mira Anggraini', 'Nadia Kusuma', 'Olivia Sari',
            'Putri Amelia', 'Qiara Aulia', 'Rina Wulandari', 'Salsabila Dewi', 'Tiara Novita',
        ];

        $namaIbu = [
            'Siti Aminah', 'Dewi Kartika', 'Rina Susanti', 'Yanti Rahayu', 'Nur Hidayah',
            'Fitri Handayani', 'Maya Sari', 'Wulan Dari', 'Sri Wahyuni', 'Ani Lestari',
            'Ratna Dewi', 'Indah Permata', 'Eka Wulandari', 'Tuti Alawiyah', 'Neni Sumarni',
            'Umi Kulsum', 'Lina Marlina', 'Dian Pratiwi', 'Yuli Astuti', 'Rini Purwanti',
        ];

        $namaAyah = [
            'Ahmad Fauzi', 'Budi Santoso', 'Cahyo Prabowo', 'Dedi Kurniawan', 'Eko Prasetyo',
            'Fajar Nugroho', 'Gunawan Wibowo', 'Hendra Kusuma', 'Irfan Hakim', 'Joko Susilo',
            'Kurniawan Adi', 'Lukman Hakim', 'Maulana Akbar', 'Nanda Firmansyah', 'Oscar Handoko',
            'Putra Wijaya', 'Rudi Hartono', 'Syaiful Anwar', 'Taufik Hidayat', 'Wahyu Setiawan',
        ];

        $golda = ['A', 'B', 'AB', 'O'];
        $totalAnak = 30;
        $records = [];

        for ($i = 1; $i <= $totalAnak; $i++) {
            $jk = fake()->randomElement([1, 2]); // 1=L, 2=P
            $nama = $jk === 1
                ? fake()->randomElement($namaLaki)
                : fake()->randomElement($namaPerempuan);

            // Age 0-5 years (balita focus)
            $umurBulan = fake()->numberBetween(0, 60);
            $tglLahir = Carbon::now()->subMonths($umurBulan)->subDays(fake()->numberBetween(0, 28));

            // Geographic
            $kec = $kecamatanList->random();
            $kelList = Kelurahan::where('id_kecamatan', $kec->id)->get();
            if ($kelList->isEmpty()) continue;
            $kel = $kelList->random();
            $rtList = Rt::where('id_kelurahan', $kel->id)->get();
            if ($rtList->isEmpty()) continue;
            $rt = $rtList->random();
            $puskesmasList = Puskesmas::where('id_kecamatan', $kec->id)->get();
            $puskesmas = $puskesmasList->isNotEmpty() ? $puskesmasList->random() : Puskesmas::first();
            $posyanduList = Posyandu::where('id_puskesmas', $puskesmas->id)->get();
            $posyandu = $posyanduList->isNotEmpty() ? $posyanduList->random() : Posyandu::first();

            $records[] = [
                'no_kk' => fake()->numerify('647############'),
                'nik' => fake()->unique()->numerify('647############'),
                'nama' => $nama,
                'nik_ortu' => fake()->numerify('647############'),
                'nama_ibu' => fake()->randomElement($namaIbu),
                'nama_ayah' => fake()->randomElement($namaAyah),
                'jk' => $jk,
                'tempat_lahir' => 'Bontang',
                'tgl_lahir' => $tglLahir->format('Y-m-d'),
                'golda' => fake()->randomElement($golda),
                'anak' => fake()->numberBetween(1, 5),
                'no' => str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 1,
                'id_kec' => $kec->id,
                'id_kel' => $kel->id,
                'id_rt' => $rt->id,
                'id_posyandu' => $posyandu->id,
                'id_puskesmas' => $puskesmas->id,
                'catatan' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('anak')->insert($records);
        $this->command?->info("Berhasil seed {$totalAnak} data anak.");
    }
}
