<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PeranFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_peran' => 'Peran Uji ' . fake()->unique()->numerify('####'),
        ];
    }
}