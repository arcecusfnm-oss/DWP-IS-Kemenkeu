<?php

namespace Database\Seeders;

use App\Models\Bidang;
use Illuminate\Database\Seeder;

class BidangSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Ekonomi', 'Sosial Budaya', 'Pendidikan', 'Sekretariat', 'Bendahara'] as $nama) {
            Bidang::firstOrCreate(['nama_bidang' => $nama]);
        }
    }
}
