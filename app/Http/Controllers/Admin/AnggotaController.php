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
 * T-42 (B-020): daftar lengkap anggota seluruh bidang, dengan filter bidang.
 *
 * Akses: hanya Admin (middleware role di routes/web.php).
 * Respons hanya memuat kolom yang dibutuhkan tabel. Tidak ada kata sandi, hash,
 * token, atau data akun (pengguna) yang ikut terbaca.
 */
class AnggotaController extends Controller
{
    // ===== T-26: konstanta =====

    private const TINGKAT_PENDIDIKAN = [
        'SMA/SMK', 'D3', 'D4', 'S1 (Sarjana)', 'S2 (Magister)', 'S3 (Doktoral)',
    ];

    // AC5 (validasi karakter): tolak < > dan karakter kontrol
    private const POLA_TEKS = '/^[^<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*$/u';

    // ===== T-42: TIDAK DIUBAH =====

    public function index(Request $request)
    {
        // Nilai filter = id_bidang. Kosong berarti semua bidang.
        $request->validate([
            'bidang' => ['nullable', 'integer'],
        ], [
            'bidang.integer' => 'Filter bidang tidak valid',
        ]);

        $query = DB::table('anggota')
            ->leftJoin('bidang', 'bidang.id_bidang', '=', 'anggota.id_bidang')
            ->leftJoin('unit_kerja', 'unit_kerja.id_unit_kerja', '=', 'anggota.id_unit_kerja')
            ->whereNull('anggota.dihapus_pada')
            ->select(
                'anggota.id_anggota',
                'anggota.nama_lengkap',
                'anggota.nomor_telepon',
                'anggota.status',
                'bidang.nama_bidang',
                'unit_kerja.nama_unit_kerja'
            );

        if ($request->filled('bidang')) {
            $query->where('anggota.id_bidang', (int) $request->query('bidang'));
        }

        $anggota = $query
            ->orderBy('anggota.nama_lengkap')
            ->orderBy('anggota.id_anggota')
            ->paginate(10)
            ->withQueryString();

        $daftarBidang = DB::table('bidang')
            ->orderBy('nama_bidang')
            ->get(['id_bidang', 'nama_bidang']);

        if ($request->expectsJson()) {
            return response()->json([
                'anggota'       => $anggota,
                'daftar_bidang' => $daftarBidang,
            ]);
        }

        return view('admin.anggota.index', [
            'anggota'      => $anggota,
            'daftarBidang' => $daftarBidang,
            'filterBidang' => $request->filled('bidang') ? (int) $request->query('bidang') : null,
        ]);
    }

    // ===== T-26: mulai di sini =====

    public function create()
    {
        return view('admin.anggota.create', [
            'akunTersedia' => $this->akunTersedia()->get(),
            'unitKerja'    => DB::table('unit_kerja')
                ->orderBy('nama_unit_kerja')
                ->get(['id_unit_kerja', 'nama_unit_kerja']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturan(), $this->pesan());

        try {
            $idAnggota = DB::table('anggota')->insertGetId([
                'id_pengguna'        => $data['id_pengguna'] ?? null,
                'nama_lengkap'       => $data['nama'],
                'nama_panggilan'     => $data['nama_panggilan'] ?? null,
                'nomor_telepon'      => $data['nomor_telepon'],
                'tempat_lahir'       => $data['tempat_lahir'] ?? null,
                'tanggal_lahir'      => $data['tanggal_lahir'] ?? null,
                'alamat_lengkap'     => $data['alamat_lengkap'] ?? null,
                'email'              => $data['email'] ?? null,
                'nama_suami'         => $data['nama_suami'] ?? null,
                'id_unit_kerja'      => $data['id_unit_kerja'] ?? null,
                'tingkat_pendidikan' => $data['tingkat_pendidikan'] ?? null,
                'bidang_pendidikan'  => $data['bidang_pendidikan'] ?? null,
                'keahlian'           => $data['keahlian'] ?? null,
                'instansi_kerja'     => $data['instansi_kerja'] ?? null,
                'bidang_usaha'       => $data['bidang_usaha'] ?? null,
                'id_bidang'          => $data['id_bidang'],
                'status'             => 'aktif',
                'dibuat_oleh'        => Auth::id(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (QueryException $e) {
            report($e); // detail hanya masuk log server

            return back()->withInput()
                ->with('error', 'Anggota belum bisa disimpan. Silakan coba lagi.');
        }

        // AC6: waktu, Admin pelaku, nama anggota; tanpa data sensitif
        Log::info('Admin membuat anggota baru', [
            'admin_id'       => Auth::id(),
            'admin_username' => Auth::user()->nama_pengguna,
            'anggota_id'     => $idAnggota,
            'nama_anggota'   => $data['nama'],
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.anggota.create')
            ->with('sukses', 'Data anggota berhasil disimpan.');
    }

    private function aturan(): array
    {
        $teks = 'regex:' . self::POLA_TEKS;

        return [
            'id_pengguna'        => [
                'nullable', 'integer',
                Rule::exists('pengguna', 'id_pengguna')->whereNull('dihapus_pada'),
                'unique:anggota,id_pengguna',
            ],
            'nama'               => ['required', 'string', 'max:255', $teks],
            'id_bidang'          => ['required', 'integer', 'exists:bidang,id_bidang'],
            'nomor_telepon'      => ['required', 'regex:/^[0-9]{8,15}$/'],
            'nama_panggilan'     => ['nullable', 'string', 'max:255', $teks],
            'tempat_lahir'       => ['nullable', 'string', 'max:255', $teks],
            'tanggal_lahir'      => ['nullable', 'date', 'before_or_equal:today'],
            'email'              => ['nullable', 'email', 'max:255'],
            'alamat_lengkap'     => ['nullable', 'string', 'max:1000', $teks],
            'nama_suami'         => ['nullable', 'string', 'max:255', $teks],
            'id_unit_kerja'      => ['nullable', 'integer', 'exists:unit_kerja,id_unit_kerja'],
            'tingkat_pendidikan' => ['nullable', Rule::in(self::TINGKAT_PENDIDIKAN)],
            'bidang_pendidikan'  => ['nullable', 'string', 'max:255', $teks],
            'keahlian'           => ['nullable', 'string', 'max:1000', $teks],
            'bidang_usaha'       => ['nullable', 'string', 'max:255', $teks],
            'instansi_kerja'     => ['nullable', 'string', 'max:255', $teks],
        ];
    }

    private function pesan(): array
    {
        $pesanTeks = 'Mengandung karakter yang tidak diperbolehkan.';

        return [
            'id_pengguna.integer'           => 'Akun tidak tersedia.',
            'id_pengguna.exists'            => 'Akun tidak tersedia.',
            'id_pengguna.unique'            => 'Akun tersebut sudah terhubung dengan anggota.',
            'nama.required'                 => 'Nama wajib diisi.',
            'id_bidang.required'            => 'Bidang tidak tersedia.',
            'id_bidang.integer'             => 'Bidang tidak tersedia.',
            'id_bidang.exists'              => 'Bidang tidak tersedia.',
            'nomor_telepon.required'        => 'Nomor telepon wajib diisi.',
            'nomor_telepon.regex'           => 'Nomor telepon harus 8 sampai 15 digit angka.',
            'email.email'                   => 'Format email tidak valid.',
            'tanggal_lahir.date'            => 'Tanggal lahir tidak valid.',
            'tanggal_lahir.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'id_unit_kerja.exists'          => 'Unit kerja tidak tersedia.',
            'tingkat_pendidikan.in'         => 'Tingkat pendidikan tidak valid.',
            'nama.regex'                    => $pesanTeks,
            'nama_panggilan.regex'          => $pesanTeks,
            'tempat_lahir.regex'            => $pesanTeks,
            'alamat_lengkap.regex'          => $pesanTeks,
            'nama_suami.regex'              => $pesanTeks,
            'bidang_pendidikan.regex'       => $pesanTeks,
            'keahlian.regex'                => $pesanTeks,
            'bidang_usaha.regex'            => $pesanTeks,
            'instansi_kerja.regex'          => $pesanTeks,
        ];
    }

    // Akun yang bisa dihubungkan ke data anggota (satu akun hanya satu anggota, sesuai UK).
    private function akunTersedia()
    {
        return DB::table('pengguna')
            ->join('peran', 'peran.id_peran', '=', 'pengguna.id_peran')
            ->leftJoin('bidang', 'bidang.id_bidang', '=', 'pengguna.id_bidang')
            ->leftJoin('anggota', 'anggota.id_pengguna', '=', 'pengguna.id_pengguna')
            ->whereNull('anggota.id_anggota')
            ->whereNull('pengguna.dihapus_pada')
            ->select(
                'pengguna.id_pengguna',
                'pengguna.nama_pengguna as username',
                'pengguna.nama_lengkap as nama',
                'peran.nama_peran as peran',
                'pengguna.id_bidang',
                'bidang.nama_bidang'
            )
            ->orderBy('pengguna.nama_pengguna');
    }
}