<?php

namespace Database\Factories;

use App\Models\Kecamatan;
use Illuminate\Database\Eloquent\Factories\Factory;

class KecamatanFactory extends Factory
{
    protected $model = Kecamatan::class;

    public function definition()
    {
        return [
            'name' => 'Kec. ' . $this->faker->unique()->city(),
        ];
    }
}
