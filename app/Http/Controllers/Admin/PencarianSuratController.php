<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * T-74 (B-039): cari surat berdasarkan kata kunci perihal dan rentang tanggal surat.
 *
 * Mencakup surat masuk, surat keluar, dan surat keputusan.
 * Akses: Admin dan bidang Sekretariat (middleware AksesPersuratan di routes/web.php).
 * Endpoint kontrak T-21: GET /surat (surat.index) dengan ?q=&dari=&sampai=.
 */
class PencarianSuratController extends Controller
{
    public function cari(Request $request)
    {
        // q = kata kunci perihal, dari/sampai = rentang tanggal surat (inklusif),
        // jenis = masuk, keluar, atau keputusan. Kosong berarti semua jenis.
        $request->validate([
            'q'      => ['nullable', 'string', 'max:100'],
            'dari'   => ['nullable', 'date_format:Y-m-d'],
            'sampai' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($request->filled('dari'), ['after_or_equal:dari']),
            ],
            'jenis'  => ['nullable', 'in:masuk,keluar,keputusan'],
        ], [
            'q.max'                 => 'Kata kunci terlalu panjang',
            'dari.date_format'      => 'Tanggal awal tidak valid',
            'sampai.date_format'    => 'Tanggal akhir tidak valid',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal',
            'jenis.in'              => 'Jenis surat tidak valid',
        ]);

        $kata   = $request->filled('q') ? $this->escapeLike(trim((string) $request->query('q'))) : null;
        $dari   = $request->query('dari') ?: null;
        $sampai = $request->query('sampai') ?: null;
        $jenis  = $request->query('jenis') ?: null;

        $masuk = DB::table('surat_masuk')->select(
            DB::raw("'masuk' as jenis"),
            'id_surat_masuk as id',
            'nomor_surat',
            'tanggal_surat as tanggal',
            'asal_surat as pihak',
            'perihal'
        );
        $this->terapkanFilter($masuk, 'tanggal_surat', $kata, $dari, $sampai);

        $keluar = DB::table('surat_keluar')->select(
            DB::raw("'keluar' as jenis"),
            'id_surat_keluar as id',
            'nomor_surat',
            'tanggal',
            'tujuan as pihak',
            'perihal'
        );
        $this->terapkanFilter($keluar, 'tanggal', $kata, $dari, $sampai);

        $keputusan = DB::table('surat_keputusan')->select(
            DB::raw("'keputusan' as jenis"),
            'id_surat_keputusan as id',
            'nomor_sk as nomor_surat',
            'tanggal',
            DB::raw('NULL as pihak'),
            'perihal'
        );
        $this->terapkanFilter($keputusan, 'tanggal', $kata, $dari, $sampai);

        $sumber = match ($jenis) {
            'masuk'     => $masuk,
            'keluar'    => $keluar,
            'keputusan' => $keputusan,
            default     => $masuk->unionAll($keluar)->unionAll($keputusan),
        };

        $surat = DB::query()
            ->fromSub($sumber, 'surat')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->orderBy('jenis')
            ->paginate(10)
            ->withQueryString();

        $pesan = $surat->total() === 0 ? 'Data tidak ditemukan' : null;

        // T-76: Statistik dashboard persuratan (tidak mengikuti filter tabel).
        $jumlahJenis = [
            ''          => DB::table('surat_masuk')->count()
                + DB::table('surat_keluar')->count()
                + DB::table('surat_keputusan')->count(),
            'masuk'     => DB::table('surat_masuk')->count(),
            'keluar'    => DB::table('surat_keluar')->count(),
            'keputusan' => DB::table('surat_keputusan')->count(),
        ];

        $awalBulan  = now()->startOfMonth()->toDateString();
        $akhirBulan = now()->endOfMonth()->toDateString();

        $totalMasukBulanIni = DB::table('surat_masuk')
            ->whereBetween('tanggal_diterima', [$awalBulan, $akhirBulan])
            ->count();

        $sudahDisposisi = DB::table('surat_masuk')
            ->whereNotNull('disposisi')
            ->whereRaw("TRIM(disposisi) <> ''")
            ->count();

        $belumDisposisi = DB::table('surat_masuk')->count() - $sudahDisposisi;

        if ($request->expectsJson()) {
            return response()->json([
                'surat' => $surat,
                'pesan' => $pesan,
            ]);
        }

        return view('surat.index', [
            'surat' => $surat,
            'pesan' => $pesan,
            'jumlahJenis' => $jumlahJenis,
            'totalMasukBulanIni' => $totalMasukBulanIni,
            'sudahDisposisi' => $sudahDisposisi,
            'belumDisposisi' => $belumDisposisi,
        ]);
    }

    private function terapkanFilter($query, string $kolomTanggal, ?string $kata, ?string $dari, ?string $sampai): void
    {
        if ($kata !== null && $kata !== '') {
            $query->where('perihal', 'like', "%{$kata}%");
        }

        if ($dari !== null) {
            $query->where($kolomTanggal, '>=', $dari);
        }

        if ($sampai !== null) {
            $query->where($kolomTanggal, '<=', $sampai);
        }
    }

    // Escape wildcard LIKE supaya "%" dan "_" dicari sebagai teks biasa.
    // Tanda kutip aman karena nilai selalu dikirim sebagai binding.
    private function escapeLike(string $teks): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $teks);
    }
}