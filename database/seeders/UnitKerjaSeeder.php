<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitKerjaSeeder extends Seeder
{
    /**
     * Daftar unit kerja suami sesuai Template Database Pengurus DWP Kemenkeu.
     * Aman dijalankan ulang: nama yang sudah ada tidak diduplikasi.
     */
    public function run(): void
    {
        $unit = ['BATII', 'BPPK', 'DJA', 'DJP', 'DJPB', 'DJBC', 'DJKN',
                 'DJPK', 'DJSEF', 'DJSPSK', 'DJPPR', 'ITJEN', 'SEKJEN'];

        foreach ($unit as $nama) {
            $sudahAda = DB::table('unit_kerja')->where('nama_unit_kerja', $nama)->exists();

            if (! $sudahAda) {
                DB::table('unit_kerja')->insert([
                    'nama_unit_kerja' => $nama,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }
}