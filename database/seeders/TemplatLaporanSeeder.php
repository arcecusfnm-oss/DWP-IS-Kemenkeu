<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TemplatLaporanSeeder extends Seeder
{
    public function run(): void
    {
        // Idempoten: aman dijalankan ulang, templat tunggal tidak dobel.
        if (DB::table('templat_laporan')->exists()) {
            return;
        }

        DB::transaction(function () {
            $id = DB::table('templat_laporan')->insertGetId([
                'nama_templat' => 'Templat Laporan Kegiatan',
                'aktif'        => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ], 'id_templat_laporan');

            $kolom = [
                ['judul_kegiatan', 'Judul Kegiatan', true],
                ['tanggal_kegiatan', 'Tanggal Kegiatan', true],
                ['penjelasan_singkat', 'Penjelasan Singkat', true],
                ['dokumentasi', 'Dokumentasi', false],
            ];

            foreach ($kolom as $i => [$kunci, $label, $wajib]) {
                DB::table('kolom_templat_laporan')->insert([
                    'id_templat_laporan' => $id,
                    'kunci'              => $kunci,
                    'label'              => $label,
                    'wajib'              => $wajib,
                    'urutan'             => $i + 1,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        });
    }
}