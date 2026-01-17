<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MassiveChildDataSeeder extends Seeder
{
    // WHO Z-Score reference ranges for realistic data generation
    private $whoReference = [
        // Format: [age_months => [median_weight, sd_weight, median_height, sd_height]]
        'male' => [
            0 => [3.3, 0.4, 49.9, 1.9],
            1 => [4.5, 0.5, 54.7, 2.0],
            2 => [5.6, 0.6, 58.4, 2.1],
            3 => [6.4, 0.6, 61.4, 2.1],
            4 => [7.0, 0.7, 63.9, 2.2],
            5 => [7.5, 0.7, 65.9, 2.2],
            6 => [7.9, 0.8, 67.6, 2.3],
            7 => [8.3, 0.8, 69.2, 2.3],
            8 => [8.6, 0.8, 70.6, 2.3],
            9 => [8.9, 0.9, 72.0, 2.4],
            10 => [9.2, 0.9, 73.3, 2.4],
            11 => [9.4, 0.9, 74.5, 2.4],
            12 => [9.6, 0.9, 75.7, 2.5],
            15 => [10.3, 1.0, 79.1, 2.6],
            18 => [10.9, 1.0, 82.3, 2.7],
            21 => [11.5, 1.1, 85.1, 2.8],
            24 => [12.2, 1.2, 87.8, 2.9],
            30 => [13.3, 1.3, 92.3, 3.1],
            36 => [14.3, 1.4, 96.1, 3.3],
            42 => [15.3, 1.5, 99.7, 3.5],
            48 => [16.3, 1.6, 103.3, 3.6],
            54 => [17.3, 1.7, 106.7, 3.8],
            60 => [18.3, 1.8, 110.0, 4.0],
        ],
        'female' => [
            0 => [3.2, 0.4, 49.1, 1.9],
            1 => [4.2, 0.5, 53.7, 1.9],
            2 => [5.1, 0.5, 57.1, 2.0],
            3 => [5.8, 0.6, 59.8, 2.0],
            4 => [6.4, 0.6, 62.1, 2.1],
            5 => [6.9, 0.7, 64.0, 2.1],
            6 => [7.3, 0.7, 65.7, 2.2],
            7 => [7.6, 0.8, 67.3, 2.2],
            8 => [7.9, 0.8, 68.7, 2.2],
            9 => [8.2, 0.8, 70.1, 2.3],
            10 => [8.5, 0.9, 71.5, 2.3],
            11 => [8.7, 0.9, 72.8, 2.4],
            12 => [8.9, 0.9, 74.0, 2.4],
            15 => [9.6, 1.0, 77.5, 2.5],
            18 => [10.2, 1.0, 80.7, 2.6],
            21 => [10.9, 1.1, 83.7, 2.7],
            24 => [11.5, 1.1, 86.4, 2.9],
            30 => [12.7, 1.3, 91.2, 3.0],
            36 => [13.9, 1.4, 95.1, 3.2],
            42 => [15.0, 1.5, 98.9, 3.4],
            48 => [16.1, 1.6, 102.7, 3.6],
            54 => [17.2, 1.7, 106.2, 3.7],
            60 => [18.2, 1.8, 109.4, 3.9],
        ],
    ];

    private $namaLakiLaki = [
        'Muhammad', 'Ahmad', 'Rizki', 'Fajar', 'Dimas', 'Andi', 'Budi', 'Candra', 'Dani', 'Eko',
        'Faisal', 'Galih', 'Hendra', 'Irfan', 'Joko', 'Kevin', 'Lutfi', 'Maulana', 'Naufal', 'Omar',
        'Putra', 'Rafi', 'Satria', 'Taufik', 'Umar', 'Vino', 'Wahyu', 'Yusuf', 'Zaki', 'Arif',
        'Bayu', 'Dafa', 'Gilang', 'Hafiz', 'Ihsan', 'Jihan', 'Krisna', 'Lukman', 'Malik', 'Nabil',
        'Rangga', 'Syahrul', 'Teguh', 'Wira', 'Yoga', 'Zainal', 'Aditya', 'Bima', 'Cahyo', 'Dwi'
    ];

    private $namaPerempuan = [
        'Aisyah', 'Bunga', 'Cantika', 'Dewi', 'Eka', 'Fitri', 'Gita', 'Hana', 'Indah', 'Jasmine',
        'Kartika', 'Lestari', 'Maya', 'Nadia', 'Oktavia', 'Putri', 'Qori', 'Rina', 'Sari', 'Tari',
        'Umi', 'Vina', 'Wulan', 'Yanti', 'Zahra', 'Amelia', 'Bella', 'Citra', 'Dina', 'Elvira',
        'Fatimah', 'Gina', 'Helena', 'Intan', 'Julia', 'Kania', 'Laila', 'Melati', 'Nabila', 'Olive',
        'Puspita', 'Ratna', 'Salma', 'Tiara', 'Ulfa', 'Viola', 'Widya', 'Xena', 'Yolanda', 'Zelda'
    ];

    private $namaBelakang = [
        'Pratama', 'Saputra', 'Wijaya', 'Kusuma', 'Nugroho', 'Hidayat', 'Rahman', 'Hakim', 'Putra', 'Sari',
        'Utami', 'Wati', 'Ningrum', 'Lestari', 'Anggraini', 'Rahayu', 'Permana', 'Setiawan', 'Susanto', 'Wibowo',
        'Hartono', 'Suryadi', 'Gunawan', 'Santoso', 'Pranoto', 'Budiman', 'Haryanto', 'Iskandar', 'Firmansyah', 'Abdullah'
    ];

    // Health status distribution (realistic for Indonesian context)
    // Based on Riskesdas data: ~21% stunting, ~7% wasting, ~4% overweight/obese
    private $healthDistribution = [
        'severely_stunted' => 5,      // 5%
        'stunted' => 16,              // 16%
        'severely_wasted' => 2,       // 2%
        'wasted' => 5,                // 5%
        'overweight' => 3,            // 3%
        'obese' => 1,                 // 1%
        'normal' => 68,               // 68%
    ];

    public function run()
    {
        $this->command->info('Starting massive child data seeder...');

        // Get all location data
        $kelurahanList = DB::table('kelurahan')->get();
        $posyanduList = DB::table('posyandu')->get();
        $rtList = DB::table('rt')->get();
        $kecamatanList = DB::table('kecamatan')->get();
        $puskesmasList = DB::table('puskesmas')->get();

        // Map relationships
        $kelurahanToKecamatan = $kelurahanList->pluck('id_kecamatan', 'id')->toArray();
        $posyanduToPuskesmas = $posyanduList->pluck('id_puskesmas', 'id')->toArray();
        $puskesmasToKecamatan = $puskesmasList->pluck('id_kecamatan', 'id')->toArray();

        // Group RT by kelurahan for distribution
        $rtByKelurahan = $rtList->groupBy('id_kelurahan');

        // Target: ~3000 children distributed across all kelurahan
        $targetTotal = 3000;
        $childrenPerKelurahan = ceil($targetTotal / $kelurahanList->count());

        $this->command->info("Target: {$targetTotal} children across {$kelurahanList->count()} kelurahan");

        $totalCreated = 0;
        $nikCounter = 6400000000000001;

        // Get vaccine types for immunization
        $vaccines = DB::table('jenis_vaksin')->get();

        foreach ($kelurahanList as $kelurahan) {
            $kecamatanId = $kelurahan->id_kecamatan;

            // Get posyandu in this kelurahan's area
            $kelurahanRts = $rtByKelurahan->get($kelurahan->id, collect());
            $posyanduIds = $kelurahanRts->pluck('id_posyandu')->unique()->values();

            if ($posyanduIds->isEmpty()) {
                // Find any posyandu in the same kecamatan
                $puskesmasIds = $puskesmasList->where('id_kecamatan', $kecamatanId)->pluck('id');
                $posyanduIds = $posyanduList->whereIn('id_puskesmas', $puskesmasIds)->pluck('id');
            }

            if ($posyanduIds->isEmpty()) {
                continue;
            }

            // Vary children count per kelurahan (80-150% of average)
            $childrenCount = rand((int)($childrenPerKelurahan * 0.8), (int)($childrenPerKelurahan * 1.5));

            for ($i = 0; $i < $childrenCount; $i++) {
                // Determine health status based on distribution
                $healthStatus = $this->getRandomHealthStatus();

                // Random gender
                $gender = rand(1, 2); // 1 = male, 2 = female
                $genderKey = $gender == 1 ? 'male' : 'female';

                // Random age (0-60 months)
                $ageMonths = rand(0, 60);

                // Generate birthdate
                $birthDate = Carbon::now()->subMonths($ageMonths)->subDays(rand(0, 29));

                // Generate name
                $firstName = $gender == 1
                    ? $this->namaLakiLaki[array_rand($this->namaLakiLaki)]
                    : $this->namaPerempuan[array_rand($this->namaPerempuan)];
                $lastName = $this->namaBelakang[array_rand($this->namaBelakang)];
                $fullName = $firstName . ' ' . $lastName;

                // Get random RT and Posyandu
                $rt = $kelurahanRts->isNotEmpty() ? $kelurahanRts->random() : null;
                $posyanduId = $posyanduIds->random();
                $puskesmasId = $posyanduToPuskesmas[$posyanduId] ?? null;

                // Generate child ID
                $childId = Str::uuid()->toString();

                // Insert child
                DB::table('anak')->insert([
                    'id' => $childId,
                    'no_kk' => '64' . str_pad(rand(1, 99999999999999), 14, '0', STR_PAD_LEFT),
                    'nik' => (string)$nikCounter++,
                    'nama' => $fullName,
                    'nik_ortu' => '64' . str_pad(rand(1, 99999999999999), 14, '0', STR_PAD_LEFT),
                    'nama_ibu' => $this->namaPerempuan[array_rand($this->namaPerempuan)] . ' ' . $this->namaBelakang[array_rand($this->namaBelakang)],
                    'nama_ayah' => $this->namaLakiLaki[array_rand($this->namaLakiLaki)] . ' ' . $this->namaBelakang[array_rand($this->namaBelakang)],
                    'jk' => $gender,
                    'tempat_lahir' => 'Bontang',
                    'tgl_lahir' => $birthDate->format('Y-m-d'),
                    'golda' => ['A', 'B', 'AB', 'O'][rand(0, 3)],
                    'anak' => rand(1, 5),
                    'no' => 'ANK-' . str_pad($totalCreated + 1, 6, '0', STR_PAD_LEFT),
                    'status' => 1,
                    'id_kec' => $kecamatanId,
                    'id_kel' => $kelurahan->id,
                    'id_rt' => $rt ? $rt->id : null,
                    'id_posyandu' => $posyanduId,
                    'id_puskesmas' => $puskesmasId,
                    'catatan' => '',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Generate measurement history (monthly visits)
                $this->generateMeasurementHistory($childId, $gender, $ageMonths, $healthStatus, $genderKey);

                // Generate immunization records
                $this->generateImmunizationRecords($childId, $ageMonths, $vaccines);

                $totalCreated++;
            }

            $this->command->info("Created children for {$kelurahan->name}: {$childrenCount}");
        }

        $this->command->info("Total children created: {$totalCreated}");
    }

    private function getRandomHealthStatus(): string
    {
        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($this->healthDistribution as $status => $percentage) {
            $cumulative += $percentage;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return 'normal';
    }

    private function generateMeasurementHistory($childId, $gender, $currentAgeMonths, $healthStatus, $genderKey)
    {
        // Generate measurements from birth (or first visit at 0-2 months) to current age
        $startMonth = rand(0, min(2, $currentAgeMonths));
        $measurementMonths = [];

        // Monthly visits for first year, then every 1-3 months
        for ($month = $startMonth; $month <= $currentAgeMonths; $month++) {
            if ($month <= 12 || rand(1, 3) == 1) {
                $measurementMonths[] = $month;
            }
        }

        foreach ($measurementMonths as $month) {
            // Get WHO reference for this age
            $ref = $this->getWHOReference($genderKey, $month);

            // Generate measurements based on health status
            list($weight, $height) = $this->generateMeasurements($ref, $healthStatus, $month);

            // Calculate visit date
            $visitDate = Carbon::now()->subMonths($currentAgeMonths - $month)->subDays(rand(0, 15));

            // Determine position (lying for < 24 months, standing for >= 24 months)
            $position = $month < 24 ? 'L' : 'H';

            // Generate other measurements
            $lla = 9 + ($month * 0.15) + (rand(-10, 10) / 10); // Upper arm circumference
            $lk = 34 + ($month * 0.3) + (rand(-10, 10) / 10);  // Head circumference

            DB::table('data_anak')->insert([
                'id_anak' => $childId,
                'tgl_kunjungan' => $visitDate->format('Y-m-d'),
                'bln' => $month,
                'posisi' => $position,
                'tb' => round($height, 1),
                'bb' => round($weight, 2),
                'lla' => round($lla, 1),
                'lk' => round($lk, 1),
                'ntob' => null,
                'asi' => $month <= 6 ? rand(0, 1) : 0,
                'vit_a' => ($month == 6 || $month == 12 || $month == 18 || $month == 24) ? 1 : null,
                'obat_cacing' => ($month >= 12 && $month % 6 == 0) ? 1 : null,
                'ddtka' => null,
                'id_user' => 1,
                'created_at' => $visitDate,
                'updated_at' => $visitDate,
            ]);
        }
    }

    private function getWHOReference($gender, $month): array
    {
        $refs = $this->whoReference[$gender];

        // Find closest reference month
        $availableMonths = array_keys($refs);
        $closest = $availableMonths[0];

        foreach ($availableMonths as $refMonth) {
            if ($refMonth <= $month) {
                $closest = $refMonth;
            } else {
                break;
            }
        }

        // Interpolate if needed
        $nextMonth = null;
        foreach ($availableMonths as $refMonth) {
            if ($refMonth > $closest) {
                $nextMonth = $refMonth;
                break;
            }
        }

        if ($nextMonth && $month > $closest) {
            $ratio = ($month - $closest) / ($nextMonth - $closest);
            $current = $refs[$closest];
            $next = $refs[$nextMonth];

            return [
                $current[0] + ($next[0] - $current[0]) * $ratio, // median weight
                $current[1] + ($next[1] - $current[1]) * $ratio, // sd weight
                $current[2] + ($next[2] - $current[2]) * $ratio, // median height
                $current[3] + ($next[3] - $current[3]) * $ratio, // sd height
            ];
        }

        return $refs[$closest];
    }

    private function generateMeasurements($ref, $healthStatus, $month): array
    {
        list($medianWeight, $sdWeight, $medianHeight, $sdHeight) = $ref;

        // Generate Z-scores based on health status
        switch ($healthStatus) {
            case 'severely_stunted':
                $heightZ = -3 - (rand(0, 100) / 100); // < -3 SD
                $weightZ = rand(-200, 100) / 100;     // Variable
                break;
            case 'stunted':
                $heightZ = -2 - (rand(0, 100) / 100); // -3 to -2 SD
                $weightZ = rand(-150, 100) / 100;
                break;
            case 'severely_wasted':
                $weightZ = -3 - (rand(0, 100) / 100); // < -3 SD
                $heightZ = rand(-100, 100) / 100;
                break;
            case 'wasted':
                $weightZ = -2 - (rand(0, 100) / 100); // -3 to -2 SD
                $heightZ = rand(-100, 100) / 100;
                break;
            case 'overweight':
                $weightZ = 2 + (rand(0, 100) / 100);  // +2 to +3 SD
                $heightZ = rand(-50, 150) / 100;
                break;
            case 'obese':
                $weightZ = 3 + (rand(0, 150) / 100);  // > +3 SD
                $heightZ = rand(-50, 150) / 100;
                break;
            default: // normal
                $weightZ = rand(-150, 150) / 100;     // -1.5 to +1.5 SD
                $heightZ = rand(-150, 150) / 100;
        }

        $weight = $medianWeight + ($weightZ * $sdWeight);
        $height = $medianHeight + ($heightZ * $sdHeight);

        // Ensure minimum values
        $weight = max($weight, 2.0);
        $height = max($height, 45.0);

        return [$weight, $height];
    }

    private function generateImmunizationRecords($childId, $currentAgeMonths, $vaccines)
    {
        // Immunization schedule (vaccine_code => age_in_days)
        $schedule = [
            'HB0' => 0,
            'BCG' => 7,
            'POLIO1' => 30,
            'DPT-HB-HIB1' => 60,
            'POLIO2' => 60,
            'DPT-HB-HIB2' => 90,
            'POLIO3' => 90,
            'DPT-HB-HIB3' => 120,
            'POLIO4' => 120,
            'IPV' => 120,
            'CAMPAK' => 270,
            'DPT-HB-HIB-LANJUTAN' => 540,
            'MR' => 540,
        ];

        $currentAgeDays = $currentAgeMonths * 30;

        foreach ($vaccines as $vaccine) {
            $scheduledDay = $schedule[$vaccine->kode] ?? null;

            if ($scheduledDay === null) continue;

            // Skip if child is too young
            if ($scheduledDay > $currentAgeDays) continue;

            // Determine if vaccinated (higher chance for basic vaccines)
            $isBasic = in_array($vaccine->kategori, ['Imunisasi Dasar']);
            $vaccinationChance = $isBasic ? 85 : 70;

            // Random chance of being vaccinated
            if (rand(1, 100) > $vaccinationChance) {
                // Not vaccinated - create 'belum' record
                DB::table('imunisasi')->insert([
                    'id_anak' => $childId,
                    'id_jenis_vaksin' => $vaccine->id,
                    'dosis' => 1,
                    'tanggal_pemberian' => null,
                    'tanggal_selanjutnya' => Carbon::now()->subMonths($currentAgeMonths)->addDays($scheduledDay)->format('Y-m-d'),
                    'batch_number' => null,
                    'lokasi_pemberian' => null,
                    'id_petugas' => null,
                    'status' => 'belum',
                    'reaksi_kipi' => null,
                    'catatan' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Vaccinated
                $daysLate = rand(0, 30); // Some delay is realistic
                $vaccinationDate = Carbon::now()->subMonths($currentAgeMonths)->addDays($scheduledDay + $daysLate);

                DB::table('imunisasi')->insert([
                    'id_anak' => $childId,
                    'id_jenis_vaksin' => $vaccine->id,
                    'dosis' => 1,
                    'tanggal_pemberian' => $vaccinationDate->format('Y-m-d'),
                    'tanggal_selanjutnya' => null,
                    'batch_number' => 'BTG-' . rand(100000, 999999),
                    'lokasi_pemberian' => ['Lengan Kiri', 'Lengan Kanan', 'Paha Kiri', 'Paha Kanan'][rand(0, 3)],
                    'id_petugas' => 1,
                    'status' => $daysLate > 14 ? 'terlambat' : 'sudah',
                    'reaksi_kipi' => rand(1, 100) <= 5 ? 'Demam ringan' : null,
                    'catatan' => null,
                    'created_at' => $vaccinationDate,
                    'updated_at' => $vaccinationDate,
                ]);
            }
        }
    }
}
