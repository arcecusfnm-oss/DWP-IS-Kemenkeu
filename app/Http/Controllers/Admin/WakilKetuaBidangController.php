<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WakilKetuaBidangController extends Controller
{
    private const PESAN_UMUM = 'Anggota tidak dapat ditetapkan sebagai wakil ketua.';

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
            // Kunci bidang supaya penetapan wakil tidak bentrok
            // jika ada request yang berjalan bersamaan.
            $bidang = DB::table('bidang')
                ->where('id_bidang', $id)
                ->lockForUpdate()
                ->first(['id_bidang', 'nama_bidang']);

            if (! $bidang) {
                abort(404);
            }

            // Anggota harus aktif, berasal dari bidang yang sama,
            // belum dihapus, dan akun (jika ada) harus aktif.
            $anggota = DB::table('anggota')
                ->leftJoin(
                    'pengguna',
                    'pengguna.id_pengguna',
                    '=',
                    'anggota.id_pengguna'
                )
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
                ->first([
                    'anggota.id_anggota',
                    'anggota.nama_lengkap',
                    'anggota.jabatan_bidang',
                ]);

            if (! $anggota) {
                return ['status' => 'ditolak'];
            }

            // Ketua tidak boleh ditetapkan menjadi wakil ketua.
            if ($anggota->jabatan_bidang === 'ketua') {
                return ['status' => 'ketua'];
            }

            // Ambil wakil ketua lama untuk keperluan audit.
            $wakilLama = DB::table('anggota')
                ->where('id_bidang', $bidang->id_bidang)
                ->where('jabatan_bidang', 'wakil_ketua')
                ->value('id_anggota');

            // Kosongkan jabatan wakil ketua sebelumnya di bidang ini.
            DB::table('anggota')
                ->where('id_bidang', $bidang->id_bidang)
                ->where('jabatan_bidang', 'wakil_ketua')
                ->update([
                    'jabatan_bidang' => null,
                    'updated_at'      => now(),
                ]);

            // Tetapkan anggota yang dipilih sebagai wakil ketua.
            DB::table('anggota')
                ->where('id_anggota', $anggota->id_anggota)
                ->update([
                    'jabatan_bidang' => 'wakil_ketua',
                    'updated_at'      => now(),
                ]);

            return [
                'status'     => 'ok',
                'bidang'     => $bidang,
                'anggota'    => $anggota,
                'wakil_lama' => $wakilLama,
            ];
        });

        if ($hasil['status'] === 'ditolak') {
            return back()
                ->withErrors(['id_anggota' => self::PESAN_UMUM])
                ->withInput();
        }

        if ($hasil['status'] === 'ketua') {
            return back()
                ->withErrors([
                    'id_anggota' => 'Ketua bidang tidak dapat ditetapkan sebagai wakil ketua.',
                ])
                ->withInput();
        }

        // Audit log penetapan wakil ketua.
        Log::info('Admin menetapkan wakil ketua bidang', [
            'admin_id'       => Auth::id(),
            'admin_username' => Auth::user()->nama_pengguna,
            'bidang_id'      => $hasil['bidang']->id_bidang,
            'nama_bidang'    => $hasil['bidang']->nama_bidang,
            'anggota_id'     => $hasil['anggota']->id_anggota,
            'nama_anggota'   => $hasil['anggota']->nama_lengkap,
            'wakil_lama_id' => $hasil['wakil_lama'],
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        return redirect()
            ->route('admin.bidang.index')
            ->with('success', 'Wakil ketua bidang berhasil ditetapkan.');
    }
}
