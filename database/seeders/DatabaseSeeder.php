<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\KecamatanTableSeeder;
use Database\Seeders\KelurahanTableSeeder;
use Database\Seeders\UsersTableSeeder;
use Database\Seeders\PuskesmasTableSeeder;
use Database\Seeders\PosyanduTableSeeder;
use Database\Seeders\RtTableSeeder;
use Database\Seeders\LokasiPenularanSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->call(UsersTableSeeder::class);
        $this->call(KecamatanTableSeeder::class);
        $this->call(KelurahanTableSeeder::class);
        $this->call(PuskesmasTableSeeder::class);
        $this->call(PosyanduTableSeeder::class);
        $this->call(RtTableSeeder::class);
        $this->call(JenisTabelSeeder::class);
        $this->call(ZScoreSeeder::class);
        $this->call(JenisVaksinSeeder::class);
        $this->call(JenisKasusEpidemiologiSeeder::class);
        $this->call(AnakSeeder::class);
        $this->call(DataAnakSeeder::class);
        $this->call(ImunisasiSeeder::class);
        $this->call(SurveillanceCaseSeeder::class);
        $this->call(RumahSakitSeeder::class);
        $this->call(RoleUserSeeder::class);
        $this->call(LokasiPenularanSeeder::class);

    }
}
