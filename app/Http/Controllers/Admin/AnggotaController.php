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
 * T-26 (B-016): form dan penyimpanan anggota baru.
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

    // ===== T-42: daftar anggota (T-43: ditambah join peran untuk kolom Posisi) =====

    public function index(Request $request)
    {
        // bidang = id_bidang, peran = id_peran, q = kata kunci. Kosong berarti tanpa filter.
        $request->validate([
            'bidang' => ['nullable', 'integer'],
            'peran'  => ['nullable', 'integer'],
            'q'      => ['nullable', 'string', 'max:100'],
        ], [
            'bidang.integer' => 'Filter bidang tidak valid',
            'peran.integer'  => 'Filter peran tidak valid',
            'q.max'          => 'Kata kunci terlalu panjang',
        ]);

        $query = DB::table('anggota')
            ->leftJoin('bidang', 'bidang.id_bidang', '=', 'anggota.id_bidang')
            ->leftJoin('unit_kerja', 'unit_kerja.id_unit_kerja', '=', 'anggota.id_unit_kerja')
            ->leftJoin('pengguna', 'pengguna.id_pengguna', '=', 'anggota.id_pengguna')
            ->leftJoin('peran', 'peran.id_peran', '=', 'pengguna.id_peran')
            ->whereNull('anggota.dihapus_pada')
            ->select(
                'anggota.id_anggota',
                'anggota.nama_lengkap',
                'anggota.nama_panggilan',
                'anggota.nomor_telepon',
                'anggota.status',
                'peran.nama_peran',
                'bidang.nama_bidang',
                'unit_kerja.nama_unit_kerja'
            )
            // DRAFT (belum dikonfirmasi klien): lengkap = 6 kolom opsional form terisi semua.
            // Dihitung di SQL supaya email/alamat tidak ikut terkirim ke respons.
            ->selectRaw("(
                NULLIF(TRIM(anggota.tempat_lahir), '') IS NOT NULL
                AND anggota.tanggal_lahir IS NOT NULL
                AND NULLIF(TRIM(anggota.alamat_lengkap), '') IS NOT NULL
                AND NULLIF(TRIM(anggota.email), '') IS NOT NULL
                AND anggota.id_unit_kerja IS NOT NULL
                AND NULLIF(TRIM(anggota.tingkat_pendidikan), '') IS NOT NULL
            ) AS lengkap");

        if ($request->filled('bidang')) {
            $query->where('anggota.id_bidang', (int) $request->query('bidang'));
        }

        if ($request->filled('peran')) {
            $query->where('pengguna.id_peran', (int) $request->query('peran'));
        }

        if ($request->filled('q')) {
            // Escape wildcard LIKE supaya "%" dan "_" dicari sebagai teks biasa.
            $kata = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim((string) $request->query('q')));
            $query->where(function ($w) use ($kata) {
                $w->where('anggota.nama_lengkap', 'like', "%{$kata}%")
                    ->orWhere('anggota.nama_panggilan', 'like', "%{$kata}%")
                    ->orWhere('pengguna.nama_pengguna', 'like', "%{$kata}%");
            });
        }

        $anggota = $query
            ->orderBy('anggota.nama_lengkap')
            ->orderBy('anggota.id_anggota')
            ->paginate(10)
            ->withQueryString();

        $daftarBidang = DB::table('bidang')
            ->orderBy('nama_bidang')
            ->get(['id_bidang', 'nama_bidang']);

        $daftarPeran = DB::table('peran')
            ->orderBy('nama_peran')
            ->get(['id_peran', 'nama_peran']);

        if ($request->expectsJson()) {
            return response()->json([
                'anggota'       => $anggota,
                'daftar_bidang' => $daftarBidang,
                'daftar_peran'  => $daftarPeran,
            ]);
        }

        return view('admin.anggota.index', [
            'anggota'      => $anggota,
            'daftarBidang' => $daftarBidang,
            'daftarPeran'  => $daftarPeran,
        ]);
    }

    // ===== T-26: mulai di sini =====

    public function create()
    {
        return view('admin.anggota.create', [
            'akunTersedia' => $this->akunTersedia()->get(),
            // Urut id = urutan seeder = urutan di Template Database Pengurus.
            'unitKerja'    => DB::table('unit_kerja')
                ->orderBy('id_unit_kerja')
                ->get(['id_unit_kerja', 'nama_unit_kerja']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturan(), $this->pesan());

        // Anti-tamper: nama dan bidang selalu diambil dari akun di server,
        // bukan dari input form (kolom itu hanya readonly di browser).
        if (! empty($data['id_pengguna'])) {
            $akun = DB::table('pengguna')
                ->where('id_pengguna', $data['id_pengguna'])
                ->first(['nama_lengkap', 'id_bidang']);

            $data['nama']      = $akun->nama_lengkap;
            $data['id_bidang'] = $akun->id_bidang;
        }

        try {
            $idAnggota = DB::table('anggota')->insertGetId([
                'id_pengguna'        => $data['id_pengguna'] ?? null,
                'nama_lengkap'       => $data['nama'],
                'nama_panggilan'     => $this->rapikanPanggilan($data['nama_panggilan'] ?? null),
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
                'bidang_usaha'       => $data['bidang_usaha'] ?? null,
                'bekerja'            => $data['bekerja'] ?? null,
                // Kalau tidak bekerja, instansi dikosongkan di server (bukan hanya di browser).
                'instansi_kerja'     => ($data['bekerja'] ?? null) === 'Tidak' ? null : ($data['instansi_kerja'] ?? null),
                // Boleh kosong: Penasihat, Ketua DWP, dan Wakil DWP tidak terikat bidang.
                'id_bidang'          => $data['id_bidang'] ?? null,
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

        return redirect()
            ->route('admin.anggota.index')
            ->with('success', 'Data berhasil disimpan.');
    }

    // Nama panggilan selalu berawalan "Ny." (mis. "Wiwik Suryo" atau "ny wiwik suryo" menjadi "Ny. Wiwik Suryo").
    // Awalan harus diikuti titik atau spasi, supaya nama seperti "Nyoman" tidak ikut terpotong.
    private function rapikanPanggilan(?string $nama): ?string
    {
        $nama = trim((string) preg_replace('/\s+/u', ' ', (string) $nama));
        $nama = preg_replace('/^(nyonya|ny)(\.\s*|\s+)/iu', '', $nama);

        return $nama === '' ? null : 'Ny. ' . $nama;
    }

    private function aturan(): array
    {
        $teks = 'regex:' . self::POLA_TEKS;

        return [
            'id_pengguna'        => [
                'nullable',
                'integer',
                Rule::exists('pengguna', 'id_pengguna')->whereNull('dihapus_pada'),
                'unique:anggota,id_pengguna',
            ],
            'nama'               => ['required', 'string', 'max:255', $teks],
            // Boleh kosong untuk peran yang tidak terikat bidang; kalau diisi harus bidang yang ada.
            'id_bidang'          => ['nullable', 'integer', 'exists:bidang,id_bidang'],
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
            'bekerja'            => ['nullable', Rule::in(['Ya', 'Tidak'])],
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
            'id_bidang.integer'             => 'Bidang tidak tersedia.',
            'id_bidang.exists'              => 'Bidang tidak tersedia.',
            'nomor_telepon.required'        => 'Nomor telepon wajib diisi.',
            'nomor_telepon.regex'           => 'Nomor telepon harus 8 sampai 15 digit angka.',
            'email.email'                   => 'Format email tidak valid.',
            'tanggal_lahir.date'            => 'Tanggal lahir tidak valid.',
            'tanggal_lahir.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'id_unit_kerja.exists'          => 'Unit kerja tidak tersedia.',
            'tingkat_pendidikan.in'         => 'Tingkat pendidikan tidak valid.',
            'bekerja.in'                    => 'Pilihan bekerja tidak valid.',
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

    public function pindahBidang(Request $request, $id)
    {
        $data = $request->validate([
            'id_bidang' => ['required', 'integer', 'exists:bidang,id_bidang'],
        ], [
            'id_bidang.required' => 'Bidang tujuan wajib dipilih.',
            'id_bidang.integer'  => 'Bidang tujuan tidak valid.',
            'id_bidang.exists'   => 'Bidang tujuan tidak tersedia.',
        ]);

        try {
            $hasil = DB::transaction(function () use ($id, $data) {
                $anggota = DB::table('anggota')
                    ->where('id_anggota', $id)
                    ->whereNull('dihapus_pada')
                    ->lockForUpdate()
                    ->first();

                if (! $anggota) {
                    return ['status' => 'tidak_ditemukan'];
                }

                if ($anggota->id_bidang === null) {
                    return ['status' => 'belum_memiliki_bidang'];
                }

                if ((int) $anggota->id_bidang === (int) $data['id_bidang']) {
                    return ['status' => 'bidang_sama'];
                }

                $bidangLama = DB::table('bidang')
                    ->where('id_bidang', $anggota->id_bidang)
                    ->value('nama_bidang');

                $bidangBaru = DB::table('bidang')
                    ->where('id_bidang', $data['id_bidang'])
                    ->value('nama_bidang');

                DB::table('anggota')
                    ->where('id_anggota', $id)
                    ->update([
                        'id_bidang'  => $data['id_bidang'],
                        'updated_at' => now(),
                    ]);

                if ($anggota->id_pengguna !== null) {
                    DB::table('pengguna')
                        ->where('id_pengguna', $anggota->id_pengguna)
                        ->update([
                            'id_bidang'  => $data['id_bidang'],
                            'updated_at' => now(),
                        ]);
                }

                return [
                    'status'        => 'berhasil',
                    'nama_anggota'  => $anggota->nama_lengkap,
                    'bidang_lama'   => $bidangLama,
                    'bidang_baru'   => $bidangBaru,
                    'id_bidang_lama' => $anggota->id_bidang,
                    'id_bidang_baru' => $data['id_bidang'],
                    'id_pengguna'   => $anggota->id_pengguna,
                ];
            });
        } catch (QueryException $e) {
            report($e);

            return back()->with('error', 'Perpindahan bidang gagal. Silakan coba lagi.');
        }

        if ($hasil['status'] === 'tidak_ditemukan') {
            return back()->with('error', 'Data anggota tidak ditemukan.');
        }

        if ($hasil['status'] === 'belum_memiliki_bidang') {
            return back()->with('error', 'Anggota belum memiliki bidang saat ini.');
        }

        if ($hasil['status'] === 'bidang_sama') {
            return back()->with('error', 'Bidang tujuan harus berbeda dari bidang saat ini.');
        }

        Log::info('Admin memindahkan bidang anggota', [
            'admin_id'        => Auth::id(),
            'admin_username'  => Auth::user()->nama_pengguna,
            'anggota_id'      => $id,
            'nama_anggota'    => $hasil['nama_anggota'],
            'id_pengguna'     => $hasil['id_pengguna'],
            'id_bidang_lama'  => $hasil['id_bidang_lama'],
            'bidang_lama'     => $hasil['bidang_lama'],
            'id_bidang_baru'  => $hasil['id_bidang_baru'],
            'bidang_baru'     => $hasil['bidang_baru'],
            'dicatat_pada'    => now()->toDateTimeString(),
        ]);

        return redirect()
            ->route('admin.anggota.index')
            ->with('success', 'Bidang anggota berhasil dipindahkan.');
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
