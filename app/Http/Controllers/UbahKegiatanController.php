<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * T-089 (B-049): endpoint ubah kegiatan pada program kerja.
 * - Mengubah nama_kegiatan, tanggal_mulai, tanggal_selesai, dan deskripsi
 * - Validasi tanggal kegiatan berada dalam rentang periode program kerja
 * - Hanya bidang pemilik program kerja yang dapat mengubah
 * - Mencatat perubahan di log (waktu, pelaku, ID program, ID kegiatan) tanpa data sensitif
 */
class UbahKegiatanController extends Controller
{
    public function update(Request $request, int $id)
    {
        $idBidang = DB::table('pengguna')
            ->where('id_pengguna', Auth::id())
            ->whereNull('dihapus_pada')
            ->value('id_bidang');

        if (! $idBidang) {
            return $this->gagal($request, 'Akun Anda belum terhubung dengan bidang.', 403);
        }

        try {
            $kegiatan = DB::table('kegiatan')
                ->where('id_kegiatan', $id)
                ->first([
                    'id_kegiatan',
                    'id_program_kerja',
                    'nama_kegiatan',
                    'tanggal_mulai',
                    'tanggal_selesai',
                    'deskripsi',
                ]);
        } catch (QueryException $e) {
            Log::error('Gagal memuat data kegiatan untuk diubah', [
                'pengguna_id'  => Auth::id(),
                'id_kegiatan'  => $id,
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa diubah. Silakan coba lagi.', 500);
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
                    'tanggal_mulai',
                    'tanggal_selesai',
                ]);
        } catch (QueryException $e) {
            Log::error('Gagal memuat program kerja untuk ubah kegiatan', [
                'pengguna_id'      => Auth::id(),
                'id_program_kerja' => $kegiatan->id_program_kerja,
                'id_kegiatan'      => $id,
                'kode_galat'       => $e->getCode(),
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa diubah. Silakan coba lagi.', 500);
        }

        if (! $program) {
            abort(404, 'Program kerja tidak ditemukan.');
        }

        if ((int) $program->id_bidang !== (int) $idBidang) {
            Log::warning('Ubah kegiatan pada program bidang lain ditolak', [
                'pengguna_id'      => Auth::id(),
                'id_bidang_akun'   => $idBidang,
                'id_program_kerja' => $program->id_program_kerja,
                'id_kegiatan'      => $id,
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            abort(403, 'Anda tidak memiliki hak akses.');
        }

        $mulaiProgram   = substr((string) $program->tanggal_mulai, 0, 10);
        $selesaiProgram = substr((string) $program->tanggal_selesai, 0, 10);
        $periode = Carbon::parse($mulaiProgram)->format('d-m-Y')
            . ' s/d ' . Carbon::parse($selesaiProgram)->format('d-m-Y');

        $aturan = [
            'nama_kegiatan'   => ['required', 'string', 'max:255'],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'deskripsi'       => ['required', 'string', 'max:5000'],
        ];

        $pesan = [
            'nama_kegiatan.required'         => 'Nama kegiatan wajib diisi',
            'nama_kegiatan.string'           => 'Nama kegiatan tidak valid',
            'nama_kegiatan.max'              => 'Nama kegiatan maksimal 255 karakter',
            'tanggal_mulai.required'         => 'Tanggal mulai wajib diisi',
            'tanggal_mulai.date_format'      => 'Format tanggal mulai tidak valid',
            'tanggal_selesai.required'       => 'Tanggal selesai wajib diisi',
            'tanggal_selesai.date_format'    => 'Format tanggal selesai tidak valid',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
            'deskripsi.required'             => 'Deskripsi wajib diisi',
            'deskripsi.string'               => 'Deskripsi tidak valid',
            'deskripsi.max'                  => 'Deskripsi maksimal 5000 karakter',
        ];

        $validator = Validator::make($request->all(), $aturan, $pesan);

        $validator->after(function ($v) use ($request, $mulaiProgram, $selesaiProgram, $periode) {
            if ($v->errors()->hasAny(['tanggal_mulai', 'tanggal_selesai'])) {
                return;
            }

            $mulai   = (string) $request->input('tanggal_mulai');
            $selesai = (string) $request->input('tanggal_selesai');

            if ($mulai < $mulaiProgram || $mulai > $selesaiProgram) {
                $v->errors()->add('tanggal_mulai', "Tanggal mulai kegiatan harus dalam periode program ($periode)");
            }

            if ($selesai < $mulaiProgram || $selesai > $selesaiProgram) {
                $v->errors()->add('tanggal_selesai', "Tanggal selesai kegiatan harus dalam periode program ($periode)");
            }
        });

        $data = $validator->validate();

        try {
            DB::table('kegiatan')
                ->where('id_kegiatan', $id)
                ->update([
                    'nama_kegiatan'  => $data['nama_kegiatan'],
                    'tanggal_mulai'  => $data['tanggal_mulai'],
                    'tanggal_selesai'=> $data['tanggal_selesai'],
                    'deskripsi'      => $data['deskripsi'],
                    'updated_at'     => now(),
                ]);
        } catch (QueryException $e) {
            Log::error('Gagal menyimpan perubahan kegiatan', [
                'pelaku_id'        => Auth::id(),
                'id_program_kerja' => $program->id_program_kerja,
                'id_kegiatan'      => $id,
                'kode_galat'       => $e->getCode(),
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa diubah. Silakan coba lagi.', 500);
        }

        Log::info('Kegiatan program kerja diubah', [
            'pelaku_id'        => Auth::id(),
            'pelaku_username'  => Auth::user()?->nama_pengguna,
            'peran'            => Auth::user()?->peran?->nama_peran,
            'id_program_kerja' => $program->id_program_kerja,
            'id_kegiatan'      => $id,
            'dicatat_pada'     => now()->toDateTimeString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'pesan'            => 'Kegiatan berhasil diubah.',
                'id_kegiatan'      => $id,
                'id_program_kerja' => $program->id_program_kerja,
            ]);
        }

        return redirect()
            ->route('kegiatan.index')
            ->with('sukses', 'Kegiatan berhasil diubah.');
    }

    private function gagal(Request $request, string $pesan, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], $status);
        }

        return redirect()->back()->withInput()->with('galat', $pesan);
    }
}