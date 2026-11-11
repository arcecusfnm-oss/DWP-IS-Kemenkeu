<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed anggota contoh (B-016, T-24).
 *
 * - Semua data fiktif; nomor telepon 12 digit (AC: 8-15 digit angka).
 * - Tidak menyimpan kata sandi atau data sensitif; foto_path dikosongkan.
 * - Aman dijalankan berulang (T-114): data yang sudah ada dilewati, tidak
 *   diduplikasi atau ditimpa, jadi relasi akun yang sudah terpasang tidak berubah.
 * - Bidang diambil dari tabel bidang (dibagi merata), tidak bergantung nama bidang.
 * - Unit kerja contoh dibuat di sini bila belum ada.
 *
 * Syarat: seeder peran, bidang, dan akun Admin (Sprint 1) dijalankan lebih dulu.
 */
class AnggotaSeeder extends Seeder
{
    public function run(): void
    {
        $bidangIds = DB::table('bidang')->orderBy('id_bidang')->pluck('id_bidang')->all();
        if (count($bidangIds) === 0) {
            $this->command?->error('Tabel bidang kosong. Jalankan seeder bidang terlebih dahulu.');
            return;
        }

        $adminId = DB::table('pengguna')
            ->join('peran', 'peran.id_peran', '=', 'pengguna.id_peran')
            ->where('peran.nama_peran', 'Admin')
            ->whereNull('pengguna.dihapus_pada')
            ->orderBy('pengguna.id_pengguna')
            ->value('pengguna.id_pengguna');
        if (! $adminId) {
            $this->command?->error('Akun Admin tidak ditemukan. Jalankan seeder akun terlebih dahulu.');
            return;
        }

        $sekarang = now();

        // Unit kerja contoh (fiktif)
        $namaUnit = ['Sekretariat Jenderal', 'Direktorat Jenderal Anggaran', 'Direktorat Jenderal Pajak'];
        $unitIds = [];
        foreach ($namaUnit as $nama) {
            $id = DB::table('unit_kerja')->where('nama_unit_kerja', $nama)->value('id_unit_kerja');
            if (! $id) {
                $id = DB::table('unit_kerja')->insertGetId([
                    'nama_unit_kerja' => $nama,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]);
            }
            $unitIds[] = $id;
        }

        $contoh = [
            ['nama_lengkap' => 'Anindya Prameswari',  'nama_panggilan' => 'Anin',  'nomor_telepon' => '081200000001', 'tempat_lahir' => 'Jakarta',  'tanggal_lahir' => '1985-03-14', 'alamat_lengkap' => 'Jl. Contoh Raya No. 1, Jakarta Pusat', 'email' => 'anindya@contoh.test', 'nama_suami' => 'Budi Santoso',   'tingkat_pendidikan' => 'S1', 'bidang_pendidikan' => 'Akuntansi',  'keahlian' => 'Pembukuan, administrasi',        'instansi_kerja' => 'Kementerian Contoh', 'bidang_usaha' => null,             'status' => 'aktif',    'hubungkan' => true],
            ['nama_lengkap' => 'Bunga Larasati',      'nama_panggilan' => 'Bunga', 'nomor_telepon' => '081200000002', 'tempat_lahir' => 'Bandung',  'tanggal_lahir' => '1988-07-02', 'alamat_lengkap' => 'Jl. Contoh Indah No. 2, Bandung',       'email' => 'bunga@contoh.test',    'nama_suami' => 'Candra Wijaya',  'tingkat_pendidikan' => 'S2', 'bidang_pendidikan' => 'Manajemen',   'keahlian' => 'Perencanaan program, MC',        'instansi_kerja' => null,                'bidang_usaha' => 'Katering',      'status' => 'aktif',    'hubungkan' => true],
            ['nama_lengkap' => 'Citra Kusumawardani', 'nama_panggilan' => 'Citra', 'nomor_telepon' => '081200000003', 'tempat_lahir' => 'Surabaya', 'tanggal_lahir' => '1990-11-23', 'alamat_lengkap' => 'Jl. Contoh Asri No. 3, Depok',         'email' => null,                   'nama_suami' => 'Dodi Prakoso',   'tingkat_pendidikan' => 'D3', 'bidang_pendidikan' => 'Sekretaris',  'keahlian' => 'Persuratan, notulen',            'instansi_kerja' => null,                'bidang_usaha' => null,           'status' => 'aktif',    'hubungkan' => true],
            ['nama_lengkap' => 'Dewi Anggraini',      'nama_panggilan' => 'Dewi',  'nomor_telepon' => '081200000004', 'tempat_lahir' => 'Semarang', 'tanggal_lahir' => '1983-01-30', 'alamat_lengkap' => 'Jl. Contoh Makmur No. 4, Bekasi',       'email' => 'dewi@contoh.test',     'nama_suami' => 'Eko Prasetyo',   'tingkat_pendidikan' => 'S1', 'bidang_pendidikan' => 'Hukum',       'keahlian' => 'Hukum, advokasi',                'instansi_kerja' => null,                'bidang_usaha' => null,           'status' => 'aktif',    'hubungkan' => false],
            ['nama_lengkap' => 'Endah Purwaningsih',  'nama_panggilan' => 'Endah', 'nomor_telepon' => '081200000005', 'tempat_lahir' => 'Yogyakarta','tanggal_lahir' => '1986-05-19', 'alamat_lengkap' => 'Jl. Contoh Sejahtera No. 5, Tangerang', 'email' => null,                   'nama_suami' => 'Fajar Nugroho',  'tingkat_pendidikan' => 'S1', 'bidang_pendidikan' => 'Psikologi',   'keahlian' => 'Konseling, pelatihan',           'instansi_kerja' => 'Sekolah Contoh',    'bidang_usaha' => null,           'status' => 'aktif',    'hubungkan' => false],
            ['nama_lengkap' => 'Fitri Handayani',     'nama_panggilan' => 'Fitri', 'nomor_telepon' => '081200000006', 'tempat_lahir' => 'Medan',    'tanggal_lahir' => '1991-09-08', 'alamat_lengkap' => 'Jl. Contoh Damai No. 6, Bogor',         'email' => 'fitri@contoh.test',    'nama_suami' => 'Gunawan Hakim',  'tingkat_pendidikan' => 'S1', 'bidang_pendidikan' => 'Desain',      'keahlian' => 'Desain grafis, dokumentasi',     'instansi_kerja' => null,                'bidang_usaha' => 'Percetakan',   'status' => 'aktif',    'hubungkan' => false],
            ['nama_lengkap' => 'Gita Maharani',       'nama_panggilan' => 'Gita',  'nomor_telepon' => '081200000007', 'tempat_lahir' => 'Malang',   'tanggal_lahir' => '1987-12-25', 'alamat_lengkap' => 'Jl. Contoh Sentosa No. 7, Jakarta Selatan','email' => null,                 'nama_suami' => 'Hendra Saputra', 'tingkat_pendidikan' => 'S2', 'bidang_pendidikan' => 'Kesehatan Masyarakat', 'keahlian' => 'Kesehatan, penyuluhan', 'instansi_kerja' => 'Puskesmas Contoh',  'bidang_usaha' => null,           'status' => 'aktif',    'hubungkan' => false],
            ['nama_lengkap' => 'Hesti Wulandari',     'nama_panggilan' => 'Hesti', 'nomor_telepon' => '081200000008', 'tempat_lahir' => 'Palembang','tanggal_lahir' => '1984-04-11', 'alamat_lengkap' => 'Jl. Contoh Bahagia No. 8, Jakarta Timur', 'email' => 'hesti@contoh.test',    'nama_suami' => 'Irfan Maulana',  'tingkat_pendidikan' => 'S1', 'bidang_pendidikan' => 'Ekonomi',     'keahlian' => 'Keuangan, bendahara',            'instansi_kerja' => null,                'bidang_usaha' => null,           'status' => 'aktif',    'hubungkan' => false],
            ['nama_lengkap' => 'Indah Permatasari',   'nama_panggilan' => 'Indah', 'nomor_telepon' => '081200000009', 'tempat_lahir' => 'Makassar', 'tanggal_lahir' => '1992-02-17', 'alamat_lengkap' => 'Jl. Contoh Rukun No. 9, Jakarta Barat',  'email' => null,                   'nama_suami' => 'Joko Susilo',    'tingkat_pendidikan' => 'D3', 'bidang_pendidikan' => 'Tata Boga',   'keahlian' => 'Memasak, kewirausahaan',         'instansi_kerja' => null,                'bidang_usaha' => 'Kuliner',      'status' => 'aktif',    'hubungkan' => false],
            ['nama_lengkap' => 'Jihan Nurhaliza',     'nama_panggilan' => 'Jihan', 'nomor_telepon' => '081200000010', 'tempat_lahir' => 'Denpasar', 'tanggal_lahir' => '1989-08-05', 'alamat_lengkap' => 'Jl. Contoh Tenteram No. 10, Jakarta Utara','email' => null,                  'nama_suami' => 'Kurniawan Adi',  'tingkat_pendidikan' => 'S1', 'bidang_pendidikan' => 'Bahasa Inggris','keahlian' => 'Penerjemahan',               'instansi_kerja' => null,                'bidang_usaha' => null,           'status' => 'nonaktif', 'hubungkan' => false], // uji tampilan status
        ];

        foreach ($contoh as $i => $row) {
            $sudahAda = DB::table('anggota')
                ->where('nama_lengkap', $row['nama_lengkap'])
                ->where('nomor_telepon', $row['nomor_telepon'])
                ->exists();
            if ($sudahAda) {
                continue;
            }

            $idBidang = $bidangIds[$i % count($bidangIds)];

            $idPengguna = null;
            if ($row['hubungkan']) {
                // Akun aktif di bidang yang sama yang belum dipakai anggota lain.
                $idPengguna = DB::table('pengguna')
                    ->where('id_bidang', $idBidang)
                    ->whereNull('dihapus_pada')
                    ->whereNotIn('id_pengguna', function ($q) {
                        $q->select('id_pengguna')->from('anggota')->whereNotNull('id_pengguna');
                    })
                    ->orderBy('id_pengguna')
                    ->value('id_pengguna');
            }

            unset($row['hubungkan']);

            DB::table('anggota')->insert($row + [
                'id_pengguna'   => $idPengguna,
                'id_bidang'     => $idBidang,
                'id_unit_kerja' => $unitIds[$i % count($unitIds)],
                'foto_path'     => null,
                'dibuat_oleh'   => $adminId,
                'created_at'    => $sekarang,
                'updated_at'    => $sekarang,
            ]);
        }
    }
}