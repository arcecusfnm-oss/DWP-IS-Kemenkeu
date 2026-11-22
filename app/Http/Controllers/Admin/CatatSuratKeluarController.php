<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class CatatSuratKeluarController extends Controller
{
    public function create()
    {
        $bidang = DB::table('bidang')
            ->select('id_bidang', 'nama_bidang')
            ->orderBy('nama_bidang')
            ->get();

        return view('surat.catat-keluar', compact('bidang'));
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
            'id_bidang_terkait' => [
                'required',
                'integer',
                'exists:bidang,id_bidang',
            ],
            'status_terkirim' => [
                'required',
                Rule::in(['sudah', 'belum', 'batal']),
            ],
            'keterangan' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ], [
            'nomor_surat.required' => 'Nomor surat wajib diisi',
            'nomor_surat.unique' => 'Nomor surat sudah tercatat',
            'tanggal_surat.required' => 'Tanggal surat wajib diisi',
            'tanggal_surat.date_format' => 'Format tanggal surat tidak valid',
            'tujuan.required' => 'Tujuan wajib diisi',
            'perihal.required' => 'Perihal wajib diisi',
            'id_bidang_terkait.required' => 'Bidang terkait wajib dipilih',
            'id_bidang_terkait.integer' => 'Bidang terkait tidak valid',
            'id_bidang_terkait.exists' => 'Bidang terkait tidak ditemukan',
            'status_terkirim.required' => 'Status terkirim wajib dipilih',
            'status_terkirim.in' => 'Status terkirim tidak valid',
            'keterangan.max' => 'Keterangan maksimal 2000 karakter',
        ]);

        try {
            $id = DB::table('surat_keluar')->insertGetId([
                'nomor_surat' => $data['nomor_surat'],
                'tanggal' => $data['tanggal_surat'],
                'tujuan' => $data['tujuan'],
                'perihal' => $data['perihal'],
                'id_bidang_terkait' => $data['id_bidang_terkait'],
                'status_terkirim' => $data['status_terkirim'],
                'keterangan' => $data['keterangan'] ?? null,
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
                'kelas_galat' => get_class($e),
                'kode_galat'  => $e->getCode(),
            ]);

            return back()->withInput()->with(
                'galat',
                'Surat keluar gagal dicatat. Silakan coba lagi.'
            );

        } catch (Throwable $e) {
            Log::error('Kesalahan pencatatan surat keluar', [
                'pengguna_id' => Auth::id(),
                'kelas_galat' => get_class($e),
                'kode_galat'  => $e->getCode(),
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