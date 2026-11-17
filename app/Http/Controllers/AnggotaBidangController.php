<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T-52 (B-022): daftar anggota bidang saya.
 *
 * Akses  : Pimpinan Bidang dan Anggota Bidang (middleware role di routes/web.php).
 *          Tanpa sesi ditolak oleh middleware auth (AC4).
 * Bidang : SELALU diambil dari akun yang masuk. Kalau request membawa ?bidang=
 *          yang bukan bidang akun, ditolak 403 di server (AC2).
 * Respons: hanya nama lengkap, jabatan bidang, dan nomor telepon (AC1).
 *          Tidak ada data akun, kata sandi, atau data pribadi lain yang ikut terbaca.
 */
class AnggotaBidangController extends Controller
{
    private const PESAN_KOSONG = 'Tidak ada anggota yang sesuai';

    public function index(Request $request)
    {
        $idBidangSaya = Auth::user()->id_bidang;

        if (! $idBidangSaya) {
            abort(403, 'Akun Anda belum terhubung dengan bidang.');
        }

        // AC2: ID bidang lain dari request tidak pernah dipakai, langsung ditolak.
        $diminta = $request->query('bidang');
        if ($diminta !== null
            && (! is_scalar($diminta) || (string) $diminta !== (string) $idBidangSaya)) {
            Log::warning('Akses daftar anggota bidang lain ditolak', [
                'pengguna_id'     => Auth::id(),
                'id_bidang_akun'  => $idBidangSaya,
                'dicatat_pada'    => now()->toDateTimeString(),
            ]);

            abort(403, 'Anda tidak memiliki hak akses.');
        }

        try {
            $anggota = DB::table('anggota')
                ->where('anggota.id_bidang', $idBidangSaya)
                ->whereNull('anggota.dihapus_pada')
                ->select(
                    'anggota.id_anggota',
                    'anggota.nama_lengkap',
                    'anggota.jabatan_bidang',
                    'anggota.nomor_telepon'
                )
                // Ketua, lalu wakil ketua, lalu anggota lain berdasarkan nama.
                ->orderByRaw("CASE anggota.jabatan_bidang WHEN 'ketua' THEN 0 WHEN 'wakil_ketua' THEN 1 ELSE 2 END")
                ->orderBy('anggota.nama_lengkap')
                ->orderBy('anggota.id_anggota')
                ->paginate(10)
                ->withQueryString();

            $namaBidang = DB::table('bidang')
                ->where('id_bidang', $idBidangSaya)
                ->value('nama_bidang');
        } catch (QueryException $e) {
            // Detail teknis hanya ke log, pengguna cukup melihat pesan umum.
            Log::error('Gagal memuat daftar anggota bidang saya', [
                'pengguna_id' => Auth::id(),
                'kode_galat'  => $e->getCode(),
            ]);

            abort(500, 'Daftar anggota belum bisa dimuat. Silakan coba lagi.');
        }

        // AC3: bidang tanpa anggota bukan error.
        $pesan = $anggota->total() === 0 ? self::PESAN_KOSONG : null;

        if ($request->expectsJson()) {
            return response()->json([
                'bidang'  => $namaBidang,
                'anggota' => $anggota,
                'pesan'   => $pesan,
            ]);
        }

        // View dibuat di T-53.
        return view('anggota.bidang-saya', [
            'anggota'    => $anggota,
            'namaBidang' => $namaBidang,
            'pesan'      => $pesan,
        ]);
    }
}