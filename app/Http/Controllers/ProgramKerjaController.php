<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * T-81 (B-041): endpoint tambah program kerja.
 *
 * Akses  : hanya peran Pimpinan Bidang (dijaga middleware role di routes/web.php).
 * Bidang : SELALU diambil dari akun yang masuk. id_bidang dan dibuat_oleh dari
 *          request tidak pernah dibaca, jadi tidak bisa dipalsukan lewat API client.
 */
class ProgramKerjaController extends Controller
{
    public function store(Request $request)
    {
        // Hanya field di bawah ini yang diambil dari request.
        $data = $request->validate([
            'nama_program'    => ['required', 'string', 'max:255'],
            'tujuan'          => ['required', 'string', 'max:5000'],
            'sasaran_peserta' => ['nullable', 'string', 'max:255'],
            'metode'          => ['nullable', 'string', 'max:255'],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'tempat'          => ['nullable', 'string', 'max:255'],
            'anggaran'        => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'keterangan'      => ['nullable', 'string', 'max:2000'],
        ], [
            'nama_program.required'          => 'Nama program wajib diisi',
            'nama_program.max'               => 'Nama program maksimal 255 karakter',
            'tujuan.required'                => 'Tujuan wajib diisi',
            'tujuan.max'                     => 'Tujuan maksimal 5000 karakter',
            'sasaran_peserta.max'            => 'Sasaran peserta maksimal 255 karakter',
            'metode.max'                     => 'Metode maksimal 255 karakter',
            'tanggal_mulai.required'         => 'Tanggal mulai wajib diisi',
            'tanggal_mulai.date_format'      => 'Format tanggal mulai tidak valid',
            'tanggal_selesai.required'       => 'Tanggal selesai wajib diisi',
            'tanggal_selesai.date_format'    => 'Format tanggal selesai tidak valid',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
            'tempat.max'                     => 'Tempat maksimal 255 karakter',
            'anggaran.numeric'               => 'Anggaran harus berupa angka',
            'anggaran.min'                   => 'Anggaran tidak boleh negatif',
            'anggaran.max'                   => 'Anggaran terlalu besar',
            'keterangan.max'                 => 'Keterangan maksimal 2000 karakter',
        ]);

        // Bidang diambil dari akun yang sedang masuk, bukan dari request.
        $idBidang = DB::table('pengguna')
            ->where('id_pengguna', Auth::id())
            ->whereNull('dihapus_pada')
            ->value('id_bidang');

        if (! $idBidang) {
            return $this->gagal($request, 'Akun Anda belum terhubung dengan bidang.', 403);
        }

        try {
            $idBaru = DB::table('program_kerja')->insertGetId([
                'id_bidang'       => $idBidang,
                'nama_program'    => $data['nama_program'],
                'tujuan'          => $data['tujuan'],
                'sasaran_peserta' => $data['sasaran_peserta'] ?? null,
                'metode'          => $data['metode'] ?? null,
                'tanggal_mulai'   => $data['tanggal_mulai'],
                'tanggal_selesai' => $data['tanggal_selesai'],
                'tempat'          => $data['tempat'] ?? null,
                'anggaran'        => $data['anggaran'] ?? null,
                'keterangan'      => $data['keterangan'] ?? null,
                'dibuat_oleh'     => Auth::id(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ], 'id_program_kerja');
        } catch (QueryException $e) {
            // Detail teknis hanya ke log, pengguna cukup melihat pesan umum.
            Log::error('Gagal menyimpan program kerja', [
                'pembuat_id'   => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Program kerja belum bisa disimpan. Silakan coba lagi.', 500);
        }

        Log::info('Pimpinan Bidang menambah program kerja', [
            'pembuat_id'       => Auth::id(),
            'pembuat_username' => Auth::user()->nama_pengguna,
            'id_program_kerja' => $idBaru,
            'id_bidang'        => $idBidang,
            'nama_program'     => $data['nama_program'],
            'dicatat_pada'     => now()->toDateTimeString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'pesan'            => 'Program kerja berhasil ditambahkan.',
                'id_program_kerja' => $idBaru,
            ], 201);
        }

        // TODO: arahkan ke route('program-kerja.index') setelah daftar program kerja (T-98) ada.
        return redirect()->back()->with('sukses', 'Program kerja berhasil ditambahkan.');
    }

    private function gagal(Request $request, string $pesan, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], $status);
        }

        return redirect()->back()->withInput()->with('galat', $pesan);
    }
}