<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bidang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BidangController extends Controller
{
    private const PESAN_UMUM = 'Nama bidang tidak dapat diubah.';

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

        // B-025 T-014: form ubah nama bidang
    public function edit($id)
    {
        $bidang = DB::table('bidang')
            ->where('id_bidang', $id)
            ->first(['id_bidang', 'nama_bidang']);

        abort_if(! $bidang, 404);

        return view('admin.bidang.edit', [
            'bidang'  => $bidang,
            'dikunci' => $this->namaDikunci($bidang->nama_bidang),
        ]);
    }

    // B-025 T-014: simpan nama bidang baru
    public function update(Request $request, $id)
    {
        // AC: bidang tidak ada -> 404
        abort_if(! DB::table('bidang')->where('id_bidang', $id)->exists(), 404);

        // AC 5: pangkas spasi awal/akhir; yang hanya spasi jadi kosong -> ditolak 'required'
        $request->merge([
            'nama_bidang' => is_string($request->input('nama_bidang'))
                ? trim($request->input('nama_bidang'))
                : $request->input('nama_bidang'),
        ]);

        $request->validate([
            'nama_bidang' => [
                'required',
                'string',
                'max:100',
                Rule::unique('bidang', 'nama_bidang')->ignore($id, 'id_bidang'),
            ],
        ], [
            'nama_bidang.required' => 'Nama bidang wajib diisi.',
            'nama_bidang.string'   => 'Nama bidang harus berupa teks.',
            'nama_bidang.max'      => 'Nama bidang maksimal 100 karakter.',
            'nama_bidang.unique'   => 'Nama bidang tersebut sudah tersedia.',
        ]);

        $namaBaru = $request->input('nama_bidang');

        try {
            $hasil = DB::transaction(function () use ($id, $namaBaru) {
                $bidang = DB::table('bidang')
                    ->where('id_bidang', $id)
                    ->lockForUpdate()
                    ->first(['id_bidang', 'nama_bidang']);

                if (! $bidang) {
                    abort(404);
                }

                // Nama yang dipakai config (peran_bidang_tetap) tidak boleh berubah
                if ($this->namaDikunci($bidang->nama_bidang)) {
                    return ['status' => 'dikunci'];
                }

                if ($bidang->nama_bidang === $namaBaru) {
                    return ['status' => 'sama'];
                }

                DB::table('bidang')
                    ->where('id_bidang', $bidang->id_bidang)
                    ->update([
                        'nama_bidang' => $namaBaru,
                        'updated_at'  => now(),
                    ]);

                return ['status' => 'ok', 'bidang' => $bidang];
            });
        } catch (QueryException $e) {
            // Dua request bersamaan dengan nama sama: ditahan unique index di DB
            if ($e->getCode() === '23000') {
                return back()->withErrors(['nama_bidang' => 'Nama bidang tersebut sudah tersedia.'])->withInput();
            }
            throw $e;
        }

        if ($hasil['status'] === 'dikunci') {
            return back()->withErrors([
                'nama_bidang' => 'Nama bidang ini dipakai sistem dan tidak dapat diubah.',
            ])->withInput();
        }

        if ($hasil['status'] === 'ok') {
            // AC 4: waktu, pelaku, nama lama, nama baru; tanpa data sensitif
            Log::info('Admin mengubah nama bidang', [
                'admin_id'       => Auth::id(),
                'admin_username' => Auth::user()->nama_pengguna,
                'bidang_id'      => $hasil['bidang']->id_bidang,
                'nama_lama'      => $hasil['bidang']->nama_bidang,
                'nama_baru'      => $namaBaru,
                'dicatat_pada'   => now()->toDateTimeString(),
            ]);
        }

        // 'sama' = tidak ada perubahan, tidak ditulis dan tidak dilog
        return redirect()
            ->route('admin.bidang.index')
            ->with('success', 'Nama bidang berhasil diubah.');
    }

    private function namaDikunci(string $nama): bool
    {
        return collect(config('dwp.peran_bidang_tetap', []))
            ->flatten()
            ->contains($nama);
    }
}
