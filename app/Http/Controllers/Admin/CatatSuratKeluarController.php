<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Throwable;

class CatatSuratKeluarController extends Controller
{
    public function create()
    {
        return view('surat.catat-keluar');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nomor_surat' => [
                'required',
                'string',
                'max:100',
                Rule::unique('surat_keluar', 'nomor_surat'),
            ],
            'tanggal_surat' => [
                'required',
                'date_format:Y-m-d',
            ],
            'tujuan' => [
                'required',
                'string',
                'max:255',
            ],
            'perihal' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'nomor_surat.required' => 'Nomor surat wajib diisi',
            'nomor_surat.unique' => 'Nomor surat sudah tercatat',
            'tanggal_surat.required' => 'Tanggal surat wajib diisi',
            'tanggal_surat.date_format' => 'Format tanggal surat tidak valid',
            'tujuan.required' => 'Tujuan wajib diisi',
            'perihal.required' => 'Perihal wajib diisi',
        ]);

        try {
            $id = DB::table('surat_keluar')->insertGetId([
                'nomor_surat' => $data['nomor_surat'],
                'tanggal' => $data['tanggal_surat'],
                'tujuan' => $data['tujuan'],
                'perihal' => $data['perihal'],
                'status_terkirim' => 'belum',
                'dibuat_oleh' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_surat_keluar');

        } catch (QueryException $e) {
            // Menangani kemungkinan nomor surat masuk bersamaan (race condition).
            $nomorSudahAda = DB::table('surat_keluar')
                ->where('nomor_surat', $data['nomor_surat'])
                ->exists();

            if ($nomorSudahAda) {
                return back()->withInput()->withErrors([
                    'nomor_surat' => 'Nomor surat sudah tercatat',
                ]);
            }

            Log::error('Gagal mencatat surat keluar', [
                'pengguna_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with(
                'galat',
                'Surat keluar gagal dicatat. Silakan coba lagi.'
            );

        } catch (Throwable $e) {
            Log::error('Kesalahan pencatatan surat keluar', [
                'pengguna_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with(
                'galat',
                'Surat keluar gagal dicatat. Silakan coba lagi.'
            );
        }

        Log::info('Surat keluar dicatat', [
            'pengguna_id' => Auth::id(),
            'pengguna_username' => Auth::user()->nama_pengguna,
            'peran' => Auth::user()->peran?->nama_peran,
            'id_surat_keluar' => $id,
            'nomor_surat' => $data['nomor_surat'],
            'tanggal_surat' => $data['tanggal_surat'],
            'dicatat_pada' => now()->toDateTimeString(),
        ]);

        return redirect()
            ->route('surat.index', ['jenis' => 'keluar'])
            ->with('sukses', 'Surat keluar berhasil dicatat.');
    }
}
