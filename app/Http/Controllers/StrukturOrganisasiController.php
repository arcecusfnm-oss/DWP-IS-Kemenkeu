<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/**
 * B-018 T-003: hierarki struktur organisasi (read-only, JSON).
 * Hanya nama dan jabatan; tanpa data pribadi.
 * Akses: semua pengguna yang login (middleware di routes/web.php).
 */
class StrukturOrganisasiController extends Controller
{
    public function index()
    {
        // leftJoin: slot kosong (atau anggota nonaktif/terhapus) tetap muncul dengan nama null
        $pejabat = DB::table('jabatan_dwp as j')
            ->leftJoin('anggota as a', function ($join) {
                $join->on('a.id_anggota', '=', 'j.id_anggota')
                    ->whereNull('a.dihapus_pada')
                    ->where('a.status', 'aktif');
            })
            ->orderBy('j.urutan')
            ->orderBy('j.id_jabatan')
            ->get(['j.jenis', 'j.nama_jabatan', 'a.nama_lengkap']);

        $ketua = $pejabat->firstWhere('jenis', 'ketua');

        $wakil = $pejabat->where('jenis', 'wakil_ketua')
            ->map(fn($p) => ['jabatan' => $p->nama_jabatan, 'nama' => $p->nama_lengkap])
            ->values()
            ->all();

        // Sekretariat dan Bendahara juga baris di tabel bidang; dipisah supaya tidak dobel
        $tetap = collect(config('dwp.peran_bidang_tetap', []))->flatten()->all();

        $bidang = DB::table('bidang')
            ->whereNotIn('nama_bidang', $tetap)
            ->orderBy('nama_bidang')
            ->pluck('nama_bidang')
            ->map(fn($nama) => ['nama' => $nama])
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'ketua_dwp' => [
                    'jabatan' => $ketua->nama_jabatan ?? 'Ketua DWP',
                    'nama'    => $ketua->nama_lengkap ?? null,
                ],
                'wakil_ketua_dwp' => $wakil,
                'sekretariat'     => ['nama' => config('dwp.peran_bidang_tetap.Admin.0', 'Sekretariat')],
                'bendahara'       => ['nama' => config('dwp.peran_bidang_tetap.Bendahara.0', 'Bendahara')],
                'bidang'          => $bidang,
            ],
        ]);
    }
}