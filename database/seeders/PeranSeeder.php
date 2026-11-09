<?php

namespace Database\Seeders;

use App\Models\Peran;
use Illuminate\Database\Seeder;

class PeranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Admin', 'Penanggung Jawab', 'Pimpinan Bidang', 'Anggota Bidang', 'Bendahara', 'Penasihat'] as $nama) {
            Peran::firstOrCreate(['nama_peran' => $nama]);
        }
    }
}