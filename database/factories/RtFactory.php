<?php

namespace Database\Factories;

use App\Models\Rt;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use Illuminate\Database\Eloquent\Factories\Factory;

class RtFactory extends Factory
{
    protected $model = Rt::class;

    public function definition()
    {
        return [
            'id_kelurahan' => Kelurahan::factory(),
            'id_posyandu' => Posyandu::factory(),
            'name' => 'RT ' . $this->faker->numerify('##'),
        ];
    }
}
