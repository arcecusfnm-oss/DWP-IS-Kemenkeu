<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JabatanDwpSeeder extends Seeder
{
    public function run(): void
    {
        $slot = [
            ['Ketua DWP',        'ketua',       1],
            ['Wakil Ketua I',    'wakil_ketua', 1],
            ['Wakil Ketua II',   'wakil_ketua', 2],
            ['Wakil Ketua III',  'wakil_ketua', 3],
            ['Wakil Ketua IV',   'wakil_ketua', 4],
        ];

        foreach ($slot as [$nama, $jenis, $urutan]) {
            DB::table('jabatan_dwp')->updateOrInsert(
                ['nama_jabatan' => $nama],
                ['jenis' => $jenis, 'urutan' => $urutan, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}