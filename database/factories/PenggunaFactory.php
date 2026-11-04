<?php

namespace Database\Factories;

use App\Models\Bidang;
use App\Models\Peran;
use Illuminate\Database\Eloquent\Factories\Factory;

class PenggunaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_lengkap'    => fake()->name(),
            'nama_pengguna'   => fake()->unique()->userName(),
            'kata_sandi_hash' => 'ArcecusAwal123',
            'id_peran'        => Peran::factory(),
            'id_bidang'       => Bidang::factory(),
        ];
    }
}