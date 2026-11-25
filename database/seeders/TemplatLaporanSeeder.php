<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TemplatLaporanSeeder extends Seeder
{
    public function run(): void
    {
        // Idempoten: template singleton, tidak dobel bila dijalankan ulang.
        if (DB::table('template_laporan')->exists()) {
            return;
        }

        DB::table('template_laporan')->insert([
            'label_judul_kegiatan'     => 'Judul Kegiatan',
            'label_tanggal_kegiatan'   => 'Tanggal Kegiatan',
            'label_penjelasan_singkat' => 'Penjelasan Singkat',
            'label_dokumentasi'        => 'Dokumentasi',
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }
}