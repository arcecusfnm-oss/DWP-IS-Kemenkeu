<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use App\Models\Bidang;
use App\Models\Peran;
use Illuminate\Database\Seeder;

class PenggunaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $passAdmin = env('SEED_ADMIN_PASSWORD');
        $passUser = env('SEED_USER_PASSWORD');

        if (! $passAdmin || ! $passUser)
        {
            throw new \RuntimeException('SEED_ADMIN_PASSWORD dan SEED_USER_PASSWORD harus diisi di .env');
        }

        $peran = Peran::pluck ('id_peran', 'nama_peran');
        $bidang = Bidang::pluck ('id_bidang', 'nama_bidang');

        // [nama_lengkap, nama_pengguna, kata sandi, peran, bidang, status]
        $akun = [
            ['Admin Uji', 'admin', $passAdmin, 'Admin', 'Sekretariat', 'aktif'],
            ['Penasihat Uji', 'penasihat', $passUser, 'Penasihat', 'Sekretariat', 'aktif'],
            ['Pimpinan Ekonomi', 'pimpinan', $passUser, 'Pimpinan Bidang', 'Ekonomi', 'aktif'],
            ['Anggota Pendidikan', 'anggota', $passUser, 'Anggota Bidang', 'Pendidikan', 'aktif'],
            ['Bendahara Uji', 'bendahara', $passUser, 'Bendahara', 'Bendahara', 'aktif'],
            ['Anggota Nonaktif', 'nonaktif', $passUser, 'Anggota Bidang', 'Sosial Budaya', 'nonaktif'],
        ];

        foreach ($akun as [$namaLengkap, $namaPengguna, $sandi, $namaPeran, $namaBidang, $status])
        {
            Pengguna::updateOrCreate(
                ['nama_pengguna' => $namaPengguna],
                [
                    'nama_lengkap' => $namaLengkap,
                    'kata_sandi_hash' => $sandi,
                    'status' => $status,
                    'perlu_ganti_kata_sandi' => false,
                    'id_peran' => $peran[$namaPeran],
                    'id_bidang' => $bidang[$namaBidang],
                ]
            );

        }

    }
}
