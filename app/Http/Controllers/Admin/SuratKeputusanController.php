<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * B-128 T-075: endpoint catat dan daftar surat keputusan (SK).
 * - Empat kolom sheet Keputusan: Nomor, Tanggal, Perihal, Penetapan (Sudah/Belum/Batal)
 * - Hanya Admin (dijaga middleware pada route)
 * - Pencatatan dicatat di log tanpa data sensitif
 */
class SuratKeputusanController extends Controller
{
    public function index()
    {
        $daftar = DB::table('surat_keputusan')
            ->orderByDesc('tanggal')
            ->orderByDesc('id_surat_keputusan')
            ->get([
                'id_surat_keputusan',
                'nomor_sk',
                'tanggal',
                'perihal',
                'status_penetapan',
            ]);

        return response()->json(['data' => $daftar]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nomor_sk' => [
                'required',
                'string',
                'max:100',
                Rule::unique('surat_keputusan', 'nomor_sk'),
            ],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'perihal' => ['required', 'string', 'max:255'],
            'status_penetapan' => ['required', Rule::in(['sudah', 'belum', 'batal'])],
        ], [
            'nomor_sk.required' => 'Nomor SK wajib diisi',
            'nomor_sk.string' => 'Nomor SK tidak valid',
            'nomor_sk.max' => 'Nomor SK maksimal 100 karakter',
            'nomor_sk.unique' => 'Nomor SK sudah tercatat',
            'tanggal.required' => 'Tanggal wajib diisi',
            'tanggal.date_format' => 'Format tanggal tidak valid',
            'perihal.required' => 'Perihal wajib diisi',
            'perihal.string' => 'Perihal tidak valid',
            'perihal.max' => 'Perihal maksimal 255 karakter',
            'status_penetapan.required' => 'Penetapan wajib dipilih',
            'status_penetapan.in' => 'Penetapan tidak valid',
        ]);

        try {
            $id = DB::table('surat_keputusan')->insertGetId([
                'nomor_sk' => $data['nomor_sk'],
                'tanggal' => $data['tanggal'],
                'perihal' => $data['perihal'],
                'status_penetapan' => $data['status_penetapan'],
                'dibuat_oleh' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_surat_keputusan');
        } catch (QueryException $e) {
            // Dua request bersamaan dengan nomor sama: ditahan unique index di DB
            $nomorSudahAda = DB::table('surat_keputusan')
                ->where('nomor_sk', $data['nomor_sk'])
                ->exists();

            if ($nomorSudahAda) {
                throw ValidationException::withMessages([
                    'nomor_sk' => 'Nomor SK sudah tercatat',
                ]);
            }

            return $this->gagal($request, $e);
        } catch (Throwable $e) {
            return $this->gagal($request, $e);
        }

        Log::info('Surat keputusan dicatat', [
            'pengguna_id' => Auth::id(),
            'pengguna_username' => Auth::user()->nama_pengguna,
            'peran' => Auth::user()->peran?->nama_peran,
            'id_surat_keputusan' => $id,
            'nomor_sk' => $data['nomor_sk'],
            'tanggal' => $data['tanggal'],
            'dicatat_pada' => now()->toDateTimeString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'pesan' => 'Surat keputusan berhasil dicatat.',
                'id_surat_keputusan' => $id,
            ], 201);
        }

        return back()->with('sukses', 'Surat keputusan berhasil dicatat.');
    }

    private function gagal(Request $request, Throwable $e)
    {
        // Tanpa pesan exception mentah ke pengguna
        Log::error('Gagal mencatat surat keputusan', [
            'pengguna_id' => Auth::id(),
            'kelas_galat' => get_class($e),
            'kode_galat' => $e->getCode(),
            'dicatat_pada' => now()->toDateTimeString(),
        ]);

        $pesan = 'Surat keputusan gagal dicatat. Silakan coba lagi.';

        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], 500);
        }

        return back()->withInput()->with('galat', $pesan);
    }
}