<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AkunController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->join('bidang', 'pengguna.id_bidang', '=', 'bidang.id_bidang')
            ->whereNull('pengguna.dihapus_pada')
            ->select(
                'pengguna.id_pengguna',
                'pengguna.nama_lengkap',
                'pengguna.nama_pengguna',
                'pengguna.status',
                'peran.nama_peran',
                'bidang.nama_bidang'
            );

        if ($request->filled('peran')) {
            $query->where('peran.nama_peran', $request->peran);
        }
        if ($request->filled('bidang')) {
            $query->where('bidang.nama_bidang', $request->bidang);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($w) use ($q) {
                $w->where('pengguna.nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('pengguna.nama_pengguna', 'like', "%{$q}%");
            });
        }

        $akun = $query->orderBy('pengguna.id_pengguna')->paginate(10)->withQueryString();

        // Statistik (dihitung dari seluruh akun, tidak ikut filter)
        $dasar = DB::table('pengguna')->whereNull('dihapus_pada');
        $stat = [
            'total'    => (clone $dasar)->count(),
            'aktif'    => (clone $dasar)->where('status', 'aktif')->count(),
            'nonaktif' => (clone $dasar)->where('status', 'nonaktif')->count(),
            'admin'    => DB::table('pengguna')
                ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
                ->whereNull('pengguna.dihapus_pada')
                ->where('peran.nama_peran', 'Admin')
                ->count(),
        ];

        $daftarPeran  = DB::table('peran')->orderBy('id_peran')->pluck('nama_peran');
        $daftarBidang = DB::table('bidang')->orderBy('nama_bidang')->pluck('nama_bidang');

        return view('admin.akun.index', compact('akun', 'stat', 'daftarPeran', 'daftarBidang'));
    }

    public function create()
    {
        $daftarPeran  = DB::table('peran')->orderBy('id_peran')->pluck('nama_peran');
        $daftarBidang = DB::table('bidang')->orderBy('nama_bidang')->pluck('nama_bidang');

        return view('admin.akun.create', compact('daftarPeran', 'daftarBidang'));
    }

    public function edit($id)
    {
        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->join('bidang', 'pengguna.id_bidang', '=', 'bidang.id_bidang')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select(
                'pengguna.id_pengguna',
                'pengguna.nama_lengkap',
                'pengguna.nama_pengguna',
                'peran.nama_peran',
                'bidang.nama_bidang'
            )
            ->first();

        abort_if(!$akun, 404);

        $daftarBidang = DB::table('bidang')->orderBy('nama_bidang')->pluck('nama_bidang');

        return view('admin.akun.edit', compact('akun', 'daftarBidang'));
    }

    public function update(Request $request, $id)
    {
        $ada = DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->whereNull('dihapus_pada')
            ->exists();

        abort_if(!$ada, 404);

        $request->validate([
            'nama'     => 'required|string|max:255',
            'username' => ['required', 'string', 'max:50',
                Rule::unique('pengguna', 'nama_pengguna')->ignore($id, 'id_pengguna')],
            'bidang'   => ['required', Rule::exists('bidang', 'nama_bidang')],
        ], [
            'nama.required'     => 'Nama lengkap wajib diisi',
            'username.required' => 'Nama pengguna wajib diisi',
            'username.unique'   => 'Nama pengguna sudah digunakan',
            'bidang.required'   => 'Bidang wajib dipilih',
            'bidang.exists'     => 'Bidang tidak valid',
        ]);

        $idBidang = DB::table('bidang')->where('nama_bidang', $request->bidang)->value('id_bidang');

        DB::table('pengguna')->where('id_pengguna', $id)->update([
            'nama_lengkap'  => $request->nama,
            'nama_pengguna' => $request->username,
            'id_bidang'     => $idBidang,
            'updated_at'    => now(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Data akun berhasil diubah.');
    }

    public function editPeran($id)
    {
        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select(
                'pengguna.id_pengguna',
                'pengguna.nama_lengkap',
                'pengguna.nama_pengguna',
                'peran.nama_peran'
            )
            ->first();

        abort_if((int) $id === (int) Auth::id(), 403);
        abort_if(!$akun, 404);

        $daftarPeran = DB::table('peran')->orderBy('id_peran')->pluck('nama_peran');

        return view('admin.akun.peran', compact('akun', 'daftarPeran'));
    }

    public function updatePeran(Request $request, $id)
    {
        // Admin tidak boleh mengubah peran akunnya sendiri
        abort_if((int) $id === (int) Auth::id(), 403);

        $ada = DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->whereNull('dihapus_pada')
            ->exists();

        abort_if(!$ada, 404);

        $request->validate([
            'peran' => ['required', Rule::exists('peran', 'nama_peran')],
        ], [
            'peran.required' => 'Peran wajib dipilih',
            'peran.exists'   => 'Peran tidak valid',
        ]);

        $idPeran = DB::table('peran')
            ->where('nama_peran', $request->peran)
            ->value('id_peran');

        $idPeranLama = DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->value('id_peran');

        $namaPeranLama = DB::table('peran')
            ->where('id_peran', $idPeranLama)
            ->value('nama_peran');

        $namaPeranBaru = $request->peran;

        if ((int) $idPeran === (int) $idPeranLama) {
            return back()
                ->withErrors(['peran' => 'Peran baru tidak boleh sama dengan peran saat ini'])
                ->withInput();
        }

        DB::table('pengguna')->where('id_pengguna', $id)->update([
            'id_peran'   => $idPeran,
            'updated_at' => now(),
        ]);

        Log::info('Admin mengubah peran akun', [
            'admin_id'       => Auth::id(),
            'akun_id'        => $id,
            'peran_lama_id'  => $idPeranLama,
            'peran_lama'     => $namaPeranLama,
            'peran_baru_id'  => $idPeran,
            'peran_baru'     => $namaPeranBaru,
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Peran akun berhasil diubah.');
    }

    public function destroy($id)
    {
        abort_if((int) $id === (int) Auth::id(), 403);

        $ada = DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->whereNull('dihapus_pada')
            ->exists();

        abort_if(!$ada, 404);

        DB::table('pengguna')->where('id_pengguna', $id)->update([
            'dihapus_pada' => now(),
            'updated_at'   => now(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Akun berhasil dihapus.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'     => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pengguna', 'nama_pengguna')
            ],
            'peran'    => ['required', Rule::exists('peran', 'nama_peran')],
            'bidang'   => ['required', Rule::exists('bidang', 'nama_bidang')],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], [
            'nama.required'     => 'Nama lengkap wajib diisi',
            'username.required' => 'Nama pengguna wajib diisi',
            'username.unique'   => 'Nama pengguna sudah digunakan',
            'peran.required'    => 'Peran wajib dipilih',
            'peran.exists'      => 'Peran tidak valid',
            'bidang.required'   => 'Bidang wajib dipilih',
            'bidang.exists'     => 'Bidang tidak valid',
            'password.required' => 'Kata sandi awal wajib diisi',
            'password.min'      => 'Kata sandi awal minimal 8 karakter',
        ]);

        $idPeran  = DB::table('peran')->where('nama_peran', $request->peran)->value('id_peran');
        $idBidang = DB::table('bidang')->where('nama_bidang', $request->bidang)->value('id_bidang');

        DB::table('pengguna')->insert([
            'nama_lengkap'           => $request->nama,
            'nama_pengguna'          => $request->username,
            'kata_sandi_hash'        => Hash::make($request->password),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => 1,
            'id_peran'               => $idPeran,
            'id_bidang'              => $idBidang,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        Log::info('Admin membuat akun baru', [
            'admin_id'       => Auth::id(),
            'admin_username' => Auth::user()->nama_pengguna,
            'akun_baru'      => $request->username,
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Akun berhasil dibuat.');
    }

    public function resetPassword($id)
    {
        $akun = DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->whereNull('dihapus_pada')
            ->first();

        abort_if(!$akun, 404);

        $kataSandiSementara = Str::password(12);

        DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->update([
                'kata_sandi_hash'        => Hash::make($kataSandiSementara),
                'perlu_ganti_kata_sandi' => 1,
                'updated_at'             => now(),
            ]);

        Log::info('Admin mereset kata sandi akun', [
            'admin_id'     => Auth::id(),
            'akun_id'      => $id,
            'dicatat_pada' => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Kata sandi berhasil direset.')
            ->with('kata_sandi_sementara', $kataSandiSementara);
    }
}
