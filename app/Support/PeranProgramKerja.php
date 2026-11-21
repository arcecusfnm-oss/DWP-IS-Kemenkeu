<?php

namespace App\Support;

/**
 * Satu-satunya tempat aturan peran untuk fitur program kerja dan kegiatan.
 * Sumber: tabel user story (US-115/124 tambah, US-116/125 ubah, US-127 kegiatan).
 * Kalau aturan berubah, cukup ubah konstanta di sini.
 */
class PeranProgramKerja
{
    /** Boleh tambah dan ubah program kerja bidangnya sendiri. */
    public const KELOLA_PROGRAM = ['Pimpinan Bidang', 'Anggota Bidang'];

    /** Boleh tambah, ubah, hapus kegiatan (US-127, 141, 142). */
    /** Boleh tambah, ubah, hapus kegiatan (AC B-048 poin 1). */
    public const KELOLA_KEGIATAN = ['Pimpinan Bidang', 'Anggota Bidang'];

    /** Boleh melihat daftar program bidangnya sendiri; juga yang memakai layout bidang. */
    public const LIHAT_BIDANG_SENDIRI = ['Pimpinan Bidang', 'Anggota Bidang'];

    /** Untuk dipakai di route: ->middleware(['auth', PeranProgramKerja::middleware(...)]) */
    public static function middleware(array $peran): string
    {
        return 'role:' . implode(',', $peran);
    }

    public static function bolehKelolaProgram($pengguna): bool
    {
        return in_array($pengguna?->peran?->nama_peran, self::KELOLA_PROGRAM, true);
    }

    public static function bolehKelolaKegiatan($pengguna): bool
    {
        return in_array($pengguna?->peran?->nama_peran, self::KELOLA_KEGIATAN, true);
    }

    /** Halaman baca-saja yang boleh dibuka semua peran: pilih layout sesuai peran. */
    public static function layoutHalaman($pengguna): string
    {
        return in_array($pengguna?->peran?->nama_peran, self::LIHAT_BIDANG_SENDIRI, true)
            ? 'layouts.bidang'
            : 'layouts.admin';
    }
}