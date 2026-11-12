<?php

namespace App\Http\Controllers\Admin;
use App\Rules\BidangSesuaiPeran;
use App\Http\Controllers\Controller;
use App\Rules\KataSandiKuat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AkunController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->leftJoin('bidang', 'pengguna.id_bidang', '=', 'bidang.id_bidang')
            ->whereNull('pengguna.dihapus_pada')
            ->select(
                'pengguna.id_pengguna',
                'pengguna.nama_lengkap',
                'pengguna.nama_pengguna',
                'pengguna.status',
                'peran.nama_peran'
            )
            // Bidang tunggal dari kolom biasa, atau gabungan bidang dari pivot (PJ)
            ->selectRaw("COALESCE(bidang.nama_bidang, (
                SELECT GROUP_CONCAT(b.nama_bidang ORDER BY b.id_bidang SEPARATOR ', ')
                FROM pengguna_bidang pb
                JOIN bidang b ON b.id_bidang = pb.id_bidang
                WHERE pb.id_pengguna = pengguna.id_pengguna
            )) AS nama_bidang");

        if ($request->filled('peran')) {
            $query->where('peran.nama_peran', $request->peran);
        }
        if ($request->filled('bidang')) {
            $namaBidang = $request->bidang;
            $query->where(function ($w) use ($namaBidang) {
                $w->where('bidang.nama_bidang', $namaBidang)
                    ->orWhereExists(function ($s) use ($namaBidang) {
                        $s->select(DB::raw(1))
                            ->from('pengguna_bidang as pb')
                            ->join('bidang as b2', 'b2.id_bidang', '=', 'pb.id_bidang')
                            ->whereColumn('pb.id_pengguna', 'pengguna.id_pengguna')
                            ->where('b2.nama_bidang', $namaBidang);
                    });
            });
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($w) use ($q) {
                $w->where('pengguna.nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('pengguna.nama_pengguna', 'like', "%{$q}%");
            });
        }

        $akun = $query->orderBy('pengguna.id_pengguna')->paginate(10)->withQueryString();

        // Statistik (dihitung dari seluruh akun, tidak ikut filter)
        $dasar = DB::table('pengguna')->whereNull('dihapus_pada');
        $stat = [
            'total'    => (clone $dasar)->count(),
            'aktif'    => (clone $dasar)->where('status', 'aktif')->count(),
            'nonaktif' => (clone $dasar)->where('status', 'nonaktif')->count(),
            'admin'    => DB::table('pengguna')
                ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
                ->whereNull('pengguna.dihapus_pada')
                ->where('peran.nama_peran', 'Admin')
                ->count(),
        ];

        $daftarPeran  = DB::table('peran')->orderBy('id_peran')->pluck('nama_peran');
        $daftarBidang = DB::table('bidang')->orderBy('nama_bidang')->pluck('nama_bidang');

        $tujuanAlih = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.status', 'aktif')
            ->where('peran.nama_peran', '!=', 'Admin')
            ->where('pengguna.id_pengguna', '!=', Auth::id())
            ->orderBy('pengguna.nama_lengkap')
            ->get(['pengguna.id_pengguna', 'pengguna.nama_lengkap', 'peran.nama_peran']);

        $peranTurun = config('dwp.peran_ubah');
        $petaBidang = [];
        foreach ($peranTurun as $p) {
            $petaBidang[$p] = BidangSesuaiPeran::bidangBoleh($p);
        }
        $penuh       = $this->slotPenuh();
        $tanpaBidang = config('dwp.peran_tanpa_bidang');
        $peranMulti  = config('dwp.peran_multi_bidang', []);

        return view('admin.akun.index', compact(
            'akun', 'stat', 'daftarPeran', 'daftarBidang',
            'tujuanAlih', 'peranTurun', 'petaBidang', 'penuh', 'tanpaBidang', 'peranMulti'
        ));
    }

    // =====================================================================
    // HELPER
    // =====================================================================

    private function slotPenuh(): array
    {
        $penuh = [];
        $daftarBidang = DB::table('bidang')->orderBy('id_bidang')->pluck('nama_bidang');

        foreach (config('dwp.peran_satu_per_slot') as $p) {
            foreach ($daftarBidang as $b) {
                if (BidangSesuaiPeran::slotTerisi($p, $b)) {
                    $penuh[$p][] = $b;
                }
            }
        }
        return $penuh;
    }

    /** Aturan validasi untuk field bidang[] (array). */
    private function aturanBidang(?string $peran, ?int $kecualiId = null): array
    {
        if (BidangSesuaiPeran::tanpaBidang($peran)) {
            return ['bidang' => ['nullable']];
        }

        $multi = BidangSesuaiPeran::multiBidang($peran);

        return [
            'bidang'   => $multi
                ? ['bail', 'required', 'array', 'min:1']
                : ['bail', 'required', 'array', 'size:1'],
            'bidang.*' => array_filter([
                'bail',
                'string',
                $multi ? 'distinct' : null,
                Rule::exists('bidang', 'nama_bidang'),
                new BidangSesuaiPeran($peran, $kecualiId),
            ]),
        ];
    }

    private function pesanBidang(): array
    {
        return [
            'bidang.required'          => 'Bidang wajib dipilih',
            'bidang.array'             => 'Format bidang tidak valid',
            'bidang.min'               => 'Pilih minimal satu bidang',
            'bidang.size'              => 'Peran ini hanya boleh memiliki satu bidang',
            'bidang.*.exists'          => 'Bidang tidak valid',
            'bidang.*.distinct'        => 'Bidang tidak boleh dipilih dua kali',
        ];
    }

    /**
     * Simpan bidang akun sesuai jenis peran.
     * - Peran multi-bidang (PJ): disimpan di pivot, kolom pengguna.id_bidang null.
     * - Peran biasa: kolom pengguna.id_bidang (nilainya DIKEMBALIKAN, caller yang menyimpan).
     * - Peran tanpa bidang: null.
     */
    private function simpanBidang(int $idPengguna, ?string $peran, array $namaBidang): ?int
    {
        DB::table('pengguna_bidang')->where('id_pengguna', $idPengguna)->delete();

        if (BidangSesuaiPeran::tanpaBidang($peran) || empty($namaBidang)) {
            return null;
        }

        $ids = DB::table('bidang')
            ->whereIn('nama_bidang', $namaBidang)
            ->orderBy('id_bidang')
            ->pluck('id_bidang');

        if (BidangSesuaiPeran::multiBidang($peran)) {
            DB::table('pengguna_bidang')->insert(
                $ids->map(fn ($b) => ['id_pengguna' => $idPengguna, 'id_bidang' => $b])->all()
            );
            return null;
        }

        return $ids->first();
    }

    /** Daftar nama bidang suatu akun (pivot dulu, kalau kosong pakai kolom id_bidang). */
    private function bidangAkun(int $idPengguna, ?int $idBidang): array
    {
        $pivot = DB::table('pengguna_bidang')
            ->join('bidang', 'bidang.id_bidang', '=', 'pengguna_bidang.id_bidang')
            ->where('pengguna_bidang.id_pengguna', $idPengguna)
            ->orderBy('bidang.id_bidang')
            ->pluck('bidang.nama_bidang')
            ->all();

        if ($pivot) {
            return $pivot;
        }

        $satu = $idBidang
            ? DB::table('bidang')->where('id_bidang', $idBidang)->value('nama_bidang')
            : null;

        return $satu ? [$satu] : [];
    }

    // =====================================================================
    // CREATE / STORE
    // =====================================================================

    public function create()
    {
        $peta = [];
        foreach (config('dwp.peran_dipilih') as $p) {
            $peta[$p] = BidangSesuaiPeran::bidangBoleh($p);
        }

        return view('admin.akun.create', [
            'daftarPeran' => config('dwp.peran_dipilih'),
            'petaBidang'  => $peta,
            'penuh'       => $this->slotPenuh(),
            'tanpaBidang' => config('dwp.peran_tanpa_bidang'),
            'peranMulti'  => config('dwp.peran_multi_bidang', []),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'     => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pengguna', 'nama_pengguna'),
            ],
            'peran'    => ['required', Rule::in(config('dwp.peran_dipilih'))],
            'password' => ['required', 'string', 'min:8', 'max:100', new KataSandiKuat()],
            ...$this->aturanBidang($request->input('peran')),
        ], [
            'nama.required'     => 'Nama lengkap wajib diisi',
            'username.required' => 'Nama pengguna wajib diisi',
            'username.unique'   => 'Nama pengguna sudah digunakan',
            'peran.required'    => 'Peran wajib dipilih',
            'peran.in'          => 'Peran tidak valid',
            'password.required' => 'Kata sandi awal wajib diisi',
            'password.min'      => 'Kata sandi awal minimal 8 karakter',
        ] + $this->pesanBidang());

        DB::transaction(function () use ($request) {
            $idPeran = DB::table('peran')->where('nama_peran', $request->peran)->value('id_peran');

            $idBaru = DB::table('pengguna')->insertGetId([
                'nama_lengkap'           => $request->nama,
                'nama_pengguna'          => $request->username,
                'kata_sandi_hash'        => Hash::make($request->password),
                'status'                 => 'aktif',
                'perlu_ganti_kata_sandi' => 1,
                'id_peran'               => $idPeran,
                'id_bidang'              => null,
                'created_at'             => now(),
                'updated_at'             => now(),
            ], 'id_pengguna');

            $idBidang = $this->simpanBidang($idBaru, $request->peran, $request->input('bidang', []));

            DB::table('pengguna')->where('id_pengguna', $idBaru)->update(['id_bidang' => $idBidang]);
        });

        Log::info('Admin membuat akun baru', [
            'admin_id'       => Auth::id(),
            'admin_username' => Auth::user()->nama_pengguna,
            'akun_baru'      => $request->username,
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Akun berhasil dibuat.');
    }

    // =====================================================================
    // EDIT / UPDATE
    // =====================================================================

    public function edit($id)
    {
        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select(
                'pengguna.id_pengguna',
                'pengguna.id_bidang',
                'pengguna.nama_lengkap',
                'pengguna.nama_pengguna',
                'peran.nama_peran'
            )
            ->first();

        abort_if(!$akun, 404);

        return view('admin.akun.edit', [
            'akun'           => $akun,
            'daftarBidang'   => BidangSesuaiPeran::bidangBoleh($akun->nama_peran),
            'bidangTerpilih' => $this->bidangAkun((int) $akun->id_pengguna, $akun->id_bidang),
            'multi'          => BidangSesuaiPeran::multiBidang($akun->nama_peran),
        ]);
    }

    public function update(Request $request, $id)
    {
        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select('pengguna.id_pengguna', 'peran.nama_peran')
            ->first();

        abort_if(!$akun, 404);

        $request->validate([
            'nama'     => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pengguna', 'nama_pengguna')->ignore($id, 'id_pengguna'),
            ],
            ...$this->aturanBidang($akun->nama_peran, (int) $akun->id_pengguna),
        ], [
            'nama.required'     => 'Nama lengkap wajib diisi',
            'username.required' => 'Nama pengguna wajib diisi',
            'username.unique'   => 'Nama pengguna sudah digunakan',
        ] + $this->pesanBidang());

        DB::transaction(function () use ($request, $akun, $id) {
            $idBidang = $this->simpanBidang((int) $id, $akun->nama_peran, $request->input('bidang', []));

            DB::table('pengguna')->where('id_pengguna', $id)->update([
                'nama_lengkap'  => $request->nama,
                'nama_pengguna' => $request->username,
                'id_bidang'     => $idBidang,
                'updated_at'    => now(),
            ]);
        });

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Data akun berhasil diubah.');
    }

    public function updateBidang(Request $request, $id)
    {
        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select('pengguna.id_pengguna', 'peran.nama_peran')
            ->first();

        abort_if(!$akun, 404);

        if (BidangSesuaiPeran::tanpaBidang($akun->nama_peran)) {
            return back()->withErrors(['bidang' => 'Peran ini tidak memiliki bidang.']);
        }

        $request->validate(
            $this->aturanBidang($akun->nama_peran, (int) $akun->id_pengguna),
            $this->pesanBidang()
        );

        DB::transaction(function () use ($request, $akun, $id) {
            $idBidang = $this->simpanBidang((int) $id, $akun->nama_peran, $request->input('bidang', []));

            DB::table('pengguna')->where('id_pengguna', $id)->update([
                'id_bidang'  => $idBidang,
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Bidang akun berhasil diperbarui.');
    }

    // =====================================================================
    // UBAH PERAN
    // =====================================================================

    public function alihAdmin(Request $request)
    {
        $idAsal   = (int) Auth::id();
        $idTujuan = (int) $request->input('id_tujuan');

        if ($idTujuan === $idAsal) {
            return back()->with('galat', 'Tidak bisa mengalihkan peran Admin ke akun sendiri.');
        }

        $peranTurunBoleh = config('dwp.peran_ubah');

        $request->validate([
            'id_tujuan'   => ['required', 'integer'],
            'peran_turun' => ['required', Rule::in($peranTurunBoleh)],
            // kecualiId = akun tujuan: dia akan jadi Admin, jadi slot lamanya kosong
            ...$this->aturanBidang($request->input('peran_turun'), $idTujuan),
        ], [
            'id_tujuan.required'   => 'Akun tujuan wajib dipilih',
            'peran_turun.required' => 'Peran baru untuk akun Anda wajib dipilih',
            'peran_turun.in'       => 'Peran tidak valid',
        ] + $this->pesanBidang());

        $namaPeranTurun = $request->peran_turun;
        $idAdmin        = DB::table('peran')->where('nama_peran', 'Admin')->value('id_peran');
        $idPeranTurun   = DB::table('peran')->where('nama_peran', $namaPeranTurun)->value('id_peran');

        try {
            DB::transaction(function () use ($idAsal, $idTujuan, $idAdmin, $idPeranTurun, $namaPeranTurun, $request) {
                // kunci kedua baris supaya dua request bersamaan tidak saling menimpa
                $baris = DB::table('pengguna')
                    ->whereIn('id_pengguna', [$idAsal, $idTujuan])
                    ->whereNull('dihapus_pada')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id_pengguna');

                $asal   = $baris->get($idAsal);
                $tujuan = $baris->get($idTujuan);

                if (! $asal || (int) $asal->id_peran !== (int) $idAdmin) {
                    throw new \DomainException('Hanya Admin yang dapat mengalihkan peran Admin.');
                }
                if (! $tujuan) {
                    throw new \DomainException('Akun tujuan tidak ditemukan.');
                }
                if ($tujuan->status !== 'aktif') {
                    throw new \DomainException('Akun tujuan harus berstatus aktif.');
                }
                if ((int) $tujuan->id_peran === (int) $idAdmin) {
                    throw new \DomainException('Akun tujuan sudah berperan Admin.');
                }

                // Tujuan -> Admin (bidang tetap dari config; pivot PJ ikut dibersihkan)
                $bidangAdmin = $this->simpanBidang($idTujuan, 'Admin', BidangSesuaiPeran::bidangBoleh('Admin'));
                DB::table('pengguna')->where('id_pengguna', $idTujuan)->update([
                    'id_peran'   => $idAdmin,
                    'id_bidang'  => $bidangAdmin,
                    'updated_at' => now(),
                ]);

                // Asal -> peran pilihan Admin
                $bidangTurun = $this->simpanBidang($idAsal, $namaPeranTurun, (array) $request->input('bidang', []));
                DB::table('pengguna')->where('id_pengguna', $idAsal)->update([
                    'id_peran'   => $idPeranTurun,
                    'id_bidang'  => $bidangTurun,
                    'updated_at' => now(),
                ]);
            });
        } catch (\DomainException $e) {
            return back()->with('galat', $e->getMessage())->withInput();
        }

        Log::info('Admin mengalihkan peran Admin', [
            'admin_lama_id' => $idAsal,
            'admin_baru_id' => $idTujuan,
            'peran_baru_admin_lama' => $namaPeranTurun,
            'bidang_baru_admin_lama' => implode(', ', (array) $request->input('bidang', [])),
            'dicatat_pada'  => now()->toDateTimeString(),
        ]);

        $rute = $namaPeranTurun === 'Penasihat' ? 'penasihat.dashboard' : 'beranda';

        return redirect()->route($rute)
            ->with('sukses', 'Peran Admin berhasil dialihkan.');
    }

    public function editPeran($id)
    {
        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select(
                'pengguna.id_pengguna',
                'pengguna.nama_lengkap',
                'pengguna.nama_pengguna',
                'peran.nama_peran'
            )
            ->first();

        abort_if((int) $id === (int) Auth::id(), 403);
        abort_if(!$akun, 404);

        $daftarPeran = config('dwp.peran_ubah');

        return view('admin.akun.peran', compact('akun', 'daftarPeran'));
    }

    public function updatePeran(Request $request, $id)
    {
        // Admin tidak boleh mengubah peran akunnya sendiri
        abort_if((int) $id === (int) Auth::id(), 403);

        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select(
                'pengguna.id_pengguna',
                'pengguna.id_peran',
                'pengguna.id_bidang',
                'peran.nama_peran'
            )
            ->first();

        abort_if(!$akun, 404);

        $request->validate([
            'peran' => ['required', Rule::in(config('dwp.peran_ubah'))],
        ], [
            'peran.required' => 'Peran wajib dipilih',
            'peran.in'       => 'Peran tidak valid',
        ]);

        $namaPeranBaru = $request->peran;

        if ($namaPeranBaru === $akun->nama_peran) {
            return back()
                ->withErrors(['peran' => 'Peran baru tidak boleh sama dengan peran saat ini'])
                ->withInput();
        }

        $bidangLama = $this->bidangAkun((int) $akun->id_pengguna, $akun->id_bidang);

        if (BidangSesuaiPeran::tanpaBidang($namaPeranBaru)) {
            $bidangBaru = [];
        } else {
            // Utamakan bidang lama yang masih cocok, lalu sisanya.
            $boleh    = BidangSesuaiPeran::bidangBoleh($namaPeranBaru);
            $kandidat = array_values(array_unique(array_merge(array_intersect($bidangLama, $boleh), $boleh)));

            // Buang bidang yang slotnya sudah terisi (hanya berlaku untuk peran satu-per-slot).
            $bebas = array_values(array_filter(
                $kandidat,
                fn ($b) => ! BidangSesuaiPeran::slotTerisi($namaPeranBaru, $b, (int) $akun->id_pengguna)
            ));

            if (empty($bebas)) {
                return back()
                    ->withErrors(['peran' => "Semua slot peran {$namaPeranBaru} sudah terisi."])
                    ->withInput();
            }

            if (BidangSesuaiPeran::multiBidang($namaPeranBaru)) {
                // PJ: pertahankan semua bidang lama yang masih bebas, kalau tidak ada ambil satu.
                $dipertahankan = array_values(array_filter($bebas, fn ($b) => in_array($b, $bidangLama, true)));
                $bidangBaru    = $dipertahankan ?: [$bebas[0]];
            } else {
                $bidangBaru = [$bebas[0]];
            }
        }

        $idPeranBaru = DB::table('peran')->where('nama_peran', $namaPeranBaru)->value('id_peran');

        DB::transaction(function () use ($id, $idPeranBaru, $namaPeranBaru, $bidangBaru) {
            $idBidang = $this->simpanBidang((int) $id, $namaPeranBaru, $bidangBaru);

            DB::table('pengguna')->where('id_pengguna', $id)->update([
                'id_peran'   => $idPeranBaru,
                'id_bidang'  => $idBidang,
                'updated_at' => now(),
            ]);
        });

        Log::info('Admin mengubah peran akun', [
            'admin_id'      => Auth::id(),
            'akun_id'       => $id,
            'peran_lama_id' => $akun->id_peran,
            'peran_lama'    => $akun->nama_peran,
            'peran_baru_id' => $idPeranBaru,
            'peran_baru'    => $namaPeranBaru,
            'bidang_baru'   => implode(', ', $bidangBaru),
            'dicatat_pada'  => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Peran akun berhasil diubah.');
    }

    // =====================================================================
    // HAPUS / RESET / NONAKTIF
    // =====================================================================

    public function destroy($id)
    {
        // Admin tidak boleh menghapus akunnya sendiri
        if ((int) $id === (int) Auth::id()) {
            abort(403, 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $akun = DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->whereNull('dihapus_pada')
            ->first(['id_pengguna', 'nama_pengguna']);

        abort_if(!$akun, 404);

        DB::table('pengguna')->where('id_pengguna', $id)->update([
            'dihapus_pada' => now(),
            'updated_at'   => now(),
        ]);

        Log::info('Admin menghapus akun', [
            'admin_id'       => Auth::id(),
            'admin_username' => Auth::user()->nama_pengguna,
            'akun_id'        => $akun->id_pengguna,
            'akun_username'  => $akun->nama_pengguna,
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Akun berhasil dihapus.');
    }

    public function resetPassword($id)
    {
        // Admin tidak boleh mereset kata sandi akunnya sendiri
        if ((int) $id === (int) Auth::id()) {
            return back()->with('galat', 'Anda tidak dapat mereset kata sandi akun sendiri.');
        }

        $akun = DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->whereNull('dihapus_pada')
            ->first();

        abort_if(!$akun, 404);

        $kataSandiSementara = Str::password(12);

        DB::table('pengguna')
            ->where('id_pengguna', $id)
            ->update([
                'kata_sandi_hash'        => Hash::make($kataSandiSementara),
                'perlu_ganti_kata_sandi' => 1,
                'updated_at'             => now(),
            ]);

        Log::info('Admin mereset kata sandi akun', [
            'admin_id'     => Auth::id(),
            'akun_id'      => $id,
            'dicatat_pada' => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Kata sandi berhasil direset.')
            ->with('kata_sandi_sementara', $kataSandiSementara);
    }

    public function nonaktifkan($id)
    {
        // Admin tidak boleh menonaktifkan akunnya sendiri.
        if ((int) $id === (int) Auth::id()) {
            return back()->with('galat', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $akun = DB::transaction(function () use ($id) {
            $akun = DB::table('pengguna')
                ->where('id_pengguna', $id)
                ->whereNull('dihapus_pada')
                ->lockForUpdate()
                ->first(['id_pengguna', 'nama_pengguna', 'status']);

            if (!$akun) {
                abort(404, 'Akun tidak ditemukan.');
            }

            if ($akun->status !== 'aktif') {
                return null;
            }

            DB::table('pengguna')
                ->where('id_pengguna', $id)
                ->update([
                    'status'     => 'nonaktif',
                    'updated_at' => now(),
                ]);

            // Cabut seluruh sesi aktif milik akun tersebut.
            DB::table('sessions')
                ->where('user_id', $id)
                ->delete();

            return $akun;
        });

        if (!$akun) {
            return back()->with('galat', 'Akun tersebut sudah nonaktif.');
        }

        Log::info('Admin menonaktifkan akun', [
            'admin_id'        => Auth::id(),
            'target_id'       => $akun->id_pengguna,
            'target_username' => $akun->nama_pengguna,
        ]);

        return back()->with('sukses', 'Akun berhasil dinonaktifkan.');
    }
}
