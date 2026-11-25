<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * B-052: kelola templat laporan kegiatan (T-131).
 *
 * Templat  : tunggal dan aktif, dipakai semua bidang.
 * Lihat    : semua peran yang masuk (bidang membutuhkannya untuk mengisi laporan, B-053).
 * Ubah     : hanya Admin (dijaga middleware role:Admin di routes/web.php).
 * Ubah     : hanya label dan status wajib; kunci dan urutan kolom tetap.
 *            Judul, Tanggal, dan Penjelasan selalu wajib.
 */
class TemplatLaporanController extends Controller
{
    private const KUNCI = ['judul_kegiatan', 'tanggal_kegiatan', 'penjelasan_singkat', 'dokumentasi'];

    private const SELALU_WAJIB = ['judul_kegiatan', 'tanggal_kegiatan', 'penjelasan_singkat'];

    public function show()
    {
        try {
            $templat = $this->templatAktif();

            if (! $templat) {
                return response()->json(['pesan' => 'Templat laporan kegiatan belum tersedia.'], 404);
            }

            return response()->json($this->susunRespons($templat));
        } catch (QueryException $e) {
            Log::error('Gagal memuat templat laporan kegiatan', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return response()->json(['pesan' => 'Templat belum bisa dimuat. Silakan coba lagi.'], 500);
        }
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kolom'         => ['required', 'array', 'size:' . count(self::KUNCI)],
            'kolom.*.kunci' => ['required', 'string', 'distinct', Rule::in(self::KUNCI)],
            'kolom.*.label' => ['required', 'string', 'max:100'],
            'kolom.*.wajib' => ['required', 'boolean'],
        ], [
            'kolom.required'         => 'Kolom templat wajib diisi',
            'kolom.array'            => 'Kolom templat tidak valid',
            'kolom.size'             => 'Templat harus memuat tepat ' . count(self::KUNCI) . ' kolom',
            'kolom.*.kunci.required' => 'Kunci kolom wajib diisi',
            'kolom.*.kunci.distinct' => 'Kunci kolom tidak boleh dobel',
            'kolom.*.kunci.in'       => 'Kunci kolom tidak dikenal',
            'kolom.*.label.required' => 'Label kolom wajib diisi',
            'kolom.*.label.string'   => 'Label kolom tidak valid',
            'kolom.*.label.max'      => 'Label kolom maksimal 100 karakter',
            'kolom.*.wajib.required' => 'Status wajib kolom harus diisi',
            'kolom.*.wajib.boolean'  => 'Status wajib kolom tidak valid',
        ]);

        $validator->after(function ($v) use ($request) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            foreach ($request->input('kolom') as $i => $k) {
                if (in_array($k['kunci'], self::SELALU_WAJIB, true) && ! filter_var($k['wajib'], FILTER_VALIDATE_BOOLEAN)) {
                    $v->errors()->add("kolom.$i.wajib", 'Kolom ini harus tetap wajib diisi');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'pesan'  => 'Data templat tidak valid.',
                'galat'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $templat = $this->templatAktif();

            if (! $templat) {
                return response()->json(['pesan' => 'Templat laporan kegiatan belum tersedia.'], 404);
            }

            DB::transaction(function () use ($data, $templat) {
                foreach ($data['kolom'] as $k) {
                    DB::table('kolom_templat_laporan')
                        ->where('id_templat_laporan', $templat->id_templat_laporan)
                        ->where('kunci', $k['kunci'])
                        ->update([
                            'label'      => $k['label'],
                            'wajib'      => filter_var($k['wajib'], FILTER_VALIDATE_BOOLEAN),
                            'updated_at' => now(),
                        ]);
                }

                DB::table('templat_laporan')
                    ->where('id_templat_laporan', $templat->id_templat_laporan)
                    ->update([
                        'diubah_oleh' => Auth::id(),
                        'updated_at'  => now(),
                    ]);
            });
        } catch (QueryException $e) {
            Log::error('Gagal menyimpan templat laporan kegiatan', [
                'pengubah_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return response()->json(['pesan' => 'Templat belum bisa disimpan. Silakan coba lagi.'], 500);
        }

        Log::info('Templat laporan kegiatan diubah', [
            'pengubah_id'       => Auth::id(),
            'pengubah_username' => Auth::user()->nama_pengguna,
            'peran'             => Auth::user()->peran?->nama_peran,
            'id_templat'        => $templat->id_templat_laporan,
            'dicatat_pada'      => now()->toDateTimeString(),
        ]);

        return response()->json(array_merge(
            ['pesan' => 'Templat laporan kegiatan berhasil disimpan.'],
            $this->susunRespons($templat)
        ));
    }

    private function templatAktif(): ?object
    {
        return DB::table('templat_laporan')
            ->where('aktif', true)
            ->orderBy('id_templat_laporan')
            ->first();
    }

    private function susunRespons(object $templat): array
    {
        $kolom = DB::table('kolom_templat_laporan')
            ->where('id_templat_laporan', $templat->id_templat_laporan)
            ->orderBy('urutan')
            ->get(['kunci', 'label', 'wajib', 'urutan'])
            ->map(fn ($k) => [
                'kunci'  => $k->kunci,
                'label'  => $k->label,
                'wajib'  => (bool) $k->wajib,
                'urutan' => (int) $k->urutan,
            ])
            ->all();

        return [
            'id_templat'   => $templat->id_templat_laporan,
            'nama_templat' => $templat->nama_templat,
            'kolom'        => $kolom,
        ];
    }
}