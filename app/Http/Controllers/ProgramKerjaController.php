<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * B-041: tambah program kerja (T-81 endpoint, T-82 form).
 *
 * Akses  : peran di PeranProgramKerja::KELOLA_PROGRAM, dijaga middleware di routes/web.php.
 * Bidang : SELALU diambil dari akun yang masuk. id_bidang dan dibuat_oleh dari
 *          request tidak pernah dibaca, jadi tidak bisa dipalsukan.
 */
class ProgramKerjaController extends Controller
{
    /**
     * T-82: tampilkan form tambah program kerja untuk bidang akun yang masuk.
     */
    public function create()
    {
        $idBidang = Auth::user()->id_bidang;

        if (! $idBidang) {
            abort(403, 'Akun Anda belum terhubung dengan bidang.');
        }

        $namaBidang = DB::table('bidang')
            ->where('id_bidang', $idBidang)
            ->value('nama_bidang');

        return view('program-kerja.tambah', ['namaBidang' => $namaBidang]);
    }

    public function store(Request $request)
    {
        // Aturan dan pesan dipakai bersama dengan update() (T-103).
        $data = $request->validate(self::aturan(), self::pesan());

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
                'status'          => 'Diajukan',
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

        Log::info('Program kerja ditambahkan', [
            'pembuat_id'       => Auth::id(),
            'pembuat_username' => Auth::user()->nama_pengguna,
            'peran'            => Auth::user()->peran?->nama_peran,
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

        return redirect()
            ->route('program-kerja.bidang-saya')
            ->with('sukses', 'Program kerja berhasil ditambahkan.');
    }

        /**
     * T-104 (B-046): tampilkan form ubah program kerja milik bidang SENDIRI.
     * Kepemilikan dicek di server dari akun yang masuk, bukan dari request.
     */
    public function edit(int $id)
    {
        $idBidang = Auth::user()->id_bidang;

        if (! $idBidang) {
            abort(403, 'Akun Anda belum terhubung dengan bidang.');
        }

        try {
            $program = DB::table('program_kerja')->where('id_program_kerja', $id)->first();

            $namaBidang = DB::table('bidang')
                ->where('id_bidang', $idBidang)
                ->value('nama_bidang');
        } catch (QueryException $e) {
            Log::error('Gagal memuat form ubah program kerja', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            abort(500, 'Terjadi kesalahan. Silakan coba lagi.');
        }

        if (! $program) {
            abort(404, 'Program kerja tidak ditemukan.');
        }

        if ((int) $program->id_bidang !== (int) $idBidang) {
            Log::warning('Buka form ubah program kerja bidang lain ditolak', [
                'pengguna_id'      => Auth::id(),
                'id_bidang_akun'   => $idBidang,
                'id_program_kerja' => $id,
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            abort(403, 'Anda tidak memiliki hak akses.');
        }

        return view('program-kerja.edit', [
            'program'    => $program,
            'namaBidang' => $namaBidang,
        ]);
    }
    
    /**
     * T-103 (B-046): ubah program kerja milik bidang SENDIRI.
     *
     * Akses : peran di PeranProgramKerja::KELOLA_PROGRAM (middleware di routes/web.php).
     * Bidang: diambil dari akun yang masuk. id_bidang dan dibuat_oleh dari request
     *         tidak pernah dibaca. Program bidang lain ditolak 403 dan data tidak berubah.
     */
    public function update(Request $request, int $id)
    {
        $idBidang = DB::table('pengguna')
            ->where('id_pengguna', Auth::id())
            ->whereNull('dihapus_pada')
            ->value('id_bidang');

        if (! $idBidang) {
            return $this->gagal($request, 'Akun Anda belum terhubung dengan bidang.', 403);
        }

        try {
            $lama = DB::table('program_kerja')->where('id_program_kerja', $id)->first();
        } catch (QueryException $e) {
            Log::error('Gagal memuat program kerja untuk diubah', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Program kerja belum bisa diubah. Silakan coba lagi.', 500);
        }

        if (! $lama) {
            abort(404, 'Program kerja tidak ditemukan.');
        }

        // Cek kepemilikan di server, sebelum validasi.
        if ((int) $lama->id_bidang !== (int) $idBidang) {
            Log::warning('Ubah program kerja bidang lain ditolak', [
                'pengguna_id'      => Auth::id(),
                'id_bidang_akun'   => $idBidang,
                'id_program_kerja' => $id,
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            abort(403, 'Anda tidak memiliki hak akses.');
        }

        $data = $request->validate(self::aturan(), self::pesan());

        // Bandingkan dengan data lama, catat nama kolom yang berubah (tanpa nilainya).
        $berubah = [];
        foreach ($data as $kolom => $baru) {
            $lamaNilai = $lama->{$kolom};

            if ($kolom === 'anggaran') {
                $sama = ($lamaNilai === null || $baru === null)
                    ? $lamaNilai === $baru
                    : (float) $lamaNilai === (float) $baru;
            } elseif (in_array($kolom, ['tanggal_mulai', 'tanggal_selesai'], true)) {
                $sama = substr((string) $lamaNilai, 0, 10) === (string) $baru;
            } else {
                $sama = (string) ($lamaNilai ?? '') === (string) ($baru ?? '');
            }

            if (! $sama) {
                $berubah[] = $kolom;
            }
        }

        $statusLama  = property_exists($lama, 'status') ? (string) ($lama->status ?? '') : '';
        $ajukanUlang = strcasecmp($statusLama, 'Perlu Revisi') === 0;

        if ($ajukanUlang) {
            $berubah[] = 'status';
        }

        if ($berubah === []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'pesan'            => 'Tidak ada perubahan pada program kerja.',
                    'id_program_kerja' => $id,
                ]);
            }

            return redirect()
                ->route('program-kerja.bidang-saya')
                ->with('sukses', 'Tidak ada perubahan pada program kerja.');
        }

        $payloadUpdate = [
            'nama_program'    => $data['nama_program'],
            'tujuan'          => $data['tujuan'],
            'sasaran_peserta' => $data['sasaran_peserta'] ?? null,
            'metode'          => $data['metode'] ?? null,
            'tanggal_mulai'   => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'tempat'          => $data['tempat'] ?? null,
            'anggaran'        => $data['anggaran'] ?? null,
            'keterangan'      => $data['keterangan'] ?? null,
            'updated_at'      => now(),
        ];

        if ($ajukanUlang && property_exists($lama, 'status')) {
            $payloadUpdate['status'] = 'Diajukan';
        }

        try {
            DB::table('program_kerja')
                ->where('id_program_kerja', $id)
                ->where('id_bidang', $idBidang)
                ->update($payloadUpdate);
        } catch (QueryException $e) {
            Log::error('Gagal mengubah program kerja', [
                'pengguna_id'      => Auth::id(),
                'id_program_kerja' => $id,
                'kode_galat'       => $e->getCode(),
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Program kerja belum bisa diubah. Silakan coba lagi.', 500);
        }

        Log::info('Program kerja diubah', [
            'pengubah_id'       => Auth::id(),
            'pengubah_username' => Auth::user()->nama_pengguna,
            'peran'             => Auth::user()->peran?->nama_peran,
            'id_program_kerja'  => $id,
            'id_bidang'         => $idBidang,
            'kolom_berubah'     => $berubah,
            'dicatat_pada'      => now()->toDateTimeString(),
        ]);

        $pesanSukses = $ajukanUlang
            ? 'Program kerja berhasil diperbaiki dan diajukan kembali.'
            : 'Program kerja berhasil diubah.';

        if ($request->expectsJson()) {
            return response()->json([
                'pesan'            => $pesanSukses,
                'id_program_kerja' => $id,
            ]);
        }

        return redirect()
            ->route('program-kerja.bidang-saya')
            ->with('sukses', $pesanSukses);
    }

    /**
     * T-98 (B-041): daftar program kerja bidang SENDIRI.
     *
     * Akses : Pimpinan Bidang dan Anggota Bidang (dijaga middleware di routes/web.php).
     * Bidang: selalu diambil dari akun yang masuk; ?bidang= yang beda dari bidang akun ditolak 403.
     */
    public function bidangSaya(Request $request)
    {
        $idBidangSaya = Auth::user()->id_bidang;

        if (! $idBidangSaya) {
            abort(403, 'Akun Anda belum terhubung dengan bidang.');
        }

        // Tolak permintaan yang mencoba mengakses bidang lain.
        $diminta = $request->query('bidang');

        if (
            $diminta !== null
            && (! is_scalar($diminta) || (string) $diminta !== (string) $idBidangSaya)
        ) {
            Log::warning('Akses program kerja bidang lain ditolak', [
                'pengguna_id'    => Auth::id(),
                'id_bidang_akun' => $idBidangSaya,
                'dicatat_pada'   => now()->toDateTimeString(),
            ]);

            abort(403, 'Anda tidak memiliki hak akses.');
        }

        try {
            $program = DB::table('program_kerja')
                ->where('id_bidang', $idBidangSaya)
                ->when($request->filled('cari'), function ($query) use ($request) {
                    $query->where(
                        'nama_program',
                        'like',
                        '%' . trim($request->input('cari')) . '%'
                    );
                })
                ->select([
                    'id_program_kerja',
                    'nama_program',
                    'tanggal_mulai',
                    'tanggal_selesai',
                    'anggaran',
                    'status',
                ])
                ->selectSub(
                    DB::table('kegiatan')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('kegiatan.id_program_kerja', 'program_kerja.id_program_kerja')
                        ->whereNull('kegiatan.dihapus_pada'),
                    'jumlah_kegiatan'
                )
                ->orderByDesc('tanggal_mulai')
                ->orderByDesc('id_program_kerja')
                ->paginate(10)
                ->withQueryString();
        } catch (QueryException $e) {
            Log::error('Gagal memuat daftar program kerja bidang sendiri', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            abort(500, 'Daftar program kerja belum bisa dimuat. Silakan coba lagi.');
        }

        // Ambil nama bidang pengguna yang sedang login.
        $namaBidang = DB::table('bidang')
            ->where('id_bidang', $idBidangSaya)
            ->value('nama_bidang');

// Rekap anggaran program kerja bidang untuk tahun berjalan.
$tahunAnggaran = now()->year;
$totalAnggaran = (float) DB::table('program_kerja')
    ->where('id_bidang', $idBidangSaya)
    ->whereYear('tanggal_mulai', $tahunAnggaran)
    ->sum('anggaran');

        $pesan = $program->total() === 0
            ? 'Belum ada program kerja untuk bidang ini.'
            : null;

        if ($request->expectsJson()) {
            return response()->json([
                'bidang'  => $namaBidang,
                'data'    => $program->items(),
                'total'   => $program->total(),
                'halaman' => $program->currentPage(),
                'pesan'   => $pesan,
                'totalAnggaran' => $totalAnggaran,
                'tahunAnggaran' => $tahunAnggaran,
            ]);
        }

        return view('program-kerja.bidang-saya', [
            'programKerja' => $program,
            'namaBidang'   => $namaBidang,
            'pesan'        => $pesan,
            'totalAnggaran' => $totalAnggaran,
            'tahunAnggaran' => $tahunAnggaran,
        ]);
    }

    /**
     * T-88/T-89 (B-042): daftar program kerja bidang LAIN. Hanya baca.
     *
     * Akses : semua peran yang masuk [asumsi tim, AC B-042 poin 1]; route hanya memakai auth.
     * Bidang: bidang "saya" selalu diambil dari akun yang masuk, tanpa parameter
     *         bidang dari request. Akun tanpa bidang melihat semua program.
     * Aksi  : tidak ada. Tidak ada route ubah/hapus untuk halaman ini.
     */
    public function bidangLain(Request $request)
    {
        $idBidangSaya = Auth::user()->id_bidang;

        $cari = $request->query('cari');
        $cari = is_string($cari) ? mb_substr(trim($cari), 0, 100) : '';

        try {
            $program = DB::table('program_kerja')
                ->join('bidang', 'bidang.id_bidang', '=', 'program_kerja.id_bidang')
                ->when($idBidangSaya, fn ($q) => $q->where('program_kerja.id_bidang', '!=', $idBidangSaya))
                ->when($cari !== '', function ($q) use ($cari) {
                    $aman = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $cari);
                    $q->where('program_kerja.nama_program', 'like', '%' . $aman . '%');
                })
                ->select([
                    'program_kerja.id_program_kerja',
                    'program_kerja.nama_program',
                    'program_kerja.tanggal_mulai',
                    'program_kerja.tanggal_selesai',
                    'bidang.id_bidang',
                    'bidang.nama_bidang',
                ])
                ->orderByDesc('program_kerja.tanggal_mulai')
                ->orderByDesc('program_kerja.id_program_kerja')
                ->paginate(20)
                ->withQueryString();
        } catch (QueryException $e) {
            // Detail teknis hanya ke log, pengguna cukup melihat pesan umum.
            Log::error('Gagal memuat daftar program kerja bidang lain', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            abort(500, 'Terjadi kesalahan. Silakan coba lagi.');
        }

        $pesan = $program->total() === 0 ? 'Belum ada program kerja' : null;

        if ($request->expectsJson()) {
            return response()->json([
                'data'    => $program->items(),
                'total'   => $program->total(),
                'halaman' => $program->currentPage(),
                'pesan'   => $pesan,
            ]);
        }

        return view('program-kerja.bidang-lain', [
            'programKerja' => $program,
            'pesan'        => $pesan,
        ]);
    }

    private function gagal(Request $request, string $pesan, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], $status);
        }

        return redirect()->back()->withInput()->with('galat', $pesan);
    }

        /** Aturan validasi bersama store() dan update(). */
    private static function aturan(): array
    {
        return [
            'nama_program'    => ['required', 'string', 'max:255'],
            'tujuan'          => ['required', 'string', 'max:5000'],
            'sasaran_peserta' => ['required', 'string', 'max:255'],
            'metode'          => ['required', 'string', 'max:255'],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'tempat'          => ['required', 'string', 'max:255'],
            'anggaran'        => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'keterangan'      => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Pesan validasi bersama store() dan update(). */
    private static function pesan(): array
    {
        return [
            'nama_program.required'          => 'Nama program wajib diisi',
            'nama_program.max'               => 'Nama program maksimal 255 karakter',
            'tujuan.required'                => 'Tujuan wajib diisi',
            'tujuan.max'                     => 'Tujuan maksimal 5000 karakter',
            'tanggal_mulai.required'         => 'Tanggal mulai wajib diisi',
            'tanggal_mulai.date_format'      => 'Format tanggal mulai tidak valid',
            'tanggal_selesai.required'       => 'Tanggal selesai wajib diisi',
            'tanggal_selesai.date_format'    => 'Format tanggal selesai tidak valid',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
            'sasaran_peserta.required'       => 'Sasaran peserta wajib diisi',
            'sasaran_peserta.max'            => 'Sasaran peserta maksimal 255 karakter',
            'metode.required'                => 'Metode wajib diisi',
            'metode.max'                     => 'Metode maksimal 255 karakter',
            'tempat.required'                => 'Tempat wajib diisi',
            'tempat.max'                     => 'Tempat maksimal 255 karakter',
            'anggaran.required'              => 'Anggaran wajib diisi',
            'anggaran.numeric'               => 'Anggaran harus berupa angka',
            'anggaran.min'                   => 'Anggaran tidak boleh negatif',
            'anggaran.max'                   => 'Anggaran terlalu besar',
            'keterangan.max'                 => 'Keterangan maksimal 2000 karakter',
        ];
    }

    /**
     * T-93 (B-044): daftar program kerja SELURUH bidang, dengan filter bidang.
     * Hanya baca.
     *
     * Akses : Admin dan Penasihat (dijaga middleware role di routes/web.php).
     * Filter: ?bidang={id_bidang}, opsional; harus ada di tabel bidang.
     */
    public function seluruhBidang(Request $request)
    {
        $request->validate([
            'bidang' => ['nullable', 'integer', 'exists:bidang,id_bidang'],
            'status' => ['nullable', 'string', 'max:30'],
        ], [
            'bidang.integer' => 'Bidang tidak tersedia.',
            'bidang.exists'  => 'Bidang tidak tersedia.',
            'status.string'  => 'Status tidak valid.',
            'status.max'     => 'Status tidak valid.',
        ]);

        $idBidang = $request->filled('bidang') ? $request->integer('bidang') : null;
        $statusDipilih = $request->filled('status') ? $request->input('status') : null;

        try {
            $program = DB::table('program_kerja')
                ->join('bidang', 'bidang.id_bidang', '=', 'program_kerja.id_bidang')
                ->when($idBidang, fn ($q) => $q->where('program_kerja.id_bidang', $idBidang))
                ->when($statusDipilih, fn ($q) => $q->where('program_kerja.status', $statusDipilih))
                ->select([
                    'program_kerja.id_program_kerja',
                    'program_kerja.nama_program',
                    'program_kerja.tujuan',
                    'program_kerja.tanggal_mulai',
                    'program_kerja.tanggal_selesai',
                    'program_kerja.status',
                    'bidang.id_bidang',
                    'bidang.nama_bidang',
                ])
                ->orderByDesc('program_kerja.tanggal_mulai')
                ->orderByDesc('program_kerja.id_program_kerja')
                ->paginate(20)
                ->withQueryString();

            // Untuk pilihan filter di halaman (T-94).
            $daftarBidang = DB::table('bidang')
                ->orderBy('nama_bidang')
                ->get(['id_bidang', 'nama_bidang']);
        } catch (QueryException $e) {
            // Detail teknis hanya ke log, pengguna cukup melihat pesan umum.
            Log::error('Gagal memuat daftar program kerja seluruh bidang', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            abort(500, 'Terjadi kesalahan. Silakan coba lagi.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data'    => $program->items(),
                'total'   => $program->total(),
                'halaman' => $program->currentPage(),
                'bidang'  => $idBidang,
                'status'  => $statusDipilih,
                'pesan'   => $program->total() === 0 ? 'Belum ada program kerja' : null,
            ]);
        }

        // View dibuat di T-94. Hasil kosong: tampilkan "Belum ada program kerja".
        return view('admin.program-kerja.index', [
            'programKerja'  => $program,
            'daftarBidang'  => $daftarBidang,
            'bidangDipilih' => $idBidang,
            'statusDipilih' => $statusDipilih,
        ]);
    }
}