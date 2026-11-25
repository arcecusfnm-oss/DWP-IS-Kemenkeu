<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * B-052: template laporan kegiatan (T-131), mengikuti DESAIN-SPRINT3 bagian 3.5 dan 5.
 *
 * Template : satu dan global untuk seluruh bidang (tabel singleton template_laporan).
 * Lihat    : GET /template-laporan (semua peran yang login) dan GET /admin/template-laporan (Admin).
 * Ubah     : PUT /admin/template-laporan, hanya Admin (middleware role:Admin di routes/web.php).
 * Ubah     : hanya label keempat kolom. Semua label wajib diisi, sehingga kolom
 *            Judul Kegiatan dan Tanggal Kegiatan tidak bisa dikosongkan.
 */
class TemplatLaporanController extends Controller
{
    private const LABEL = [
        'label_judul_kegiatan',
        'label_tanggal_kegiatan',
        'label_penjelasan_singkat',
        'label_dokumentasi',
    ];

    public function show()
    {
        try {
            $templat = $this->templat();
        } catch (QueryException $e) {
            Log::error('Gagal memuat template laporan kegiatan', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return response()->json(['pesan' => 'Template belum bisa dimuat. Silakan coba lagi.'], 500);
        }

        if (! $templat) {
            return response()->json(['pesan' => 'Template laporan kegiatan belum tersedia.'], 404);
        }

        return response()->json($this->susunRespons($templat));
    }

    public function update(Request $request)
    {
        $data = Validator::make($request->all(), [
            'label_judul_kegiatan'     => ['required', 'string', 'max:255'],
            'label_tanggal_kegiatan'   => ['required', 'string', 'max:255'],
            'label_penjelasan_singkat' => ['required', 'string', 'max:255'],
            'label_dokumentasi'        => ['required', 'string', 'max:255'],
        ], [
            'label_judul_kegiatan.required'     => 'Kolom Judul Kegiatan wajib ada, labelnya tidak boleh dikosongkan',
            'label_judul_kegiatan.string'       => 'Label Judul Kegiatan tidak valid',
            'label_judul_kegiatan.max'          => 'Label Judul Kegiatan maksimal 255 karakter',
            'label_tanggal_kegiatan.required'   => 'Kolom Tanggal Kegiatan wajib ada, labelnya tidak boleh dikosongkan',
            'label_tanggal_kegiatan.string'     => 'Label Tanggal Kegiatan tidak valid',
            'label_tanggal_kegiatan.max'        => 'Label Tanggal Kegiatan maksimal 255 karakter',
            'label_penjelasan_singkat.required' => 'Label Penjelasan Singkat wajib diisi',
            'label_penjelasan_singkat.string'   => 'Label Penjelasan Singkat tidak valid',
            'label_penjelasan_singkat.max'      => 'Label Penjelasan Singkat maksimal 255 karakter',
            'label_dokumentasi.required'        => 'Label Dokumentasi wajib diisi',
            'label_dokumentasi.string'          => 'Label Dokumentasi tidak valid',
            'label_dokumentasi.max'             => 'Label Dokumentasi maksimal 255 karakter',
        ])->validate();

        try {
            $templat = $this->templat();

            if (! $templat) {
                return $this->gagal($request, 'Template laporan kegiatan belum tersedia.', 404);
            }

            $berubah = array_values(array_filter(
                self::LABEL,
                fn ($kolom) => (string) $templat->$kolom !== $data[$kolom]
            ));

            // Tanpa perubahan: tidak menulis dan tidak mencatat log audit.
            if ($berubah === []) {
                return $this->berhasil($request, $templat, 'Tidak ada perubahan pada template laporan kegiatan.');
            }

            DB::table('template_laporan')
                ->where('id_template_laporan', $templat->id_template_laporan)
                ->update(array_merge($data, [
                    'diubah_oleh' => Auth::id(),
                    'updated_at'  => now(),
                ]));

            $terbaru = $this->templat();
        } catch (QueryException $e) {
            Log::error('Gagal menyimpan template laporan kegiatan', [
                'pengubah_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Template belum bisa disimpan. Silakan coba lagi.', 500);
        }

        Log::info('Template laporan kegiatan diubah', [
            'pengubah_id'       => Auth::id(),
            'pengubah_username' => Auth::user()->nama_pengguna,
            'peran'             => Auth::user()->peran?->nama_peran,
            'id_template'       => $templat->id_template_laporan,
            'kolom_berubah'     => $berubah,
            'dicatat_pada'      => now()->toDateTimeString(),
        ]);

        return $this->berhasil($request, $terbaru, 'Template laporan kegiatan berhasil disimpan.');
    }

    private function templat(): ?object
    {
        return DB::table('template_laporan')
            ->orderBy('id_template_laporan')
            ->first();
    }

    private function susunRespons(object $templat): array
    {
        return [
            'id_template_laporan'      => $templat->id_template_laporan,
            'label_judul_kegiatan'     => $templat->label_judul_kegiatan,
            'label_tanggal_kegiatan'   => $templat->label_tanggal_kegiatan,
            'label_penjelasan_singkat' => $templat->label_penjelasan_singkat,
            'label_dokumentasi'        => $templat->label_dokumentasi,
            'diperbarui_pada'          => $templat->updated_at,
        ];
    }

    private function berhasil(Request $request, object $templat, string $pesan)
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['pesan' => $pesan], $this->susunRespons($templat)));
        }

        return redirect()->back()->with('sukses', $pesan);
    }

    private function gagal(Request $request, string $pesan, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], $status);
        }

        return redirect()->back()->withInput()->with('galat', $pesan);
    }
}