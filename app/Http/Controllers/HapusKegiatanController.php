<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * T-094 (B-050): endpoint hapus kegiatan pada program kerja.
 * - Konfirmasi di sisi server sebelum menghapus
 * - Hanya bidang pemilik program kerja yang dapat menghapus
 * - Hapus lunak pada kolom hapus lunak jika tersedia, tanpa menghapus permanen / merusak data program induk
 * - Mencatat aktivitas dan galat di log tanpa data sensitif
 */
class HapusKegiatanController extends Controller
{
    public function destroy(Request $request, int $id)
    {
        $idBidang = DB::table('pengguna')
            ->where('id_pengguna', Auth::id())
            ->whereNull('dihapus_pada')
            ->value('id_bidang');

        if (! $idBidang) {
            return $this->gagal($request, 'Akun Anda belum terhubung dengan bidang.', 500);
        }

        // Konfirmasi wajib di sisi server
        $dikonfirmasi = filter_var(
            $request->input('konfirmasi', $request->input('confirm', false)),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($dikonfirmasi !== true) {
            return $this->gagal($request, 'Konfirmasi penghapusan kegiatan diperlukan.', 500);
        }

        try {
            $kegiatan = DB::table('kegiatan')
                ->where('id_kegiatan', $id)
                ->first([
                    'id_kegiatan',
                    'id_program_kerja',
                    'nama_kegiatan',
                ]);
        } catch (QueryException $e) {
            Log::error('Gagal memuat data kegiatan untuk dihapus', [
                'pengguna_id'  => Auth::id(),
                'id_kegiatan'  => $id,
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa dihapus. Silakan coba lagi.', 500);
        }

        if (! $kegiatan) {
            abort(404, 'Kegiatan tidak ditemukan.');
        }

        try {
            $program = DB::table('program_kerja')
                ->where('id_program_kerja', $kegiatan->id_program_kerja)
                ->first([
                    'id_program_kerja',
                    'id_bidang',
                ]);
        } catch (QueryException $e) {
            Log::error('Gagal memuat program kerja untuk hapus kegiatan', [
                'pengguna_id'      => Auth::id(),
                'id_program_kerja' => $kegiatan->id_program_kerja,
                'id_kegiatan'      => $id,
                'kode_galat'       => $e->getCode(),
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa dihapus. Silakan coba lagi.', 500);
        }

        if (! $program) {
            abort(404, 'Program kerja tidak ditemukan.');
        }

        if ((int) $program->id_bidang !== (int) $idBidang) {
            Log::warning('Hapus kegiatan pada program bidang lain ditolak', [
                'pengguna_id'      => Auth::id(),
                'id_bidang_akun'   => $idBidang,
                'id_program_kerja' => $program->id_program_kerja,
                'id_kegiatan'      => $id,
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa dihapus. Silakan coba lagi.', 500);
        }

        $kolomHapusLunak = Schema::hasColumn('kegiatan', 'dihapus_pada')
            ? 'dihapus_pada'
            : (Schema::hasColumn('kegiatan', 'deleted_at') ? 'deleted_at' : null);

        if (! $kolomHapusLunak) {
            Log::warning('Hapus lunak kegiatan belum dapat dijalankan karena kolom hapus lunak belum tersedia', [
                'pelaku_id'        => Auth::id(),
                'id_program_kerja' => $program->id_program_kerja,
                'id_kegiatan'      => $id,
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa dihapus. Silakan coba lagi.', 500);
        }

        try {
            DB::table('kegiatan')
                ->where('id_kegiatan', $id)
                ->whereNull($kolomHapusLunak)
                ->update([
                    $kolomHapusLunak => now(),
                    'updated_at'     => now(),
                ]);
        } catch (QueryException $e) {
            Log::error('Gagal menghapus kegiatan secara lunak', [
                'pelaku_id'        => Auth::id(),
                'id_program_kerja' => $program->id_program_kerja,
                'id_kegiatan'      => $id,
                'kode_galat'       => $e->getCode(),
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa dihapus. Silakan coba lagi.', 500);
        }

        Log::info('Kegiatan program kerja dihapus (hapus lunak)', [
            'pelaku_id'        => Auth::id(),
            'pelaku_username'  => Auth::user()?->nama_pengguna,
            'peran'            => Auth::user()?->peran?->nama_peran,
            'id_program_kerja' => $program->id_program_kerja,
            'id_kegiatan'      => $id,
            'dicatat_pada'     => now()->toDateTimeString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'pesan'            => 'Kegiatan berhasil dihapus.',
                'id_kegiatan'      => $id,
                'id_program_kerja' => $program->id_program_kerja,
            ]);
        }

        return redirect()
            ->route('kegiatan.index')
            ->with('sukses', 'Kegiatan berhasil dihapus.');
    }

    private function gagal(Request $request, string $pesan, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], $status);
        }

        return redirect()->back()->withInput()->with('galat', $pesan);
    }
}