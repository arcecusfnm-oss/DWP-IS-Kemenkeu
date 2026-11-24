<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class AnggotaRingkasController extends Controller
{
    public function index()
    {
        $anggota = DB::table('anggota')
            ->leftJoin('bidang', 'anggota.id_bidang', '=', 'bidang.id_bidang')
            ->leftJoin('unit_kerja', 'anggota.id_unit_kerja', '=', 'unit_kerja.id_unit_kerja')
            ->where('anggota.status', 'aktif')
            ->whereNull('anggota.dihapus_pada')
            ->select(
                'anggota.nama_lengkap',
                'bidang.nama_bidang',
                'unit_kerja.nama_unit_kerja'
            )
            ->orderBy('anggota.nama_lengkap')
            ->get();

        return response()->json([
            'anggota' => $anggota,
        ]);
    }
}
