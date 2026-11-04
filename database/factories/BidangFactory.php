<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BidangFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_bidang' => 'Bidang Uji ' . fake()->unique()->numerify('####'),
        ];
    }
}