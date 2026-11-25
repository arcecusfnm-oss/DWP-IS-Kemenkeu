<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * B-053: isi laporan kegiatan (T-154).
 *
 * Akses    : Pimpinan Bidang dan Anggota Bidang (aturan sementara sama dengan program kerja),
 *            dijaga middleware role di routes/web.php.
 * Kegiatan : hanya kegiatan pada program kerja bidang akun yang masuk.
 *            id_bidang dan dibuat_oleh dari request tidak pernah dibaca.
 * Status   : laporan baru selalu Draf.
 * Tanggal  : tidak boleh lebih dari hari ini (asumsi tim).
 * Log      : waktu, pelaku, dan ID laporan; isi laporan tidak dicatat.
 */
class LaporanKegiatanController extends Controller
{
    private const STATUS = ['Draf', 'Terkirim', 'Dikembalikan', 'Diperiksa'];

    private const MAKS_PENJELASAN = 2000;

    /**
     * Bentuk formulir dari templat tunggal + pilihan kegiatan bidang sendiri.
     */
    public function formulir()
    {
        $idBidang = $this->idBidangAkun();

        if (! $idBidang) {
            return response()->json(['pesan' => 'Akun Anda belum terhubung dengan bidang.'], 403);
        }

        try {
            $templat = DB::table('template_laporan')
                ->orderBy('id_template_laporan')
                ->first();

            if (! $templat) {
                return response()->json(['pesan' => 'Template laporan kegiatan belum tersedia.'], 404);
            }

            // Judul, Tanggal, dan Penjelasan selalu wajib; slot dokumentasi disiapkan
            // dan belum bisa diisi (B-058).
            $kolom = [
                ['kunci' => 'judul_kegiatan', 'label' => $templat->label_judul_kegiatan, 'wajib' => true, 'urutan' => 1, 'tersedia' => true],
                ['kunci' => 'tanggal_kegiatan', 'label' => $templat->label_tanggal_kegiatan, 'wajib' => true, 'urutan' => 2, 'tersedia' => true],
                ['kunci' => 'penjelasan_singkat', 'label' => $templat->label_penjelasan_singkat, 'wajib' => true, 'urutan' => 3, 'tersedia' => true],
                ['kunci' => 'dokumentasi', 'label' => $templat->label_dokumentasi, 'wajib' => false, 'urutan' => 4, 'tersedia' => false],
            ];

            $kegiatan = DB::table('kegiatan')
                ->join('program_kerja', 'program_kerja.id_program_kerja', '=', 'kegiatan.id_program_kerja')
                ->where('program_kerja.id_bidang', $idBidang)
                ->whereNull('kegiatan.dihapus_pada')
                ->orderByDesc('kegiatan.tanggal_mulai')
                ->orderByDesc('kegiatan.id_kegiatan')
                ->get([
                    'kegiatan.id_kegiatan',
                    'kegiatan.nama_kegiatan',
                    'kegiatan.tanggal_mulai',
                    'kegiatan.tanggal_selesai',
                    'program_kerja.nama_program',
                ]);
        } catch (QueryException $e) {
            Log::error('Gagal memuat formulir laporan kegiatan', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return response()->json(['pesan' => 'Formulir belum bisa dimuat. Silakan coba lagi.'], 500);
        }

        return response()->json([
            'id_template_laporan' => $templat->id_template_laporan,
            'kolom'               => $kolom,
            'kegiatan'            => $kegiatan,
            'batas_penjelasan'    => self::MAKS_PENJELASAN,
            'tanggal_maks'        => now()->toDateString(),
        ]);
    }
    /**
     * Daftar laporan bidang saya. Filter opsional: ?status=Draf
     */
    public function index(Request $request)
    {
        $idBidang = $this->idBidangAkun();

        if (! $idBidang) {
            return response()->json(['pesan' => 'Akun Anda belum terhubung dengan bidang.'], 403);
        }

        $validator = Validator::make($request->query(), [
            'status' => ['nullable', 'string', Rule::in(self::STATUS)],
        ], [
            'status.in'     => 'Status tidak valid',
            'status.string' => 'Status tidak valid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'pesan' => 'Filter tidak valid.',
                'galat' => $validator->errors(),
            ], 422);
        }

        $status = $validator->validated()['status'] ?? null;

        try {
            $laporan = DB::table('laporan_kegiatan')
                ->join('kegiatan', 'kegiatan.id_kegiatan', '=', 'laporan_kegiatan.id_kegiatan')
                ->where('laporan_kegiatan.id_bidang', $idBidang)
                ->when($status, fn ($q) => $q->where('laporan_kegiatan.status', $status))
                ->orderByDesc('laporan_kegiatan.tanggal_kegiatan')
                ->orderByDesc('laporan_kegiatan.id_laporan_kegiatan')
                ->select([
                    'laporan_kegiatan.id_laporan_kegiatan',
                    'laporan_kegiatan.judul_kegiatan',
                    'laporan_kegiatan.tanggal_kegiatan',
                    'laporan_kegiatan.penjelasan_singkat',
                    'laporan_kegiatan.status',
                    'laporan_kegiatan.created_at',
                    'kegiatan.nama_kegiatan',
                ])
                ->paginate(10)
                ->withQueryString();
        } catch (QueryException $e) {
            Log::error('Gagal memuat daftar laporan kegiatan', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return response()->json(['pesan' => 'Daftar laporan belum bisa dimuat. Silakan coba lagi.'], 500);
        }

        return response()->json($laporan);
    }

    /**
     * Simpan laporan baru sebagai Draf.
     */
    public function store(Request $request)
    {
        $idBidang = $this->idBidangAkun();

        if (! $idBidang) {
            return $this->gagal($request, 'Akun Anda belum terhubung dengan bidang.', 403);
        }

        $data = Validator::make($request->all(), [
            'id_kegiatan'        => ['required', 'integer'],
            'judul_kegiatan'     => ['required', 'string', 'max:255'],
            'tanggal_kegiatan'   => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'penjelasan_singkat' => ['required', 'string', 'max:' . self::MAKS_PENJELASAN],
        ], [
            'id_kegiatan.required'              => 'Pilih kegiatan terlebih dahulu',
            'id_kegiatan.integer'               => 'Kegiatan tidak valid',
            'judul_kegiatan.required'           => 'Judul kegiatan wajib diisi',
            'judul_kegiatan.string'             => 'Judul kegiatan tidak valid',
            'judul_kegiatan.max'                => 'Judul kegiatan maksimal 255 karakter',
            'tanggal_kegiatan.required'         => 'Tanggal kegiatan wajib diisi',
            'tanggal_kegiatan.date_format'      => 'Format tanggal kegiatan tidak valid',
            'tanggal_kegiatan.before_or_equal'  => 'Tanggal kegiatan tidak boleh lebih dari hari ini',
            'penjelasan_singkat.required'       => 'Penjelasan singkat wajib diisi',
            'penjelasan_singkat.string'         => 'Penjelasan singkat tidak valid',
            'penjelasan_singkat.max'            => 'Penjelasan singkat maksimal ' . self::MAKS_PENJELASAN . ' karakter',
        ])->validate();

        try {
            $kegiatan = DB::table('kegiatan')
                ->join('program_kerja', 'program_kerja.id_program_kerja', '=', 'kegiatan.id_program_kerja')
                ->where('kegiatan.id_kegiatan', $data['id_kegiatan'])
                ->first(['kegiatan.id_kegiatan', 'program_kerja.id_bidang']);

            if (! $kegiatan || (int) $kegiatan->id_bidang !== (int) $idBidang) {
                if ($kegiatan) {
                    Log::warning('Isi laporan pada kegiatan bidang lain ditolak', [
                        'pengguna_id'    => Auth::id(),
                        'id_bidang_akun' => $idBidang,
                        'id_kegiatan'    => $data['id_kegiatan'],
                        'dicatat_pada'   => now()->toDateTimeString(),
                    ]);
                }

                // Pesan sama untuk "tidak ada" dan "bidang lain" agar tidak membocorkan data.
                throw ValidationException::withMessages([
                    'id_kegiatan' => 'Kegiatan tidak valid',
                ]);
            }

            $idBaru = DB::table('laporan_kegiatan')->insertGetId([
                'id_kegiatan'        => $kegiatan->id_kegiatan,
                'id_bidang'          => $idBidang,
                'judul_kegiatan'     => $data['judul_kegiatan'],
                'tanggal_kegiatan'   => $data['tanggal_kegiatan'],
                'penjelasan_singkat' => $data['penjelasan_singkat'],
                'status'             => 'Draf',
                'dibuat_oleh'        => Auth::id(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ], 'id_laporan_kegiatan');
        } catch (QueryException $e) {
            Log::error('Gagal menyimpan laporan kegiatan', [
                'pengisi_id'   => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Laporan belum bisa disimpan. Silakan coba lagi.', 500);
        }

        Log::info('Laporan kegiatan diisi', [
            'pengisi_id'          => Auth::id(),
            'pengisi_username'    => Auth::user()->nama_pengguna,
            'peran'               => Auth::user()->peran?->nama_peran,
            'id_laporan_kegiatan' => $idBaru,
            'id_bidang'           => $idBidang,
            'dicatat_pada'        => now()->toDateTimeString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'pesan'               => 'Laporan kegiatan berhasil disimpan sebagai Draf.',
                'id_laporan_kegiatan' => $idBaru,
                'status'              => 'Draf',
            ], 201);
        }

        return redirect()->back()->with('sukses', 'Laporan kegiatan berhasil disimpan sebagai Draf.');
    }

    private function idBidangAkun(): ?int
    {
        $id = DB::table('pengguna')
            ->where('id_pengguna', Auth::id())
            ->whereNull('dihapus_pada')
            ->value('id_bidang');

        return $id ? (int) $id : null;
    }

    private function gagal(Request $request, string $pesan, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], $status);
        }

        return redirect()->back()->withInput()->with('galat', $pesan);
    }
}