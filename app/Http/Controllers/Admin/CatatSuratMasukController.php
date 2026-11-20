<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Throwable;

class CatatSuratMasukController extends Controller
{
    private const BULAN_ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
        5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
        9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function create()
    {
        return view('surat.catat-masuk');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nomor_surat' => [
                'required', 'string', 'max:100',
                Rule::unique('surat_masuk', 'nomor_surat'),
            ],
            'asal_surat' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date_format:Y-m-d'],
            'tanggal_diterima' => [
                'bail', 'required', 'date_format:Y-m-d',
                'after_or_equal:tanggal_surat',
            ],
            'lampiran' => [
                'nullable', 'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
                'max:5120',
            ],
            'perihal' => ['required', 'string', 'max:255'],
            'disposisi' => ['nullable', 'string', 'max:2000'],
            'tanggal_disposisi' => ['nullable', 'date_format:Y-m-d'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ], [
            'nomor_surat.required' => 'Nomor surat wajib diisi',
            'nomor_surat.unique' => 'Nomor surat sudah tercatat',
            'asal_surat.required' => 'Asal surat wajib diisi',
            'tanggal_surat.required' => 'Tanggal surat wajib diisi',
            'tanggal_diterima.required' => 'Tanggal diterima wajib diisi',
            'tanggal_diterima.after_or_equal' => 'Tanggal diterima tidak boleh sebelum tanggal surat',
            'perihal.required' => 'Perihal wajib diisi',
            'lampiran.mimes' => 'Format lampiran tidak didukung',
            'lampiran.max' => 'Ukuran lampiran maksimal 5 MB',
        ]);

        $path = null;

        if ($request->hasFile('lampiran')) {
            $path = $request->file('lampiran')->store('lampiran', 'public');
        }

        $tanggal = \Carbon\Carbon::parse($data['tanggal_diterima']);
        $bulan = (int) $tanggal->format('m');
        $tahun = (int) $tanggal->format('Y');
        $romawi = self::BULAN_ROMAWI[$bulan];

        // Lock berbeda untuk setiap bulan/tahun.
        $lockName = "agenda_surat_masuk_{$tahun}_{$bulan}";
        $lockAcquired = false;

        $data['lampiran'] = $path;
        $data['dibuat_oleh'] = Auth::id();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        try {
            $lock = DB::selectOne(
                'SELECT GET_LOCK(?, 5) AS acquired',
                [$lockName]
            );

            if (! $lock || (int) $lock->acquired !== 1) {
                if ($path) {
                    Storage::disk('public')->delete($path);
                }

                return back()->withInput()->with(
                    'galat',
                    'Pencatatan sedang sibuk. Silakan coba kembali.'
                );
            }

            $lockAcquired = true;

            $id = DB::transaction(function () use (&$data, $romawi, $tahun) {
                $agendaLama = DB::table('surat_masuk')
                    ->where('nomor_agenda', 'like', '%/' . $romawi . '/' . $tahun)
                    ->pluck('nomor_agenda');

                $nomorTerbesar = 0;

                foreach ($agendaLama as $agenda) {
                    $bagian = explode('/', $agenda);

                    if (
                        count($bagian) === 3
                        && $bagian[1] === $romawi
                        && $bagian[2] === (string) $tahun
                        && ctype_digit($bagian[0])
                    ) {
                        $nomorTerbesar = max(
                            $nomorTerbesar,
                            (int) $bagian[0]
                        );
                    }
                }

                $data['nomor_agenda'] = sprintf(
                    '%03d/%s/%d',
                    $nomorTerbesar + 1,
                    $romawi,
                    $tahun
                );

                return DB::table('surat_masuk')->insertGetId(
                    $data,
                    'id_surat_masuk'
                );
            });

        } catch (QueryException $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            $nomorSudahAda = DB::table('surat_masuk')
                ->where('nomor_surat', $data['nomor_surat'])
                ->exists();

            if ($nomorSudahAda) {
                return back()->withInput()->withErrors([
                    'nomor_surat' => 'Nomor surat sudah tercatat',
                ]);
            }

            Log::error('Gagal mencatat surat masuk', [
                'pengguna_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with(
                'galat',
                'Surat masuk gagal dicatat. Silakan coba lagi.'
            );

        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Kesalahan pencatatan surat masuk', [
                'pengguna_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with(
                'galat',
                'Surat masuk gagal dicatat. Silakan coba lagi.'
            );

        } finally {
            if ($lockAcquired) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
                } catch (Throwable $e) {
                    Log::warning('Gagal melepas lock nomor agenda', [
                        'lock' => $lockName,
                    ]);
                }
            }
        }

        Log::info('Surat masuk dicatat', [
            'pengguna_id' => Auth::id(),
            'pengguna_username' => Auth::user()->nama_pengguna,
            'peran' => Auth::user()->peran?->nama_peran,
            'id_surat_masuk' => $id,
            'nomor_agenda' => $data['nomor_agenda'],
            'nomor_surat' => $data['nomor_surat'],
            'dicatat_pada' => now()->toDateTimeString(),
        ]);

        return redirect()
            ->route('surat.index', ['jenis' => 'masuk'])
            ->with('sukses', 'Surat masuk berhasil dicatat.');
    }
}
