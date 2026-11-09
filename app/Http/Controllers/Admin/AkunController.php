<?php

namespace App\Http\Controllers\Admin;

use App\Rules\BidangSesuaiPeran;
use App\Http\Controllers\Controller;
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
                'peran.nama_peran',
                'bidang.nama_bidang'
            );

        if ($request->filled('peran')) {
            $query->where('peran.nama_peran', $request->peran);
        }
        if ($request->filled('bidang')) {
            $query->where('bidang.nama_bidang', $request->bidang);
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

        return view('admin.akun.index', compact('akun', 'stat', 'daftarPeran', 'daftarBidang'));
    }

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

    private function aturanBidang(?string $peran, ?int $kecualiId = null): array
    {
        if (BidangSesuaiPeran::tanpaBidang($peran)) {
            return ['nullable'];
        }
        return [
            'bail',
            'required',
            Rule::exists('bidang', 'nama_bidang'),
            new BidangSesuaiPeran($peran, $kecualiId),
        ];
    }

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
        ]);
    }

    public function edit($id)
    {
        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->leftJoin('bidang', 'pengguna.id_bidang', '=', 'bidang.id_bidang')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select(
                'pengguna.id_pengguna',
                'pengguna.nama_lengkap',
                'pengguna.nama_pengguna',
                'peran.nama_peran',
                'bidang.nama_bidang'
            )
            ->first();

        abort_if(!$akun, 404);

        $daftarBidang = BidangSesuaiPeran::bidangBoleh($akun->nama_peran);

        return view('admin.akun.edit', compact('akun', 'daftarBidang'));
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
                Rule::unique('pengguna', 'nama_pengguna')->ignore($id, 'id_pengguna')
            ],
            'bidang'   => $this->aturanBidang($akun->nama_peran, (int) $akun->id_pengguna),
        ], [
            'nama.required'     => 'Nama lengkap wajib diisi',
            'username.required' => 'Nama pengguna wajib diisi',
            'username.unique'   => 'Nama pengguna sudah digunakan',
            'bidang.required'   => 'Bidang wajib dipilih',
            'bidang.exists'     => 'Bidang tidak valid',
        ]);

        $idBidang = BidangSesuaiPeran::tanpaBidang($akun->nama_peran)
            ? null
            : DB::table('bidang')->where('nama_bidang', $request->bidang)->value('id_bidang');

        DB::table('pengguna')->where('id_pengguna', $id)->update([
            'nama_lengkap'  => $request->nama,
            'nama_pengguna' => $request->username,
            'id_bidang'     => $idBidang,
            'updated_at'    => now(),
        ]);

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
            return back()->withErrors(['id_bidang' => 'Peran ini tidak memiliki bidang.']);
        }

        $request->validate([
            'id_bidang' => ['required', 'integer', Rule::exists('bidang', 'id_bidang')],
        ], [
            'id_bidang.required' => 'Bidang wajib dipilih.',
            'id_bidang.integer'  => 'ID bidang tidak valid.',
            'id_bidang.exists'   => 'Bidang tidak terdaftar.',
        ]);

        $namaBidang = DB::table('bidang')->where('id_bidang', $request->id_bidang)->value('nama_bidang');

        $rule = new BidangSesuaiPeran($akun->nama_peran, (int) $akun->id_pengguna);
        $rule->validate('id_bidang', $namaBidang, function ($pesan) {
            throw \Illuminate\Validation\ValidationException::withMessages(['id_bidang' => $pesan]);
        });

        DB::table('pengguna')->where('id_pengguna', $id)->update([
            'id_bidang'  => $request->id_bidang,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Bidang akun berhasil diperbarui.');
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

        $daftarPeran = config('dwp.peran_dipilih');

        return view('admin.akun.peran', compact('akun', 'daftarPeran'));
    }

    public function updatePeran(Request $request, $id)
    {
        // Admin tidak boleh mengubah peran akunnya sendiri
        abort_if((int) $id === (int) Auth::id(), 403);

        $akun = DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->leftJoin('bidang', 'pengguna.id_bidang', '=', 'bidang.id_bidang')
            ->whereNull('pengguna.dihapus_pada')
            ->where('pengguna.id_pengguna', $id)
            ->select(
                'pengguna.id_pengguna',
                'pengguna.id_peran',
                'peran.nama_peran',
                'bidang.nama_bidang'
            )
            ->first();

        abort_if(!$akun, 404);

        $request->validate([
            'peran' => ['required', Rule::in(config('dwp.peran_dipilih'))],
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

        if (BidangSesuaiPeran::tanpaBidang($namaPeranBaru)) {
            // Penasihat: tanpa bidang
            $bidangBaru = null;
        } else {
            // Utamakan bidang lama kalau masih cocok, kalau tidak cari bidang
            // pertama yang slotnya masih kosong.
            $boleh = BidangSesuaiPeran::bidangBoleh($namaPeranBaru);
            $kandidat = in_array($akun->nama_bidang, $boleh, true)
                ? array_merge([$akun->nama_bidang], array_diff($boleh, [$akun->nama_bidang]))
                : $boleh;

            $bidangBaru = null;
            foreach ($kandidat as $b) {
                if (! BidangSesuaiPeran::slotTerisi($namaPeranBaru, $b, (int) $akun->id_pengguna)) {
                    $bidangBaru = $b;
                    break;
                }
            }

            if ($bidangBaru === null) {
                return back()
                    ->withErrors(['peran' => "Semua slot peran {$namaPeranBaru} sudah terisi."])
                    ->withInput();
            }
        }

        $idPeranBaru  = DB::table('peran')->where('nama_peran', $namaPeranBaru)->value('id_peran');
        $idBidangBaru = $bidangBaru
            ? DB::table('bidang')->where('nama_bidang', $bidangBaru)->value('id_bidang')
            : null;

        DB::table('pengguna')->where('id_pengguna', $id)->update([
            'id_peran'   => $idPeranBaru,
            'id_bidang'  => $idBidangBaru,
            'updated_at' => now(),
        ]);

        Log::info('Admin mengubah peran akun', [
            'admin_id'      => Auth::id(),
            'akun_id'       => $id,
            'peran_lama_id' => $akun->id_peran,
            'peran_lama'    => $akun->nama_peran,
            'peran_baru_id' => $idPeranBaru,
            'peran_baru'    => $namaPeranBaru,
            'bidang_baru'   => $bidangBaru,
            'dicatat_pada'  => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Peran akun berhasil diubah.');
    }

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

    public function store(Request $request)
    {
        $request->validate([
            'nama'     => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pengguna', 'nama_pengguna')
            ],
            'peran'    => ['required', Rule::in(config('dwp.peran_dipilih'))],
            'bidang'   => $this->aturanBidang($request->input('peran')),
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ], [
            'nama.required'     => 'Nama lengkap wajib diisi',
            'username.required' => 'Nama pengguna wajib diisi',
            'username.unique'   => 'Nama pengguna sudah digunakan',
            'peran.required'    => 'Peran wajib dipilih',
            'peran.in'          => 'Peran tidak valid',
            'bidang.required'   => 'Bidang wajib dipilih',
            'bidang.exists'     => 'Bidang tidak valid',
            'password.required' => 'Kata sandi awal wajib diisi',
            'password.min'      => 'Kata sandi awal minimal 8 karakter',
        ]);

        $idPeran  = DB::table('peran')->where('nama_peran', $request->peran)->value('id_peran');
        $idBidang = BidangSesuaiPeran::tanpaBidang($request->peran)
            ? null
            : DB::table('bidang')->where('nama_bidang', $request->bidang)->value('id_bidang');

        DB::table('pengguna')->insert([
            'nama_lengkap'           => $request->nama,
            'nama_pengguna'          => $request->username,
            'kata_sandi_hash'        => Hash::make($request->password),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => 1,
            'id_peran'               => $idPeran,
            'id_bidang'              => $idBidang,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        Log::info('Admin membuat akun baru', [
            'admin_id'       => Auth::id(),
            'admin_username' => Auth::user()->nama_pengguna,
            'akun_baru'      => $request->username,
            'dicatat_pada'   => now()->toDateTimeString(),
        ]);

        return redirect()->route('admin.akun.index')
            ->with('sukses', 'Akun berhasil dibuat.');
    }

    public function resetPassword($id)
    {
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
}
