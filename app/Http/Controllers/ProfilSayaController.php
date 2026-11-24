<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

/**
 * B-031 T-045: Profil Saya (read-only).
 * Anggota selalu diambil dari akun yang masuk (Auth::id()), tidak ada ID dari request.
 * Select whitelist: id_pengguna, dibuat_oleh, kata sandi, dan data akun tidak ikut ke view.
 */
class ProfilSayaController extends Controller
{

    private const BULAN = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    public function index()
    {
        $a = DB::table('anggota')
            ->leftJoin('unit_kerja', 'unit_kerja.id_unit_kerja', '=', 'anggota.id_unit_kerja')
            ->leftJoin('pengguna', 'pengguna.id_pengguna', '=', 'anggota.id_pengguna')
            ->leftJoin('peran', 'peran.id_peran', '=', 'pengguna.id_peran')
            ->where('anggota.id_pengguna', Auth::id())
            ->whereNull('anggota.dihapus_pada')
            ->select(
                'anggota.id_anggota',
                'anggota.id_pengguna',
                'anggota.id_bidang',
                'anggota.jabatan_bidang',
                'anggota.nama_lengkap',
                'anggota.nama_suami',
                'anggota.nama_panggilan',
                'anggota.tempat_lahir',
                'anggota.tanggal_lahir',
                'anggota.alamat_lengkap',
                'anggota.email',
                'anggota.tingkat_pendidikan',
                'anggota.bidang_pendidikan',
                'anggota.keahlian',
                'anggota.bidang_usaha',
                'anggota.bekerja',
                'anggota.instansi_kerja',
                'anggota.foto_path',
                'unit_kerja.nama_unit_kerja',
                'peran.nama_peran'
            )
            ->first();

        // AC3: belum tertaut (atau anggota sudah soft delete) -> view menampilkan pesan, status 200
        if (! $a) {
            return view('profil-saya.index', ['anggota' => null]);
        }

        [$posisi, $adalahWakil] = $this->posisi($a);

        return view('profil-saya.index', [
            'anggota' => (object) [
                'nama_asli'              => $this->isi($a->nama_lengkap),
                'unit_kerja_suami'       => $this->isi($a->nama_unit_kerja),
                'nama_suami'             => $this->isi($a->nama_suami),
                'nama_panggilan'         => $this->isi($a->nama_panggilan),
                'tempat_tanggal_lahir'   => $this->tempatTanggalLahir($a->tempat_lahir, $a->tanggal_lahir),
                'alamat_lengkap'         => $this->isi($a->alamat_lengkap),
                'email'                  => $this->isi($a->email),
                'tingkat_pendidikan'     => $this->isi($a->tingkat_pendidikan),
                'bidang_pendidikan'      => $this->isi($a->bidang_pendidikan),
                'keahlian'               => $this->isi($a->keahlian),
                'bidang_usaha'           => $this->isi($a->bidang_usaha),
                'bekerja'                => $this->isi($a->bekerja),
                'instansi_tempat_bekerja' => $this->isi($a->instansi_kerja),
                'foto' => $a->foto_path
                    ? route('profil-saya.foto', ['v' => crc32($a->foto_path)]) // v = cache buster saat foto diganti
                    : '-',
                'posisi_kepengurusan'    => $posisi,
                'bidang_kepengurusan'    => $this->bidang($a, $adalahWakil),
            ],
        ]);
    }

    public function simpanFoto(Request $request)
    {
        $request->validate([
            'foto' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], [
            'foto.required' => 'Foto wajib dipilih.',
            'foto.file'     => 'Foto tidak valid.',
            'foto.image'    => 'Berkas harus berupa gambar.',
            'foto.mimes'    => 'Foto harus berformat JPG atau PNG.',
            'foto.max'      => 'Ukuran foto maksimal 2 MB.',
            'foto.uploaded' => 'Foto gagal diunggah. Silakan coba lagi.',
        ]);

        $anggota = $this->anggotaSaya();

        if (! $anggota) {
            return back()->with('error', 'Data anggota belum tersedia, hubungi Admin');
        }

        $pathBaru = $request->file('foto')->store('foto-anggota', 'local');

        if (! $pathBaru) {
            return back()->with('error', 'Foto belum bisa disimpan. Silakan coba lagi.');
        }

        try {
            DB::table('anggota')
                ->where('id_anggota', $anggota->id_anggota)
                ->update(['foto_path' => $pathBaru, 'updated_at' => now()]);
        } catch (QueryException $e) {
            report($e);
            Storage::disk('local')->delete($pathBaru);

            return back()->with('error', 'Foto belum bisa disimpan. Silakan coba lagi.');
        }

        if ($anggota->foto_path) {
            Storage::disk('local')->delete($anggota->foto_path);
        }

        Log::info('Anggota mengubah foto profil', [
            'pengguna_id'  => Auth::id(),
            'anggota_id'   => $anggota->id_anggota,
            'dicatat_pada' => now()->toDateTimeString(),
        ]);

        return redirect()->route('profil-saya')->with('success', 'Foto berhasil disimpan.');
    }

    public function foto()
    {
        $anggota = $this->anggotaSaya();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        if (! $anggota || ! $anggota->foto_path || ! $disk->exists($anggota->foto_path)) {
            abort(404);
        }

        return $disk->response($anggota->foto_path);
    }

    // Anggota milik akun yang masuk; soft delete dianggap belum tertaut.
    private function anggotaSaya(): ?object
    {
        return DB::table('anggota')
            ->where('id_pengguna', Auth::id())
            ->whereNull('dihapus_pada')
            ->first(['id_anggota', 'foto_path']);
    }

    // Mengembalikan [label posisi, apakah termasuk Wakil Ketua/PJ (bidang bisa lebih dari satu)].
    private function posisi(object $a): array
    {
        $jenisDwp = DB::table('jabatan_dwp')
            ->where('id_anggota', $a->id_anggota)
            ->value('jenis');

        if ($jenisDwp === 'ketua') {
            return ['Ketua DWP', false];
        }

        $wakilDwp = $jenisDwp === 'wakil_ketua' || $a->jabatan_bidang === 'wakil_ketua';
        $pj       = $a->nama_peran === 'Penanggung Jawab';

        if ($wakilDwp || $pj) {
            $label = array_filter([
                $wakilDwp ? 'Wakil Ketua DWP' : null,
                $pj ? 'Penanggung Jawab Bidang' : null,
            ]);

            return [implode(', ', $label), true];
        }

        return match (true) {
            $a->nama_peran === 'Penasihat' => ['Penasihat', false],
            $a->nama_peran === 'Admin'     => [config('dwp.peran_bidang_tetap.Admin.0', 'Sekretariat'), false],
            $a->nama_peran === 'Bendahara' => [config('dwp.peran_bidang_tetap.Bendahara.0', 'Bendahara'), false],
            $a->jabatan_bidang === 'ketua' => ['Ketua Bidang', false],
            default                        => ['Anggota Bidang', false],
        };
    }

    private function bidang(object $a, bool $adalahWakil): string
    {
        if ($adalahWakil && $a->id_pengguna !== null) {
            $daftar = DB::table('pengguna_bidang')
                ->join('bidang', 'bidang.id_bidang', '=', 'pengguna_bidang.id_bidang')
                ->where('pengguna_bidang.id_pengguna', $a->id_pengguna)
                ->orderBy('bidang.nama_bidang')
                ->pluck('bidang.nama_bidang')
                ->all();

            if (! empty($daftar)) {
                return implode(', ', $daftar);
            }
        }

        if ($a->id_bidang === null) {
            return '-';
        }

        return $this->isi(DB::table('bidang')->where('id_bidang', $a->id_bidang)->value('nama_bidang'));
    }

    private function tempatTanggalLahir($tempat, $tanggal): string
    {
        $tempat = trim((string) $tempat);
        $teksTanggal = '';

        if ($tanggal) {
            [$y, $m, $d] = array_map('intval', explode('-', substr((string) $tanggal, 0, 10)));
            $teksTanggal = $d . ' ' . self::BULAN[$m] . ' ' . $y;
        }

        $bagian = array_filter([$tempat, $teksTanggal], fn($v) => $v !== '');

        return $bagian ? implode(', ', $bagian) : '-';
    }

    // AC5: kosong/null jadi "-"
    private function isi($nilai): string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? '-' : $nilai;
    }
}