<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PencarianKeahlianController extends Controller
{
    public function cari(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $kataKunci = trim($validated['q']);

        $anggota = DB::table('anggota')
            ->leftJoin('bidang', 'anggota.id_bidang', '=', 'bidang.id_bidang')
            ->leftJoin('unit_kerja', 'anggota.id_unit_kerja', '=', 'unit_kerja.id_unit_kerja')
            ->where('anggota.status', 'aktif')
            ->whereNull('anggota.dihapus_pada')
            ->where('anggota.keahlian', 'like', '%' . $kataKunci . '%')
            ->select(
                'anggota.nama_lengkap',
                'bidang.nama_bidang',
                'unit_kerja.nama_unit_kerja'
            )
            ->orderBy('anggota.nama_lengkap')
            ->limit(50)
            ->get();

        return response()->json([
            'hasil' => $anggota,
        ]);
    }
}
