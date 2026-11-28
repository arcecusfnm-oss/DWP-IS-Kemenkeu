<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Validation\Rule;

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
    private const TINGKAT_PENDIDIKAN = [
        'SMA/SMK', 'D3', 'D4', 'S1 (Sarjana)', 'S2 (Magister)', 'S3 (Doktoral)',
    ];

    // Sama dengan AnggotaController: tolak < > dan karakter kontrol
    private const POLA_TEKS = '/^[^<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*$/u';

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

    public function update(Request $request)
    {
        $teks = 'regex:' . self::POLA_TEKS;

        // Hanya kolom yang boleh diubah sendiri. Bidang, posisi, nama asli, nomor telepon,
        // dan id apa pun yang dikirim manual tidak ikut di validated() (AC 3 dan 4).
        $data = $request->validate([
            'nama_suami'         => ['nullable', 'string', 'max:255', $teks],
            'nama_panggilan'     => ['nullable', 'string', 'max:255', $teks],
            'alamat_lengkap'     => ['nullable', 'string', 'max:1000', $teks],
            'email'              => ['nullable', 'email', 'max:255'],
            'tingkat_pendidikan' => ['nullable', Rule::in(self::TINGKAT_PENDIDIKAN)],
            'bidang_pendidikan'  => ['nullable', 'string', 'max:255', $teks],
            'keahlian'           => ['nullable', 'string', 'max:1000', $teks],
            'bidang_usaha'       => ['nullable', 'string', 'max:255', $teks],
            'bekerja'            => ['nullable', Rule::in(['Ya', 'Tidak'])],
            'instansi_kerja'     => ['nullable', 'string', 'max:255', $teks],
            'foto'               => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ], $this->pesanUbah());

        unset($data['foto']);

        if (array_key_exists('nama_panggilan', $data)) {
            $data['nama_panggilan'] = $this->rapikanPanggilan($data['nama_panggilan']);
        }

        // Tidak bekerja: instansi dikosongkan di server
        if (($data['bekerja'] ?? null) === 'Tidak') {
            $data['instansi_kerja'] = null;
        }

        $fotoBaru = $request->hasFile('foto')
            ? $request->file('foto')->store('foto-anggota', 'local')
            : null;

        try {
            $hasil = DB::transaction(function () use ($data, $fotoBaru) {
                // Anggota selalu dari akun yang masuk, bukan dari request
                $anggota = DB::table('anggota')
                    ->where('id_pengguna', Auth::id())
                    ->whereNull('dihapus_pada')
                    ->lockForUpdate()
                    ->first();

                if (! $anggota) {
                    return null;
                }

                $perubahan = [];
                foreach ($data as $kolom => $baru) {
                    if ((string) ($anggota->{$kolom} ?? '') !== (string) ($baru ?? '')) {
                        $perubahan[] = $kolom;
                    }
                }

                $isi = $data;
                if ($fotoBaru) {
                    $isi['foto_path'] = $fotoBaru;
                    $perubahan[] = 'foto';
                }

                if (! empty($perubahan)) {
                    DB::table('anggota')
                        ->where('id_anggota', $anggota->id_anggota)
                        ->update(array_merge($isi, ['updated_at' => now()]));
                }

                return [
                    'id_anggota' => $anggota->id_anggota,
                    'foto_lama'  => $anggota->foto_path,
                    'perubahan'  => $perubahan,
                ];
            });
        } catch (QueryException $e) {
            report($e); // detail hanya masuk log server
            if ($fotoBaru) {
                Storage::disk('local')->delete($fotoBaru);
            }

            return back()->withInput()
                ->with('error', 'Profil gagal diperbarui. Silakan coba lagi.');
        }

        if ($hasil === null) {
            if ($fotoBaru) {
                Storage::disk('local')->delete($fotoBaru);
            }

            return back()->with('error', 'Data anggota belum tersedia, hubungi Admin');
        }

        // Foto lama dihapus setelah update berhasil
        if ($fotoBaru && $hasil['foto_lama']) {
            Storage::disk('local')->delete($hasil['foto_lama']);
        }

        // AC 5: waktu, pelaku, daftar kolom saja, tanpa isi data
        if (! empty($hasil['perubahan'])) {
            Log::info('Anggota mengubah profil sendiri', [
                'pengguna_id'  => Auth::id(),
                'anggota_id'   => $hasil['id_anggota'],
                'kolom_diubah' => $hasil['perubahan'],
                'dicatat_pada' => now()->toDateTimeString(),
            ]);
        }

        return redirect()->route('profil-saya')->with('success', 'Profil berhasil diperbarui.');
    }

    // Sama dengan AnggotaController: nama panggilan selalu berawalan "Ny."
    private function rapikanPanggilan(?string $nama): ?string
    {
        $nama = trim((string) preg_replace('/\s+/u', ' ', (string) $nama));
        $nama = preg_replace('/^(nyonya|ny)(\.\s*|\s+)/iu', '', $nama);

        return $nama === '' ? null : 'Ny. ' . $nama;
    }

    private function pesanUbah(): array
    {
        $pesanTeks = 'Mengandung karakter yang tidak diperbolehkan.';

        return [
            'email.email'             => 'Format email tidak valid.',
            'tingkat_pendidikan.in'   => 'Tingkat pendidikan tidak valid.',
            'bekerja.in'              => 'Pilihan bekerja harus Ya atau Tidak.',
            'foto.file'               => 'Foto tidak valid.',
            'foto.image'              => 'Berkas harus berupa gambar.',
            'foto.mimes'              => 'Foto harus berformat JPG atau PNG.',
            'foto.max'                => 'Ukuran foto maksimal 2 MB.',
            'foto.uploaded'           => 'Foto gagal diunggah. Silakan coba lagi.',
            'nama_suami.regex'        => $pesanTeks,
            'nama_panggilan.regex'    => $pesanTeks,
            'alamat_lengkap.regex'    => $pesanTeks,
            'bidang_pendidikan.regex' => $pesanTeks,
            'keahlian.regex'          => $pesanTeks,
            'bidang_usaha.regex'      => $pesanTeks,
            'instansi_kerja.regex'    => $pesanTeks,
        ];
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