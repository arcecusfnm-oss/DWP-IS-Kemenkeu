<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class StatistikAnggotaController extends Controller
{
    public function jumlahPerBidang()
    {
        $statistik = DB::table('bidang')
            ->leftJoin('anggota', function ($join) {
                $join->on('anggota.id_bidang', '=', 'bidang.id_bidang')
                    ->where('anggota.status', 'aktif')
                    ->whereNull('anggota.dihapus_pada');
            })
            ->select(
                'bidang.id_bidang',
                'bidang.nama_bidang'
            )
            ->selectRaw('COUNT(anggota.id_anggota) AS jumlah_anggota')
            ->groupBy('bidang.id_bidang', 'bidang.nama_bidang')
            ->orderBy('bidang.nama_bidang')
            ->get();

        return response()->json([
            'statistik' => $statistik,
        ]);
    }
}
