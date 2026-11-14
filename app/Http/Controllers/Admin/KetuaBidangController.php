<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T-32 (B-017): Admin menetapkan ketua bidang.
 * Jabatan disimpan di anggota.jabatan_bidang ('ketua' / 'wakil_ketua').
 */
class KetuaBidangController extends Controller
{
    private const PESAN_UMUM = 'Anggota tidak dapat ditetapkan sebagai ketua.';

    public function update(Request $request, $id)
    {
        abort_if(! DB::table('bidang')->where('id_bidang', $id)->exists(), 404);

        $request->validate([
            'id_anggota' => ['required', 'integer'],
        ], [
            'id_anggota.required' => 'Anggota wajib dipilih.',
            'id_anggota.integer'  => self::PESAN_UMUM,
        ]);

        $idAnggota = (int) $request->input('id_anggota');

        $hasil = DB::transaction(function () use ($id, $idAnggota) {
            // kunci baris bidang supaya dua request bersamaan tidak saling menimpa
            $bidang = DB::table('bidang')
                ->where('id_bidang', $id)
                ->lockForUpdate()
                ->first(['id_bidang', 'nama_bidang']);

            if (! $bidang) {
                abort(404);
            }

            // Syarat AC 3: anggota di bidang ini, aktif, tidak dihapus,
            // dan (kalau punya akun) akunnya aktif dan tidak dihapus.
            $anggota = DB::table('anggota')
                ->leftJoin('pengguna', 'pengguna.id_pengguna', '=', 'anggota.id_pengguna')
                ->where('anggota.id_anggota', $idAnggota)
                ->where('anggota.id_bidang', $bidang->id_bidang)
                ->where('anggota.status', 'aktif')
                ->whereNull('anggota.dihapus_pada')
                ->where(function ($w) {
                    $w->whereNull('anggota.id_pengguna')
                        ->orWhere(function ($x) {
                            $x->whereNull('pengguna.dihapus_pada')
                                ->where('pengguna.status', 'aktif');
                        });
                })
                ->first(['anggota.id_anggota', 'anggota.nama_lengkap', 'anggota.jabatan_bidang']);

            if (! $anggota) {
                return ['status' => 'ditolak'];
            }

            if ($anggota->jabatan_bidang === 'ketua') {
                return ['status' => 'sudah'];
            }

            // AC 2: lepas jabatan ketua lama di bidang ini
            $ketuaLama = DB::table('anggota')
                ->where('id_bidang', $bidang->id_bidang)
                ->where('jabatan_bidang', 'ketua')
                ->value('id_anggota');

            DB::table('anggota')
                ->where('id_bidang', $bidang->id_bidang)
                ->where('jabatan_bidang', 'ketua')
                ->update(['jabatan_bidang' => null, 'updated_at' => now()]);

            DB::table('anggota')
                ->where('id_anggota', $anggota->id_anggota)
                ->update(['jabatan_bidang' => 'ketua', 'updated_at' => now()]);

            return [
                'status'     => 'ok',
                'bidang'     => $bidang,
                'anggota'    => $anggota,
                'ketua_lama' => $ketuaLama,
            ];
        });

        if ($hasil['status'] === 'ditolak') {
            return back()->withErrors(['id_anggota' => self::PESAN_UMUM])->withInput();
        }

        if ($hasil['status'] === 'sudah') {
            return back()->withErrors(['id_anggota' => 'Anggota tersebut sudah menjadi ketua bidang ini.'])->withInput();
        }

        // AC 6: waktu, Admin pelaku, bidang, anggota; tanpa data sensitif
        Log::info('Admin menetapkan ketua bidang', [
            'admin_id'       => Auth::id(),
            'admin_username' => Auth::user()->nama_pengguna,
            'bidang_id'      => $hasil['bidang']->id_bidang,
            'nama_bidang'    => $hasil['bidang']->nama_bidang,
            'anggota_id'     => $hasil['anggota']->id_anggota,
            'nama_anggota'   => $hasil['anggota']->nama_lengkap,
            'ketua_lama_id'  => $hasil['ketua_lama'],
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        return redirect()
            ->route('admin.bidang.index')
            ->with('success', 'Ketua bidang berhasil ditetapkan.');
    }
}