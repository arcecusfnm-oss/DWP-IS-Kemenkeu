<?php

namespace Database\Seeders;

use App\Models\Peran;
use Illuminate\Database\Seeder;

class PeranSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Admin', 'Penasihat', 'Pimpinan Bidang', 'Anggota Bidang', 'Bendahara'] as $nama) {
            Peran::firstOrCreate(['nama_peran' => $nama]);
        }
    }
}
