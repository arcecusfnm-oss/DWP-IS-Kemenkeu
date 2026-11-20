<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bidang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BidangController extends Controller
{
    // Menampilkan daftar bidang
    public function index()
    {
        $bidangs = Bidang::orderBy('nama_bidang')->get();

        // T-33: nama ketua per bidang (hanya yang aktif dan belum dihapus)
        $ketuaBidang = DB::table('anggota')
            ->where('jabatan_bidang', 'ketua')
            ->where('status', 'aktif')
            ->whereNull('dihapus_pada')
            ->pluck('nama_lengkap', 'id_bidang');

        // Nama wakil ketua per bidang (hanya yang aktif dan belum dihapus)
        $wakilKetuaBidang = DB::table('anggota')
            ->where('jabatan_bidang', 'wakil_ketua')
            ->where('status', 'aktif')
            ->whereNull('dihapus_pada')
            ->pluck('nama_lengkap', 'id_bidang');

        // T-33: pilihan ketua per bidang. Syaratnya sama dengan validasi T-32:
        // anggota aktif, belum dihapus, dan akun terhubung (kalau ada) aktif.
        $anggotaBidang = DB::table('anggota')
            ->leftJoin('pengguna', 'pengguna.id_pengguna', '=', 'anggota.id_pengguna')
            ->where('anggota.status', 'aktif')
            ->whereNull('anggota.dihapus_pada')
            ->where(function ($w) {
                $w->whereNull('anggota.id_pengguna')
                    ->orWhere(function ($x) {
                        $x->whereNull('pengguna.dihapus_pada')
                            ->where('pengguna.status', 'aktif');
                    });
            })
            ->orderBy('anggota.nama_lengkap')
            ->get(['anggota.id_anggota', 'anggota.id_bidang', 'anggota.nama_lengkap', 'anggota.jabatan_bidang'])
            ->groupBy('id_bidang')
            ->map(fn($g) => $g->map(fn($a) => [
                'id'    => $a->id_anggota,
                'nama'   => $a->nama_lengkap,
                'ketua'  => $a->jabatan_bidang === 'ketua',
                'wakil'  => $a->jabatan_bidang === 'wakil_ketua',
            ])->values())
            ->all();

        return view('admin.bidang.index', compact(
            'bidangs',
            'ketuaBidang',
            'wakilKetuaBidang',
            'anggotaBidang'
        ));
    }

    // Menampilkan form tambah bidang
    public function create()
    {
        return view('admin.bidang.create');
    }

    // Menyimpan bidang baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bidang' => [
                'required',
                'string',
                'max:255',
                'unique:bidang,nama_bidang',
            ],
        ], [
            'nama_bidang.required' => 'Nama bidang wajib diisi.',
            'nama_bidang.string' => 'Nama bidang harus berupa teks.',
            'nama_bidang.max' => 'Nama bidang maksimal 255 karakter.',
            'nama_bidang.unique' => 'Nama bidang tersebut sudah tersedia.',
        ]);

        Bidang::create($validated);

        return redirect()
            ->route('admin.bidang.index')
            ->with('success', 'Data berhasil disimpan.');
    }
}
