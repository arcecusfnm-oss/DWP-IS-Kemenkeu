<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $namaUser  = 'Ny. ' . $user->nama_lengkap;
        $peranUser = DB::table('peran')->where('id_peran', $user->id_peran)->value('nama_peran');

        $totalAnggota = DB::table('pengguna')->whereNull('dihapus_pada')->count();

        // Sama dengan daftar di halaman Program Kerja (seluruhBidang): join ke bidang, tanpa filter status.
        $totalProgramKerja = DB::table('program_kerja')
            ->join('bidang', 'bidang.id_bidang', '=', 'program_kerja.id_bidang')
            ->count();

        $totalSuratMasuk = DB::table('surat_masuk')->count();

        return view('admin.dashboard', compact(
            'namaUser', 'peranUser', 'totalAnggota', 'totalProgramKerja', 'totalSuratMasuk'
        ));
    }
}