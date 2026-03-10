<?php

namespace Database\Factories;

use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Database\Eloquent\Factories\Factory;

class PosyanduFactory extends Factory
{
    protected $model = Posyandu::class;

    public function definition()
    {
        return [
            'id_puskesmas' => Puskesmas::factory(),
            'name' => 'Posyandu ' . $this->faker->unique()->city(),
        ];
    }
}
