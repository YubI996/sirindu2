<?php

namespace Database\Factories;

use App\Models\Puskesmas;
use App\Models\Kecamatan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PuskesmasFactory extends Factory
{
    protected $model = Puskesmas::class;

    public function definition()
    {
        return [
            'id_kecamatan' => Kecamatan::factory(),
            'name' => 'Puskesmas ' . $this->faker->unique()->city(),
        ];
    }
}
