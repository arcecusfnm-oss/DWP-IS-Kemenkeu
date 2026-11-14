<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * T-42 (B-020): daftar lengkap anggota seluruh bidang, dengan filter bidang.
 *
 * Akses: hanya Admin (middleware role di routes/web.php).
 * Respons hanya memuat kolom yang dibutuhkan tabel. Tidak ada kata sandi, hash,
 * token, atau data akun (pengguna) yang ikut terbaca.
 */
class AnggotaController extends Controller
{
    public function index(Request $request)
    {
        // Nilai filter = id_bidang. Kosong berarti semua bidang.
        $request->validate([
            'bidang' => ['nullable', 'integer'],
        ], [
            'bidang.integer' => 'Filter bidang tidak valid',
        ]);

        $query = DB::table('anggota')
            ->leftJoin('bidang', 'bidang.id_bidang', '=', 'anggota.id_bidang')
            ->leftJoin('unit_kerja', 'unit_kerja.id_unit_kerja', '=', 'anggota.id_unit_kerja')
            ->whereNull('anggota.dihapus_pada')
            ->select(
                'anggota.id_anggota',
                'anggota.nama_lengkap',
                'anggota.nomor_telepon',
                'anggota.status',
                'bidang.nama_bidang',
                'unit_kerja.nama_unit_kerja'
            );

        if ($request->filled('bidang')) {
            $query->where('anggota.id_bidang', (int) $request->query('bidang'));
        }

        $anggota = $query
            ->orderBy('anggota.nama_lengkap')
            ->orderBy('anggota.id_anggota')
            ->paginate(10)
            ->withQueryString();

        $daftarBidang = DB::table('bidang')
            ->orderBy('nama_bidang')
            ->get(['id_bidang', 'nama_bidang']);

        if ($request->expectsJson()) {
            return response()->json([
                'anggota'       => $anggota,
                'daftar_bidang' => $daftarBidang,
            ]);
        }

        return view('admin.anggota.index', [
            'anggota'      => $anggota,
            'daftarBidang' => $daftarBidang,
            'filterBidang' => $request->filled('bidang') ? (int) $request->query('bidang') : null,
        ]);
    }
}