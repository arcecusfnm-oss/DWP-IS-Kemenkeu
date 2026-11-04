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

        return view('admin.dashboard', compact('namaUser', 'peranUser', 'totalAnggota'));
    }
}