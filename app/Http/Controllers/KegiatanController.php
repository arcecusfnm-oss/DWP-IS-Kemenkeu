<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * B-048: tambah kegiatan pada program kerja (T-109 endpoint, T-110 form).
 *
 * Akses  : peran di PeranProgramKerja::KELOLA_KEGIATAN (Anggota Bidang, ikut user story),
 *          dijaga middleware di routes/web.php.
 * Program: hanya milik bidang akun yang masuk; dicek di server dari akun login.
 *          id_bidang dan dibuat_oleh dari request tidak pernah dibaca.
 * Tanggal: tanggal mulai dan selesai kegiatan harus di dalam periode program (dicek di aplikasi).
 */
class KegiatanController extends Controller
{
    /**
     * Daftar kegiatan milik bidang akun yang sedang masuk.
     */
    public function index(Request $request)
    {
        $idBidang = Auth::user()->id_bidang;

        if (! $idBidang) {
            abort(403, 'Akun Anda belum terhubung dengan bidang.');
        }

        $cari = $request->query('cari');
        $cari = is_string($cari) ? mb_substr(trim($cari), 0, 100) : '';
        $idProgram = $request->filled('program') && ctype_digit((string) $request->query('program'))
            ? (int) $request->query('program')
            : null;

        try {
            $kegiatan = DB::table('kegiatan')
                ->join('program_kerja', 'program_kerja.id_program_kerja', '=', 'kegiatan.id_program_kerja')
                ->where('program_kerja.id_bidang', $idBidang)
                ->whereNull('kegiatan.dihapus_pada')
                ->when($idProgram, fn ($q) => $q->where('kegiatan.id_program_kerja', $idProgram))
                ->when($cari !== '', function ($q) use ($cari) {
                    $aman = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $cari);
                    $q->where('kegiatan.nama_kegiatan', 'like', '%' . $aman . '%');
                })
                ->select([
                    'kegiatan.id_kegiatan',
                    'kegiatan.nama_kegiatan',
                    'kegiatan.tanggal_mulai',
                    'kegiatan.tanggal_selesai',
                    'kegiatan.deskripsi',
                    'program_kerja.id_program_kerja',
                    'program_kerja.nama_program',
                ])
                ->orderByDesc('kegiatan.tanggal_mulai')
                ->orderByDesc('kegiatan.id_kegiatan')
                ->paginate(10)
                ->withQueryString();

            $daftarProgram = DB::table('program_kerja')
                ->where('id_bidang', $idBidang)
                ->orderBy('nama_program')
                ->get(['id_program_kerja', 'nama_program']);

            $namaBidang = DB::table('bidang')
                ->where('id_bidang', $idBidang)
                ->value('nama_bidang');
        } catch (QueryException $e) {
            Log::error('Gagal memuat daftar kegiatan', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            abort(500, 'Daftar kegiatan belum bisa dimuat. Silakan coba lagi.');
        }

        return view('kegiatan.index', [
            'kegiatan'        => $kegiatan,
            'daftarProgram'   => $daftarProgram,
            'programDipilih'  => $idProgram,
            'namaBidang'      => $namaBidang,
        ]);
    }

    /**
     * T-110 (jalur dari Daftar Kegiatan): form tambah kegiatan dengan dropdown program kerja bidang sendiri.
     */
    public function createDariSidebar()
    {
        $idBidang = Auth::user()->id_bidang;

        if (! $idBidang) {
            abort(403, 'Akun Anda belum terhubung dengan bidang.');
        }

        try {
            $daftarProgram = DB::table('program_kerja')
                ->where('id_bidang', $idBidang)
                ->orderByDesc('tanggal_mulai')
                ->orderByDesc('id_program_kerja')
                ->get(['id_program_kerja', 'nama_program', 'tanggal_mulai', 'tanggal_selesai', 'anggaran']);

            $namaBidang = DB::table('bidang')
                ->where('id_bidang', $idBidang)
                ->value('nama_bidang');
        } catch (QueryException $e) {
            Log::error('Gagal memuat form tambah kegiatan', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            abort(500, 'Terjadi kesalahan. Silakan coba lagi.');
        }

        return view('kegiatan.tambah', [
            'program'       => null,
            'daftarProgram' => $daftarProgram,
            'namaBidang'    => $namaBidang,
        ]);
    }

    /**
     * T-110 (jalur dari Daftar Kegiatan): validasi pilihan program kerja lalu teruskan ke store().
     */
    public function storeDariSidebar(Request $request)
    {
        $idProgram = $request->input('id_program_kerja');

        if ($request->filled('id_program_kerja') && ctype_digit((string) $idProgram)) {
            return $this->store($request, (int) $idProgram);
        }

        $validator = Validator::make($request->all(), array_merge([
            'id_program_kerja' => ['required', 'integer'],
        ], self::aturanIsian()), array_merge([
            'id_program_kerja.required' => 'Pilih program kerja terlebih dahulu',
            'id_program_kerja.integer'  => 'Program kerja tidak valid',
        ], self::pesanIsian()));

        $validator->validate();
    }

    /**
     * T-110 (jalur dari baris Program Kerja): form tambah kegiatan dengan program kerja terkunci.
     */
    public function create(int $id)
    {
        $idBidang = Auth::user()->id_bidang;

        if (! $idBidang) {
            abort(403, 'Akun Anda belum terhubung dengan bidang.');
        }

        try {
            $program = DB::table('program_kerja')
                ->where('id_program_kerja', $id)
                ->first(['id_program_kerja', 'id_bidang', 'nama_program', 'tanggal_mulai', 'tanggal_selesai', 'anggaran']);

            $namaBidang = DB::table('bidang')
                ->where('id_bidang', $idBidang)
                ->value('nama_bidang');
        } catch (QueryException $e) {
            Log::error('Gagal memuat form tambah kegiatan', [
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
            Log::warning('Buka form tambah kegiatan pada program bidang lain ditolak', [
                'pengguna_id'      => Auth::id(),
                'id_bidang_akun'   => $idBidang,
                'id_program_kerja' => $id,
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            abort(403, 'Anda tidak memiliki hak akses.');
        }

        return view('kegiatan.tambah', [
            'program'       => $program,
            'daftarProgram' => collect(),
            'namaBidang'    => $namaBidang,
        ]);
    }

    public function store(Request $request, int $id)
    {
        $idBidang = DB::table('pengguna')
            ->where('id_pengguna', Auth::id())
            ->whereNull('dihapus_pada')
            ->value('id_bidang');

        if (! $idBidang) {
            return $this->gagal($request, 'Akun Anda belum terhubung dengan bidang.', 403);
        }

        try {
            $program = DB::table('program_kerja')
                ->where('id_program_kerja', $id)
                ->first(['id_program_kerja', 'id_bidang', 'tanggal_mulai', 'tanggal_selesai']);
        } catch (QueryException $e) {
            Log::error('Gagal memuat program kerja untuk kegiatan', [
                'pengguna_id'  => Auth::id(),
                'kode_galat'   => $e->getCode(),
                'dicatat_pada' => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa disimpan. Silakan coba lagi.', 500);
        }

        if (! $program) {
            abort(404, 'Program kerja tidak ditemukan.');
        }

        if ((int) $program->id_bidang !== (int) $idBidang) {
            Log::warning('Tambah kegiatan pada program bidang lain ditolak', [
                'pengguna_id'      => Auth::id(),
                'id_bidang_akun'   => $idBidang,
                'id_program_kerja' => $id,
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            abort(403, 'Anda tidak memiliki hak akses.');
        }

        $mulaiProgram   = substr((string) $program->tanggal_mulai, 0, 10);
        $selesaiProgram = substr((string) $program->tanggal_selesai, 0, 10);
        $periode = Carbon::parse($mulaiProgram)->format('d-m-Y')
            . ' s/d ' . Carbon::parse($selesaiProgram)->format('d-m-Y');

        $validator = Validator::make($request->all(), self::aturanIsian(), self::pesanIsian());

        $validator->after(function ($v) use ($request, $mulaiProgram, $selesaiProgram, $periode) {
            if ($v->errors()->hasAny(['tanggal_mulai', 'tanggal_selesai'])) {
                return;
            }

            $mulai   = (string) $request->input('tanggal_mulai');
            $selesai = (string) $request->input('tanggal_selesai');

            if ($mulai < $mulaiProgram || $mulai > $selesaiProgram) {
                $v->errors()->add('tanggal_mulai', "Tanggal mulai kegiatan harus dalam periode program ($periode)");
            }

            if ($selesai < $mulaiProgram || $selesai > $selesaiProgram) {
                $v->errors()->add('tanggal_selesai', "Tanggal selesai kegiatan harus dalam periode program ($periode)");
            }
        });

        $data = $validator->validate();

        try {
            $idBaru = DB::table('kegiatan')->insertGetId([
                'id_program_kerja' => $program->id_program_kerja,
                'nama_kegiatan'    => $data['nama_kegiatan'],
                'tanggal_mulai'    => $data['tanggal_mulai'],
                'tanggal_selesai'  => $data['tanggal_selesai'],
                'deskripsi'        => $data['deskripsi'],
                'dibuat_oleh'      => Auth::id(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ], 'id_kegiatan');
        } catch (QueryException $e) {
            Log::error('Gagal menyimpan kegiatan', [
                'pembuat_id'       => Auth::id(),
                'id_program_kerja' => $id,
                'kode_galat'       => $e->getCode(),
                'dicatat_pada'     => now()->toDateTimeString(),
            ]);

            return $this->gagal($request, 'Kegiatan belum bisa disimpan. Silakan coba lagi.', 500);
        }

        Log::info('Kegiatan ditambahkan', [
            'pembuat_id'       => Auth::id(),
            'pembuat_username' => Auth::user()->nama_pengguna,
            'peran'            => Auth::user()->peran?->nama_peran,
            'id_kegiatan'      => $idBaru,
            'id_program_kerja' => $program->id_program_kerja,
            'id_bidang'        => $idBidang,
            'nama_kegiatan'    => $data['nama_kegiatan'],
            'dicatat_pada'     => now()->toDateTimeString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'pesan'            => 'Kegiatan berhasil ditambahkan.',
                'id_kegiatan'      => $idBaru,
                'id_program_kerja' => $program->id_program_kerja,
            ], 201);
        }

        return redirect()
            ->route('kegiatan.index')
            ->with('sukses', 'Kegiatan berhasil ditambahkan.');
    }

    private static function aturanIsian(): array
    {
        return [
            'nama_kegiatan'   => ['required', 'string', 'max:255'],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'deskripsi'       => ['required', 'string', 'max:5000'],
        ];
    }

    private static function pesanIsian(): array
    {
        return [
            'nama_kegiatan.required'         => 'Nama kegiatan wajib diisi',
            'nama_kegiatan.string'           => 'Nama kegiatan tidak valid',
            'nama_kegiatan.max'              => 'Nama kegiatan maksimal 255 karakter',
            'tanggal_mulai.required'         => 'Tanggal mulai wajib diisi',
            'tanggal_mulai.date_format'      => 'Format tanggal mulai tidak valid',
            'tanggal_selesai.required'       => 'Tanggal selesai wajib diisi',
            'tanggal_selesai.date_format'    => 'Format tanggal selesai tidak valid',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
            'deskripsi.required'             => 'Deskripsi wajib diisi',
            'deskripsi.string'               => 'Deskripsi tidak valid',
            'deskripsi.max'                  => 'Deskripsi maksimal 5000 karakter',
        ];
    }

    private function gagal(Request $request, string $pesan, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], $status);
        }

        return redirect()->back()->withInput()->with('galat', $pesan);
    }
}