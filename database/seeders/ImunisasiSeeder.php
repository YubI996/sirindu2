<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Anak;
use App\Models\JenisVaksin;
use Carbon\Carbon;

class ImunisasiSeeder extends Seeder
{
    /**
     * Seed imunisasi table with immunization records linked to anak and jenis_vaksin.
     */
    public function run(): void
    {
        if (DB::table('imunisasi')->count() > 0) {
            $this->command?->info('imunisasi table already has data, skipping.');
            return;
        }

        $anakList = Anak::all();
        $vaksinList = JenisVaksin::where('aktif', true)->get();

        if ($anakList->isEmpty() || $vaksinList->isEmpty()) {
            $this->command?->warn('Skipping ImunisasiSeeder: anak or jenis_vaksin data not found.');
            return;
        }

        $this->command?->info('Seeding imunisasi records...');

        $lokasiPemberian = [
            'Puskesmas Bontang Utara I', 'Puskesmas Bontang Utara II',
            'Puskesmas Bontang Selatan I', 'Puskesmas Bontang Selatan II',
            'Posyandu Melati', 'Posyandu Mawar', 'Posyandu Dahlia',
            'RSUD Taman Husada', 'Klinik Pertamina',
        ];

        $records = [];

        foreach ($anakList as $anak) {
            $tglLahir = Carbon::parse($anak->tgl_lahir);
            $umurHari = (int) $tglLahir->diffInDays(Carbon::now());

            // Give each child a random subset of vaccines appropriate for their age
            $eligibleVaksin = $vaksinList->filter(function ($v) use ($umurHari) {
                return $v->usia_pemberian_min !== null && $umurHari >= $v->usia_pemberian_min;
            });

            // Take 60-90% of eligible vaccines (not all children are fully immunized)
            $count = max(1, (int) ($eligibleVaksin->count() * fake()->randomFloat(2, 0.6, 0.9)));
            $selectedVaksin = $eligibleVaksin->random(min($count, $eligibleVaksin->count()));

            foreach ($selectedVaksin as $vaksin) {
                $pemberian = (clone $tglLahir)->addDays($vaksin->usia_pemberian_min + fake()->numberBetween(0, 14));
                if ($pemberian->isFuture()) {
                    // Schedule for future — mark as 'belum'
                    $status = 'belum';
                } else {
                    $status = fake()->randomElement(['sudah', 'sudah', 'sudah', 'terlambat']);
                }

                $records[] = [
                    'id_anak' => $anak->id,
                    'id_jenis_vaksin' => $vaksin->id,
                    'dosis' => 1,
                    'tanggal_pemberian' => $status !== 'belum' ? $pemberian->format('Y-m-d') : null,
                    'tanggal_selanjutnya' => $vaksin->interval_hari
                        ? (clone $pemberian)->addDays($vaksin->interval_hari)->format('Y-m-d')
                        : null,
                    'batch_number' => $status !== 'belum' ? strtoupper(fake()->bothify('??###??')) : null,
                    'lokasi_pemberian' => $status !== 'belum' ? fake()->randomElement($lokasiPemberian) : null,
                    'id_petugas' => null,
                    'status' => $status,
                    'reaksi_kipi' => $status === 'sudah' && fake()->boolean(15) ? fake()->randomElement([
                        'Demam ringan', 'Bengkak di area suntik', 'Rewel', 'Kemerahan lokal',
                    ]) : null,
                    'catatan' => null,
                    'created_at' => $pemberian->isFuture() ? now() : $pemberian,
                    'updated_at' => $pemberian->isFuture() ? now() : $pemberian,
                ];
            }
        }

        foreach (array_chunk($records, 50) as $chunk) {
            DB::table('imunisasi')->insert($chunk);
        }

        $this->command?->info('Berhasil seed ' . count($records) . ' data imunisasi.');
    }
}
