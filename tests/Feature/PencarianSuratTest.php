<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T-74 / B-039: pencarian surat (AC1-AC5).
 *
 * Mandiri: semua peran, bidang, akun, dan surat dibuat di dalam test, tidak
 * bergantung pada seeder, urutan test, atau sisa data test lain.
 * DatabaseTransactions: semua data dibatalkan setelah tiap test.
 *
 * Asumsi lingkungan: MySQL dengan collation tabel *_ci (AC1) dan escape LIKE
 * memakai backslash (AC4).
 */
class PencarianSuratTest extends TestCase
{
    use DatabaseTransactions;

    private ?Pengguna $admin = null;
    private int $urut = 0;
    private static ?string $hash = null;

    // ---------------------------------------------------------------- setup

    private function peran(string $nama): Peran
    {
        return Peran::firstOrCreate(['nama_peran' => $nama]);
    }

    private function bidang(string $nama): Bidang
    {
        return Bidang::firstOrCreate(['nama_bidang' => $nama]);
    }

    private function akun(string $namaPeran, ?Bidang $bidang = null): Pengguna
    {
        self::$hash ??= Hash::make('Rahasia#123');

        $p = new Pengguna();
        $p->nama_lengkap = 'Uji ' . $namaPeran;
        $p->nama_pengguna = 'uji_' . uniqid();
        $p->kata_sandi_hash = self::$hash;
        $p->status = 'aktif';
        $p->perlu_ganti_kata_sandi = false;
        $p->id_peran = $this->peran($namaPeran)->id_peran;
        $p->id_bidang = $bidang?->id_bidang;
        $p->save();

        return $p;
    }

    private function admin(): Pengguna
    {
        return $this->admin ??= $this->akun('Admin');
    }

    private function nomor(string $awalan): string
    {
        return $awalan . '-' . (++$this->urut) . '-' . uniqid();
    }

    private function keluar(string $tanggal, string $perihal): string
    {
        $nomor = $this->nomor('K');
        DB::table('surat_keluar')->insert([
            'nomor_surat' => $nomor,
            'tanggal'     => $tanggal,
            'tujuan'      => 'Tujuan Uji',
            'perihal'     => $perihal,
            'dibuat_oleh' => $this->admin()->id_pengguna,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return $nomor;
    }

    private function masuk(string $tanggal, string $perihal): string
    {
        $nomor = $this->nomor('M');
        DB::table('surat_masuk')->insert([
            'nomor_agenda'     => $this->urut . '/XI/2031/' . uniqid(),
            'nomor_surat'      => $nomor,
            'asal_surat'       => 'Asal Uji',
            'tanggal_surat'    => $tanggal,
            'tanggal_diterima' => $tanggal,
            'lampiran'         => '1 berkas',
            'perihal'          => $perihal,
            'dibuat_oleh'      => $this->admin()->id_pengguna,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return $nomor;
    }

    private function keputusan(string $tanggal, string $perihal): string
    {
        $nomor = $this->nomor('SK');
        DB::table('surat_keputusan')->insert([
            'nomor_sk'    => $nomor,
            'tanggal'     => $tanggal,
            'perihal'     => $perihal,
            'dibuat_oleh' => $this->admin()->id_pengguna,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return $nomor;
    }

    private function cari(array $query, ?Pengguna $sebagai = null)
    {
        return $this->actingAs($sebagai ?? $this->admin())
            ->getJson('/surat?' . http_build_query($query));
    }

    /** Daftar nomor_surat pada halaman hasil, terurut (maks 10 baris per halaman). */
    private function nomorHasil($response): array
    {
        $nomor = collect($response->json('surat.data'))->pluck('nomor_surat')->all();
        sort($nomor);

        return $nomor;
    }

    private function diurutkan(array $nomor): array
    {
        sort($nomor);

        return $nomor;
    }

    // ------------------------------------------------- AC1: huruf besar/kecil

    public function test_kata_kunci_tidak_membedakan_huruf_besar_kecil(): void
    {
        $a = $this->keluar('2031-03-01', 'UNDANGAN zzcase');
        $b = $this->keluar('2031-03-01', 'rapat koordinasi zzcase');
        $this->keluar('2031-03-01', 'perihal lain zzcase');

        // disimpan HURUF BESAR, dicari huruf kecil
        $r = $this->cari(['q' => 'undangan zzcase'])->assertOk();
        $this->assertSame([$a], $this->nomorHasil($r));

        // disimpan huruf kecil, dicari HURUF BESAR
        $r = $this->cari(['q' => 'RAPAT KOORDINASI ZZCASE'])->assertOk();
        $this->assertSame([$b], $this->nomorHasil($r));
    }

    public function test_kata_kunci_cocok_sebagian_kata_dan_hanya_pada_perihal(): void
    {
        $a = $this->keluar('2031-03-02', 'Permohonan zzsub data');
        $this->keluar('2031-03-02', 'Tidak relevan');

        $r = $this->cari(['q' => 'mohon zzsub'])->assertOk();
        $this->assertSame([], $this->nomorHasil($r)); // "mohon zzsub" tidak berurutan: bukan substring

        $r = $this->cari(['q' => 'ermohonan zzsub'])->assertOk();
        $this->assertSame([$a], $this->nomorHasil($r));
    }

    // ------------------------------------------------------ AC2: rentang tanggal

    public function test_rentang_tanggal_inklusif_dan_bisa_dipadukan_kata_kunci(): void
    {
        $awal  = $this->keluar('2031-01-01', 'zzrange awal');
        $akhir = $this->keluar('2031-01-31', 'zzrange akhir');
        $this->keluar('2030-12-31', 'zzrange sebelum');
        $this->keluar('2031-02-01', 'zzrange sesudah');

        $r = $this->cari(['q' => 'zzrange', 'dari' => '2031-01-01', 'sampai' => '2031-01-31'])->assertOk();
        $this->assertSame($this->diurutkan([$awal, $akhir]), $this->nomorHasil($r));

        $r = $this->cari(['q' => 'zzrange akhir', 'dari' => '2031-01-01', 'sampai' => '2031-01-31'])->assertOk();
        $this->assertSame([$akhir], $this->nomorHasil($r));
    }

    public function test_rentang_tanggal_berlaku_untuk_ketiga_jenis_surat(): void
    {
        $dalam = [];
        foreach (['keluar', 'masuk', 'keputusan'] as $jenis) {
            $this->$jenis('2031-05-31', 'zzbatas luar');
            $dalam[] = $this->$jenis('2031-06-01', 'zzbatas dalam');
            $dalam[] = $this->$jenis('2031-06-30', 'zzbatas dalam');
            $this->$jenis('2031-07-01', 'zzbatas luar');
        }

        // batas awal dan akhir termasuk, di tiap tabel (kolom tanggal berbeda-beda)
        $r = $this->cari(['q' => 'zzbatas', 'dari' => '2031-06-01', 'sampai' => '2031-06-30'])->assertOk();
        $this->assertSame($this->diurutkan($dalam), $this->nomorHasil($r));

        // hanya batas awal: 06-30 dan 07-01 di tiga tabel
        $this->cari(['q' => 'zzbatas', 'dari' => '2031-06-30'])
            ->assertOk()->assertJsonPath('surat.total', 6);

        // hanya batas akhir: 05-31 dan 06-01 di tiga tabel
        $this->cari(['q' => 'zzbatas', 'sampai' => '2031-06-01'])
            ->assertOk()->assertJsonPath('surat.total', 6);

        // tanggal awal = tanggal akhir diterima: satu hari penuh
        $this->cari(['q' => 'zzbatas', 'dari' => '2031-06-01', 'sampai' => '2031-06-01'])
            ->assertOk()->assertJsonPath('surat.total', 3);
    }

    // ------------------------------------------------------ jenis surat

    public function test_surat_masuk_dan_keputusan_ikut_dicari(): void
    {
        $m = $this->masuk('2031-04-01', 'zzsemua masuk');
        $s = $this->keputusan('2031-04-03', 'zzsemua keputusan');
        $k = $this->keluar('2031-04-05', 'zzsemua keluar');

        $r = $this->cari(['q' => 'zzsemua'])->assertOk();
        $this->assertSame($this->diurutkan([$m, $s, $k]), $this->nomorHasil($r));

        $r = $this->cari(['q' => 'zzsemua', 'jenis' => 'keputusan'])->assertOk();
        $this->assertSame([$s], $this->nomorHasil($r));
        $r = $this->cari(['q' => 'zzsemua', 'jenis' => 'masuk'])->assertOk();
        $this->assertSame([$m], $this->nomorHasil($r));
        $r = $this->cari(['q' => 'zzsemua', 'jenis' => 'keluar'])->assertOk();
        $this->assertSame([$k], $this->nomorHasil($r));
    }

    // ------------------------------------------------------ AC3: pesan & rentang terbalik

    public function test_tanpa_hasil_menampilkan_pesan(): void
    {
        $this->cari(['q' => 'zztidakadasama'])
            ->assertOk()
            ->assertJsonPath('surat.total', 0)
            ->assertJsonPath('pesan', 'Data tidak ditemukan');
    }

    public function test_pesan_kosong_tidak_muncul_bila_ada_hasil(): void
    {
        $this->keluar('2031-03-03', 'zzada hasil');

        $this->cari(['q' => 'zzada hasil'])
            ->assertOk()
            ->assertJsonPath('surat.total', 1)
            ->assertJsonPath('pesan', null);
    }

    public function test_rentang_terbalik_ditolak_dengan_pesan_jelas(): void
    {
        $this->cari(['dari' => '2031-12-31', 'sampai' => '2031-01-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sampai'])
            ->assertJsonPath('errors.sampai.0', 'Tanggal akhir tidak boleh sebelum tanggal awal');
    }

    // ------------------------------------------------------ AC4: karakter khusus

    public function test_persen_underscore_dan_kutip_diperlakukan_sebagai_teks_biasa(): void
    {
        $pct   = $this->keluar('2031-05-01', 'Diskon 100% zzpct');
        $this->keluar('2031-05-01', 'Diskon 100X zzpct');
        $und   = $this->keluar('2031-05-01', 'kode_A zzund');
        $this->keluar('2031-05-01', 'kodeXA zzund');
        $kutip = $this->keluar('2031-05-01', "Surat 'Penting' zzq");

        $this->assertSame([$pct], $this->nomorHasil($this->cari(['q' => '100% zzpct'])->assertOk()));
        $this->assertSame([$und], $this->nomorHasil($this->cari(['q' => 'kode_A zzund'])->assertOk()));
        $this->assertSame([$kutip], $this->nomorHasil($this->cari(['q' => "'Penting' zzq"])->assertOk()));
    }

    public function test_backslash_diperlakukan_sebagai_teks_biasa(): void
    {
        $bs = $this->keluar('2031-05-02', 'Lampiran C:\\temp zzbs');
        $this->keluar('2031-05-02', 'Lampiran C:Xtemp zzbs');

        $r = $this->cari(['q' => 'C:\\temp zzbs'])->assertOk();
        $this->assertSame([$bs], $this->nomorHasil($r));
    }

    public function test_percobaan_sql_injection_tidak_error_dan_tidak_mengembalikan_semua_data(): void
    {
        $this->keluar('2031-05-03', 'zzinj aman');

        foreach (["x' OR '1'='1", "'; DROP TABLE surat_keluar; --", '" OR ""="'] as $q) {
            $this->cari(['q' => $q . ' zzinj'])
                ->assertOk()
                ->assertJsonPath('surat.total', 0);
        }

        // tabel masih utuh dan data uji masih bisa dicari
        $this->cari(['q' => 'zzinj aman'])->assertOk()->assertJsonPath('surat.total', 1);
    }

    // ------------------------------------------------------ AC5: hak akses

    public function test_admin_boleh_mencari(): void
    {
        $this->actingAs($this->admin())->getJson('/surat')->assertOk();
    }

    public function test_akun_berbidang_sekretariat_langsung_lewat_id_bidang_boleh(): void
    {
        $akun = $this->akun('Anggota Bidang', $this->bidang('Sekretariat'));

        $this->actingAs($akun)->getJson('/surat')->assertOk();
    }

    public function test_akun_berbidang_sekretariat_lewat_pivot_boleh(): void
    {
        $lain  = $this->bidang('Bidang Uji Lain');
        $akun  = $this->akun('Anggota Bidang', $lain); // id_bidang BUKAN Sekretariat

        DB::table('pengguna_bidang')->insert([
            'id_pengguna' => $akun->id_pengguna,
            'id_bidang'   => $this->bidang('Sekretariat')->id_bidang,
        ]);

        $this->actingAs($akun)->getJson('/surat')->assertOk();
    }

    public function test_akun_bidang_lain_bukan_admin_ditolak_403(): void
    {
        $this->bidang('Sekretariat'); // bidang Sekretariat ada, tetapi akun ini bukan anggotanya
        $akun = $this->akun('Anggota Bidang', $this->bidang('Bidang Uji Lain'));

        $this->actingAs($akun)->getJson('/surat')->assertForbidden();
    }

    public function test_akun_bidang_lain_lewat_pivot_saja_ditolak_403(): void
    {
        $this->bidang('Sekretariat');
        $lain = $this->bidang('Bidang Uji Lain');
        $akun = $this->akun('Anggota Bidang');

        DB::table('pengguna_bidang')->insert([
            'id_pengguna' => $akun->id_pengguna,
            'id_bidang'   => $lain->id_bidang,
        ]);

        $this->actingAs($akun)->getJson('/surat')->assertForbidden();
    }

    public function test_akun_tanpa_bidang_bukan_admin_ditolak_403(): void
    {
        $this->bidang('Sekretariat');
        $akun = $this->akun('Anggota Bidang');

        $this->actingAs($akun)->getJson('/surat')->assertForbidden();
    }

    public function test_tanpa_sesi_ditolak(): void
    {
        $this->getJson('/surat')->assertUnauthorized();
        $this->get('/surat')->assertRedirect(route('login'));
    }
}