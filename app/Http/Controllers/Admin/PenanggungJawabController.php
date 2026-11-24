<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T-009 (B-024): Admin menetapkan penanggung jawab sistem pada bidang.
 *
 * Disimpan di bidang.id_penanggung_jawab_sistem (FK ke pengguna.id_pengguna),
 * jadi satu kolom = satu penanggung jawab per bidang. Penetapan baru menimpa
 * yang lama.
 *
 * Kandidat = akun aktif berperan multi-bidang (config dwp.peran_multi_bidang,
 * saat ini "Penanggung Jawab") yang terhubung ke bidang ini lewat pivot
 * pengguna_bidang. Satu akun boleh menjadi penanggung jawab sistem di
 * beberapa bidang sekaligus.
 *
 * Pelepasan otomatis kalau akun tidak lagi terhubung ke bidang (AC 5) ada di
 * AkunController (simpanBidang dan destroy).
 *
 * Akses: hanya Admin (middleware role di routes/web.php).
 * CSRF: otomatis dari grup middleware 'web' (AC 7).
 */
class PenanggungJawabController extends Controller
{
    private const PESAN_UMUM = 'Penanggung jawab sistem tidak dapat ditetapkan.';

    public function update(Request $request, $id)
    {
        // AC 3: bidang tidak ada
        abort_if(! DB::table('bidang')->where('id_bidang', $id)->exists(), 404);

        $request->validate([
            'id_pengguna' => ['required', 'integer'],
        ], [
            'id_pengguna.required' => 'Penanggung jawab wajib dipilih.',
            'id_pengguna.integer'  => self::PESAN_UMUM,
        ]);

        $idPengguna = (int) $request->input('id_pengguna');

        $hasil = DB::transaction(function () use ($id, $idPengguna) {
            // Kunci baris bidang supaya dua request bersamaan tidak
            // menghasilkan dua penanggung jawab atau saling menimpa.
            $bidang = DB::table('bidang')
                ->where('id_bidang', $id)
                ->lockForUpdate()
                ->first(['id_bidang', 'nama_bidang', 'id_penanggung_jawab_sistem']);

            if (! $bidang) {
                abort(404);
            }

            // AC 3: akun harus ada, aktif, belum dihapus, berperan multi-bidang,
            // dan terhubung ke bidang ini (pivot, atau kolom id_bidang).
            $akun = DB::table('pengguna')
                ->join('peran', 'peran.id_peran', '=', 'pengguna.id_peran')
                ->where('pengguna.id_pengguna', $idPengguna)
                ->whereIn('peran.nama_peran', config('dwp.peran_multi_bidang', []))
                ->where('pengguna.status', 'aktif')
                ->whereNull('pengguna.dihapus_pada')
                ->where(function ($w) use ($bidang) {
                    $w->where('pengguna.id_bidang', $bidang->id_bidang)
                        ->orWhereExists(function ($s) use ($bidang) {
                            $s->select(DB::raw(1))
                                ->from('pengguna_bidang as pb')
                                ->whereColumn('pb.id_pengguna', 'pengguna.id_pengguna')
                                ->where('pb.id_bidang', $bidang->id_bidang);
                        });
                })
                ->first(['pengguna.id_pengguna', 'pengguna.nama_lengkap']);

            if (! $akun) {
                return ['status' => 'ditolak'];
            }

            if ((int) $bidang->id_penanggung_jawab_sistem === (int) $akun->id_pengguna) {
                return ['status' => 'sudah'];
            }

            // AC 2: timpa nilai lama, tidak pernah ada dua penanggung jawab.
            DB::table('bidang')
                ->where('id_bidang', $bidang->id_bidang)
                ->update([
                    'id_penanggung_jawab_sistem' => $akun->id_pengguna,
                    'updated_at'                 => now(),
                ]);

            return [
                'status' => 'ok',
                'bidang' => $bidang,
                'akun'   => $akun,
            ];
        });

        if ($hasil['status'] === 'ditolak') {
            return back()->withErrors(['id_pengguna' => self::PESAN_UMUM])->withInput();
        }

        if ($hasil['status'] === 'sudah') {
            return back()->withErrors([
                'id_pengguna' => 'Akun tersebut sudah menjadi penanggung jawab sistem bidang ini.',
            ])->withInput();
        }

        // AC 4: waktu, Admin pelaku, bidang, akun; tanpa data sensitif
        Log::info('Admin menetapkan penanggung jawab sistem bidang', [
            'admin_id'        => Auth::id(),
            'admin_username'  => Auth::user()->nama_pengguna,
            'bidang_id'       => $hasil['bidang']->id_bidang,
            'nama_bidang'     => $hasil['bidang']->nama_bidang,
            'pj_baru_id_akun' => $hasil['akun']->id_pengguna,
            'pj_baru_nama'    => $hasil['akun']->nama_lengkap,
            'pj_lama_id_akun' => $hasil['bidang']->id_penanggung_jawab_sistem,
            'dicatat_pada'    => now()->toDateTimeString(),
        ]);

        return redirect()
            ->route('admin.bidang.index')
            ->with('success', 'Penanggung jawab sistem berhasil ditetapkan.');
    }
}
