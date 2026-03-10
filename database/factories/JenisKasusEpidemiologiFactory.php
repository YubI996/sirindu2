<?php

namespace Database\Factories;

use App\Models\JenisKasusEpidemiologi;
use Illuminate\Database\Eloquent\Factories\Factory;

class JenisKasusEpidemiologiFactory extends Factory
{
    protected $model = JenisKasusEpidemiologi::class;

    public function definition()
    {
        return [
            'kode_penyakit' => strtoupper($this->faker->unique()->lexify('???-###')),
            'nama_penyakit' => $this->faker->sentence(3),
            'kategori' => $this->faker->randomElement(['PD3I', 'menular_langsung', 'vector_borne', 'zoonosis', 'lainnya']),
            'deskripsi' => $this->faker->paragraph(),
            'is_active' => true,
        ];
    }

    public function inactive()
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
