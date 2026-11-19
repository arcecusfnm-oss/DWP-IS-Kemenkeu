<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * T-69 (B-038): endpoint ubah data surat masuk dan surat keluar.
 *
 * Akses : Admin dan bidang Sekretariat (middleware AksesPersuratan di routes/web.php).
 * Jenis : diambil dari URL ({jenis}), tidak pernah dari isi request, jadi jenis surat
 *         tidak bisa diubah setelah tersimpan (AC 4). Request yang membawa 'jenis' ditolak.
 * Tidak dapat diubah di sini: nomor agenda, lampiran, disposisi (asumsi tim).
 */
class UbahSuratController extends Controller
{
    private const TABEL = ['masuk' => 'surat_masuk', 'keluar' => 'surat_keluar'];

    private const KUNCI = ['masuk' => 'id_surat_masuk', 'keluar' => 'id_surat_keluar'];

    private const KOLOM = [
        'masuk'  => ['nomor_surat', 'asal_surat', 'tanggal_surat', 'tanggal_diterima', 'perihal', 'keterangan'],
        'keluar' => ['nomor_surat', 'tanggal', 'tujuan', 'perihal', 'status_terkirim', 'id_bidang_terkait', 'keterangan'],
    ];

    private const KOLOM_TANGGAL = ['tanggal_surat', 'tanggal_diterima', 'tanggal'];

    public function update(Request $request, string $jenis, int $id)
    {
        $lama = $this->cari($jenis, $id);

        if (! $lama) {
            abort(404, 'Surat tidak ditemukan.');
        }

        $data = $request->validate($this->aturan($jenis, $id), $this->pesan());

        // Catat nama kolom yang berubah saja (tanpa nilainya).
        $berubah = [];
        foreach (self::KOLOM[$jenis] as $kolom) {
            if ($this->normal($kolom, $lama->{$kolom}) !== $this->normal($kolom, $data[$kolom] ?? null)) {
                $berubah[] = $kolom;
            }
        }

        if ($berubah === []) {
            return $this->selesai($request, $jenis, $id, 'Tidak ada perubahan pada data surat.');
        }

        $isi = [];
        foreach ($berubah as $kolom) {
            $isi[$kolom] = $data[$kolom] ?? null;
        }
        $isi['updated_at'] = now();

        try {
            DB::table(self::TABEL[$jenis])->where(self::KUNCI[$jenis], $id)->update($isi);
        } catch (QueryException $e) {
            // Dua orang menyimpan nomor yang sama bersamaan: tertangkap oleh unique di DB.
            if ((string) $e->getCode() === '23000') {
                return $this->gagalValidasi($request, 'nomor_surat', 'Nomor surat sudah tercatat');
            }

            Log::error('Gagal mengubah surat', [
                'pengguna_id'  => Auth::id(),
                'jenis'        => $jenis,
                'id_surat'     => $id,
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            $pesan = 'Data surat belum bisa diubah. Silakan coba lagi.';

            return $request->expectsJson()
                ? response()->json(['pesan' => $pesan], 500)
                : redirect()->back()->withInput()->with('galat', $pesan);
        }

        Log::info('Surat diubah', [
            'pengubah_id'       => Auth::id(),
            'pengubah_username' => Auth::user()->nama_pengguna,
            'peran'             => Auth::user()->peran?->nama_peran,
            'jenis'             => $jenis,
            'id_surat'          => $id,
            'nomor_surat'       => $data['nomor_surat'],
            'kolom_berubah'     => $berubah,
            'dicatat_pada'      => now()->toDateTimeString(),
        ]);

        return $this->selesai($request, $jenis, $id, 'Data surat berhasil diubah.');
    }

    private function cari(string $jenis, int $id): ?object
    {
        if (! isset(self::TABEL[$jenis])) {
            return null;
        }

        return DB::table(self::TABEL[$jenis])->where(self::KUNCI[$jenis], $id)->first();
    }

    private function normal(string $kolom, mixed $nilai): string
    {
        $teks = (string) ($nilai ?? '');

        return in_array($kolom, self::KOLOM_TANGGAL, true) ? substr($teks, 0, 10) : $teks;
    }

    private function selesai(Request $request, string $jenis, int $id, string $pesan)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan, 'jenis' => $jenis, 'id_surat' => $id]);
        }

        return redirect()->route('surat.index', ['jenis' => $jenis])->with('sukses', $pesan);
    }

    private function gagalValidasi(Request $request, string $kolom, string $pesan)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $pesan, 'errors' => [$kolom => [$pesan]]], 422);
        }

        return redirect()->back()->withInput()->withErrors([$kolom => $pesan]);
    }

    private function aturan(string $jenis, int $id): array
    {
        // Jenis hanya dari URL; mengirim 'jenis' lewat request ditolak.
        $umum = ['jenis' => ['prohibited']];

        if ($jenis === 'masuk') {
            return $umum + [
                'nomor_surat'      => ['required', 'string', 'max:100',
                    Rule::unique('surat_masuk', 'nomor_surat')->ignore($id, 'id_surat_masuk')],
                'asal_surat'       => ['required', 'string', 'max:255'],
                'tanggal_surat'    => ['required', 'date_format:Y-m-d'],
                'tanggal_diterima' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:tanggal_surat'],
                'perihal'          => ['required', 'string', 'max:255'],
                'keterangan'       => ['nullable', 'string', 'max:2000'],
            ];
        }

        return $umum + [
            'nomor_surat'       => ['required', 'string', 'max:100',
                Rule::unique('surat_keluar', 'nomor_surat')->ignore($id, 'id_surat_keluar')],
            'tanggal'           => ['required', 'date_format:Y-m-d'],
            'tujuan'            => ['required', 'string', 'max:255'],
            'perihal'           => ['required', 'string', 'max:255'],
            'status_terkirim'   => ['required', 'in:sudah,belum,batal'],
            'id_bidang_terkait' => ['nullable', 'integer', 'exists:bidang,id_bidang'],
            'keterangan'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function pesan(): array
    {
        return [
            'jenis.prohibited'                => 'Jenis surat tidak dapat diubah',
            'nomor_surat.required'            => 'Nomor surat wajib diisi',
            'nomor_surat.max'                 => 'Nomor surat maksimal 100 karakter',
            'nomor_surat.unique'              => 'Nomor surat sudah tercatat',
            'asal_surat.required'             => 'Asal surat wajib diisi',
            'asal_surat.max'                  => 'Asal surat maksimal 255 karakter',
            'tanggal_surat.required'          => 'Tanggal surat wajib diisi',
            'tanggal_surat.date_format'       => 'Format tanggal surat tidak valid',
            'tanggal_diterima.required'       => 'Tanggal diterima wajib diisi',
            'tanggal_diterima.date_format'    => 'Format tanggal diterima tidak valid',
            'tanggal_diterima.after_or_equal' => 'Tanggal diterima tidak boleh sebelum tanggal surat',
            'tanggal.required'                => 'Tanggal surat wajib diisi',
            'tanggal.date_format'             => 'Format tanggal surat tidak valid',
            'tujuan.required'                 => 'Tujuan wajib diisi',
            'tujuan.max'                      => 'Tujuan maksimal 255 karakter',
            'perihal.required'                => 'Perihal wajib diisi',
            'perihal.max'                     => 'Perihal maksimal 255 karakter',
            'status_terkirim.required'        => 'Status terkirim wajib dipilih',
            'status_terkirim.in'              => 'Status terkirim tidak valid',
            'id_bidang_terkait.integer'       => 'Bidang terkait tidak valid',
            'id_bidang_terkait.exists'        => 'Bidang terkait tidak tersedia',
            'keterangan.max'                  => 'Keterangan maksimal 2000 karakter',
        ];
    }
}