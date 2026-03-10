<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Anak;
use App\Models\User;
use Carbon\Carbon;

class DataAnakSeeder extends Seeder
{
    /**
     * Seed data_anak table with periodic growth measurements.
     */
    public function run(): void
    {
        if (DB::table('data_anak')->count() > 0) {
            $this->command?->info('data_anak table already has data, skipping.');
            return;
        }

        $anakList = Anak::all();
        $users = User::all();

        if ($anakList->isEmpty() || $users->isEmpty()) {
            $this->command?->warn('Skipping DataAnakSeeder: anak or users data not found.');
            return;
        }

        $this->command?->info('Seeding data_anak (growth measurements)...');

        $records = [];

        foreach ($anakList as $anak) {
            $tglLahir = Carbon::parse($anak->tgl_lahir);
            $umurBulan = (int) $tglLahir->diffInMonths(Carbon::now());
            $user = $users->random();

            // Generate 2-5 measurement visits per child
            $visitCount = min(fake()->numberBetween(2, 5), max(1, $umurBulan));

            for ($v = 0; $v < $visitCount; $v++) {
                $bln = (int) round($umurBulan * ($v + 1) / $visitCount);
                if ($bln < 0) $bln = 0;
                if ($bln > 60) $bln = 60;

                $tglKunjungan = (clone $tglLahir)->addMonths($bln);
                if ($tglKunjungan->isFuture()) {
                    $tglKunjungan = Carbon::now()->subDays(fake()->numberBetween(1, 30));
                }

                // Posisi: H (horizontal/lying) for < 24 months, L (standing) for >= 24
                $posisi = $bln < 24 ? 'H' : 'L';

                // Realistic growth data based on age
                $bb = $this->weightForAge($bln, $anak->jk);
                $tb = $this->heightForAge($bln, $anak->jk);

                $records[] = [
                    'id_anak' => $anak->id,
                    'tgl_kunjungan' => $tglKunjungan->format('Y-m-d'),
                    'bln' => $bln,
                    'posisi' => $posisi,
                    'tb' => $tb,
                    'bb' => $bb,
                    'lla' => round(fake()->randomFloat(1, 10, 18), 1),
                    'lk' => round(fake()->randomFloat(1, 33, 50), 1),
                    'ntob' => null,
                    'asi' => fake()->randomElement([0, 1, 1, 1]),
                    'vit_a' => fake()->randomElement([0, 1]),
                    'obat_cacing' => $bln >= 12 ? fake()->randomElement([0, 1]) : 0,
                    'id_user' => $user->id,
                    'created_at' => $tglKunjungan,
                    'updated_at' => $tglKunjungan,
                ];
            }
        }

        foreach (array_chunk($records, 50) as $chunk) {
            DB::table('data_anak')->insert($chunk);
        }

        $this->command?->info('Berhasil seed ' . count($records) . ' data pengukuran anak.');
    }

    /**
     * Generate realistic weight based on age (months) and gender.
     */
    private function weightForAge(int $months, int $jk): float
    {
        // WHO median approximation (kg)
        $baseWeights = [
            0 => 3.3, 1 => 4.5, 2 => 5.6, 3 => 6.4, 4 => 7.0,
            6 => 7.9, 9 => 8.9, 12 => 9.6, 18 => 10.9,
            24 => 12.2, 36 => 14.3, 48 => 16.3, 60 => 18.3,
        ];

        $closest = 0;
        foreach (array_keys($baseWeights) as $key) {
            if ($key <= $months) $closest = $key;
        }

        $base = $baseWeights[$closest];
        // Female slightly lighter
        if ($jk === 2) $base *= 0.95;
        // Add variance
        $variance = fake()->randomFloat(1, -0.8, 0.8);

        return round(max(2.5, $base + $variance), 1);
    }

    /**
     * Generate realistic height based on age (months) and gender.
     */
    private function heightForAge(int $months, int $jk): float
    {
        // WHO median approximation (cm)
        $baseHeights = [
            0 => 49.9, 1 => 54.7, 2 => 58.4, 3 => 61.4, 4 => 63.9,
            6 => 67.6, 9 => 72.0, 12 => 75.7, 18 => 82.3,
            24 => 87.8, 36 => 96.1, 48 => 103.3, 60 => 110.0,
        ];

        $closest = 0;
        foreach (array_keys($baseHeights) as $key) {
            if ($key <= $months) $closest = $key;
        }

        $base = $baseHeights[$closest];
        if ($jk === 2) $base *= 0.98;
        $variance = fake()->randomFloat(1, -2.0, 2.0);

        return round(max(45.0, $base + $variance), 1);
    }
}
