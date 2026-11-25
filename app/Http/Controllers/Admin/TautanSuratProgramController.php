<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * B-040 (T-068): tautkan dan lepas tautan surat masuk ke program kerja.
 *
 * Akses : Admin dan bidang Sekretariat (middleware AksesPersuratan di routes/web.php),
 *         sama dengan fitur surat lainnya.
 * Tautan: hanya surat masuk. Pilihan program kerja hanya dari program yang ada
 *         (nilai bebas ditolak). Melepas tautan tidak menghapus catatan surat.
 */
class TautanSuratProgramController extends Controller
{
    public function pilihan()
    {
        $program = DB::table('program_kerja')
            ->join('bidang', 'bidang.id_bidang', '=', 'program_kerja.id_bidang')
            ->orderByDesc('program_kerja.tanggal_mulai')
            ->orderByDesc('program_kerja.id_program_kerja')
            ->get([
                'program_kerja.id_program_kerja',
                'program_kerja.nama_program',
                'program_kerja.tanggal_mulai',
                'bidang.nama_bidang',
            ]);

        return response()->json($program);
    }

    public function suratTerkait(int $idProgram)
    {
        if (! $this->programAktif($idProgram)) {
            abort(404, 'Program kerja tidak ditemukan.');
        }

        $surat = DB::table('surat_masuk')
            ->where('id_program_kerja', $idProgram)
            ->orderByDesc('tanggal_surat')
            ->orderByDesc('id_surat_masuk')
            ->get(['id_surat_masuk', 'nomor_agenda', 'nomor_surat', 'perihal', 'tanggal_surat']);

        return response()->json($surat);
    }

    public function tautkan(Request $request, int $id)
    {
        $data = Validator::make($request->all(), [
            'id_program_kerja' => ['required', 'integer', 'min:1'],
        ], [
            'id_program_kerja.required' => 'Program kerja wajib dipilih',
            'id_program_kerja.integer'  => 'Program kerja tidak valid',
            'id_program_kerja.min'      => 'Program kerja tidak valid',
        ])->validate();

        $idProgram = (int) $data['id_program_kerja'];

        $surat = DB::table('surat_masuk')->where('id_surat_masuk', $id)->first();

        if (! $surat) {
            abort(404, 'Surat tidak ditemukan.');
        }

        if (! $this->programAktif($idProgram)) {
            throw ValidationException::withMessages([
                'id_program_kerja' => 'Program kerja tidak tersedia',
            ]);
        }

        // Tanpa perubahan: tidak menulis dan tidak mencatat log audit.
        if ((int) $surat->id_program_kerja === $idProgram) {
            return $this->selesai($request, 'Surat sudah tertaut ke program kerja ini.', $id, $idProgram);
        }

        try {
            DB::table('surat_masuk')->where('id_surat_masuk', $id)->update([
                'id_program_kerja' => $idProgram,
                'updated_at'       => now(),
            ]);
        } catch (QueryException $e) {
            return $this->gagal($request, 'Tautan belum bisa disimpan', $id, $e);
        }

        Log::info('Surat ditautkan ke program kerja', [
            'pengubah_id'       => Auth::id(),
            'pengubah_username' => Auth::user()->nama_pengguna,
            'peran'             => Auth::user()->peran?->nama_peran,
            'id_surat_masuk'    => $id,
            'id_program_lama'   => $surat->id_program_kerja,
            'id_program_baru'   => $idProgram,
            'dicatat_pada'      => now()->toDateTimeString(),
        ]);

        return $this->selesai($request, 'Surat berhasil ditautkan ke program kerja.', $id, $idProgram);
    }

    public function lepas(Request $request, int $id)
    {
        $surat = DB::table('surat_masuk')->where('id_surat_masuk', $id)->first();

        if (! $surat) {
            abort(404, 'Surat tidak ditemukan.');
        }

        if ($surat->id_program_kerja === null) {
            return $this->selesai($request, 'Surat belum tertaut ke program kerja.', $id, null);
        }

        try {
            DB::table('surat_masuk')->where('id_surat_masuk', $id)->update([
                'id_program_kerja' => null,
                'updated_at'       => now(),
            ]);
        } catch (QueryException $e) {
            return $this->gagal($request, 'Tautan belum bisa dilepas', $id, $e);
        }

        Log::info('Tautan surat ke program kerja dilepas', [
            'pengubah_id'       => Auth::id(),
            'pengubah_username' => Auth::user()->nama_pengguna,
            'peran'             => Auth::user()->peran?->nama_peran,
            'id_surat_masuk'    => $id,
            'id_program_lama'   => $surat->id_program_kerja,
            'dicatat_pada'      => now()->toDateTimeString(),
        ]);

        return $this->selesai($request, 'Tautan surat ke program kerja dilepas.', $id, null);
    }

    /**
     * Satu-satunya tempat untuk menentukan program yang masih dianggap ada.
     * Saat B-047 menambah penanda hapus lunak, tambahkan ->whereNull('<kolom penanda>') di sini.
     */
    private function programAktif(int $idProgram): bool
    {
        return DB::table('program_kerja')
            ->where('id_program_kerja', $idProgram)
            ->exists();
    }

    private function selesai(Request $request, string $pesan, int $id, ?int $idProgram)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'pesan'            => $pesan,
                'id_surat_masuk'   => $id,
                'id_program_kerja' => $idProgram,
            ]);
        }

        return redirect()->back()->with('sukses', $pesan);
    }

    private function gagal(Request $request, string $pesan, int $id, QueryException $e)
    {
        Log::error('Gagal mengubah tautan surat ke program kerja', [
            'pengguna_id'    => Auth::id(),
            'id_surat_masuk' => $id,
            'kode_galat'     => $e->getCode(),
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        $pesan .= '. Silakan coba lagi.';

        return $request->expectsJson()
            ? response()->json(['pesan' => $pesan], 500)
            : redirect()->back()->with('galat', $pesan);
    }
}