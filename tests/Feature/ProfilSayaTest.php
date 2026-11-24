<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T-045 (B-031): endpoint Profil Saya. Self-contained.
 */
class ProfilSayaTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $admin;
    private Bidang $bidangA;
    private Bidang $bidangB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bidangA = Bidang::firstOrCreate(['nama_bidang' => 'Bidang Uji A']);
        $this->bidangB = Bidang::firstOrCreate(['nama_bidang' => 'Bidang Uji B']);
        $this->admin = $this->buatAkun('Admin', 'admin_uji');
    }

    private function buatAkun(string $peran, string $username, ?int $idBidang = null): Pengguna
    {
        $p = Peran::firstOrCreate(['nama_peran' => $peran]);

        return Pengguna::create([
            'nama_lengkap'           => ucfirst(str_replace('_', ' ', $username)),
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => 'Rahasia#12345',
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $p->id_peran,
            'id_bidang'              => $idBidang,
        ]);
    }

    private function buatAnggota(Pengguna $akun, array $ekstra = []): int
    {
        return DB::table('anggota')->insertGetId(array_merge([
            'id_pengguna'   => $akun->id_pengguna,
            'nama_lengkap'  => 'Nama ' . $akun->nama_pengguna,
            'nomor_telepon' => '081234567890',
            'status'        => 'aktif',
            'dibuat_oleh'   => $this->admin->id_pengguna,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $ekstra));
    }

    private function profil(Pengguna $akun, string $url = '/profil-saya')
    {
        return $this->actingAs($akun)->get($url);
    }

    public function test_semua_peran_yang_punya_anggota_melihat_datanya_sendiri(): void
    {
        $peran = ['Admin', 'Penanggung Jawab', 'Pimpinan Bidang', 'Anggota Bidang', 'Bendahara', 'Penasihat'];

        foreach ($peran as $i => $nama) {
            $akun = $this->buatAkun($nama, 'user_' . $i);
            $this->buatAnggota($akun, ['nama_lengkap' => 'Ibu Peran ' . $i]);

            $this->profil($akun)
                ->assertOk()
                ->assertViewIs('profil-saya.index')
                ->assertViewHas('anggota', fn($a) => $a->nama_asli === 'Ibu Peran ' . $i);
        }
    }

    public function test_enam_belas_atribut_tampil_lengkap(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'lengkap', $this->bidangA->id_bidang);
        $unit = DB::table('unit_kerja')->insertGetId(['nama_unit_kerja' => 'Unit Uji']);

        $this->buatAnggota($akun, [
            'nama_panggilan'     => 'Ny. Ani',
            'tempat_lahir'       => 'Jakarta',
            'tanggal_lahir'      => '1980-08-17',
            'alamat_lengkap'     => 'Jl. Uji 1',
            'email'              => 'ani@example.test',
            'nama_suami'         => 'Budi',
            'id_unit_kerja'      => $unit,
            'tingkat_pendidikan' => 'S1 (Sarjana)',
            'bidang_pendidikan'  => 'Akuntansi',
            'keahlian'           => 'Menjahit',
            'bidang_usaha'       => 'Katering',
            'bekerja'            => 'Ya',
            'instansi_kerja'     => 'Instansi Uji',
            'id_bidang'          => $this->bidangA->id_bidang,
        ]);

        $a = $this->profil($akun)->viewData('anggota');

        $this->assertCount(16, (array) $a);
        $this->assertSame('Unit Uji', $a->unit_kerja_suami);
        $this->assertSame('Jakarta, 17 Agustus 1980', $a->tempat_tanggal_lahir);
        $this->assertSame('Bidang Uji A', $a->bidang_kepengurusan);
        $this->assertSame('Anggota Bidang', $a->posisi_kepengurusan);
        $this->assertSame('Instansi Uji', $a->instansi_tempat_bekerja);
        $this->assertSame('-', $a->foto);
    }

    public function test_id_milik_anggota_lain_diabaikan(): void
    {
        $saya = $this->buatAkun('Anggota Bidang', 'saya');
        $lain = $this->buatAkun('Anggota Bidang', 'orang_lain');
        $this->buatAnggota($saya, ['nama_lengkap' => 'Ibu Saya']);
        $idLain = $this->buatAnggota($lain, ['nama_lengkap' => 'Ibu Orang Lain']);

        $r = $this->profil($saya, '/profil-saya?id=' . $idLain . '&id_anggota=' . $idLain)
            ->assertOk()
            ->assertSee('Ibu Saya')
            ->assertDontSee('Ibu Orang Lain');

        $this->assertSame('Ibu Saya', $r->viewData('anggota')->nama_asli);
    }

    public function test_akun_belum_tertaut_mendapat_pesan_tanpa_galat(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'belum_tertaut');

        $this->profil($akun)
            ->assertOk()
            ->assertViewHas('anggota', null)
            ->assertSee('Data anggota belum tersedia, hubungi Admin');
    }

    public function test_anggota_soft_delete_dianggap_belum_tertaut(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'terhapus');
        $this->buatAnggota($akun, ['dihapus_pada' => now()]);

        $this->profil($akun)
            ->assertOk()
            ->assertViewHas('anggota', null)
            ->assertSee('Data anggota belum tersedia, hubungi Admin');
    }

    public function test_tanpa_sesi_diarahkan_ke_login(): void
    {
        $this->get('/profil-saya')->assertRedirect(route('login'));
    }

    public function test_respons_tidak_memuat_data_akun_atau_anggota_lain(): void
    {
        $saya = $this->buatAkun('Anggota Bidang', 'saya_aman');
        $lain = $this->buatAkun('Anggota Bidang', 'akun_rahasia_lain');
        $this->buatAnggota($saya, ['nama_lengkap' => 'Ibu Pemilik Profil']);
        $this->buatAnggota($lain, ['nama_lengkap' => 'Ibu Rahasia Lain', 'email' => 'rahasia@example.test']);

        $r = $this->profil($saya)->assertOk();

        $r->assertSee('Ibu Pemilik Profil')
        ->assertDontSee('akun_rahasia_lain')
        ->assertDontSee('Ibu Rahasia Lain')
        ->assertDontSee('rahasia@example.test')
        ->assertDontSee('kata_sandi')
        ->assertDontSee('Rahasia#12345')
        ->assertDontSee('saya_aman'); // nama_pengguna akun sendiri juga tidak ikut

        $this->assertArrayNotHasKey('id_pengguna', (array) $r->viewData('anggota'));
        $this->assertArrayNotHasKey('dibuat_oleh', (array) $r->viewData('anggota'));
    }

    public function test_field_kosong_tampil_strip_bukan_null(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'serba_kosong');
        $this->buatAnggota($akun);

        $r = $this->profil($akun)->assertOk()->assertDontSee('null');
        $a = (array) $r->viewData('anggota');

        foreach ($a as $kunci => $nilai) {
            if (in_array($kunci, ['nama_asli', 'posisi_kepengurusan'], true)) {
                continue;
            }
            $this->assertSame('-', $nilai, "Atribut {$kunci} harus '-'");
        }
    }

    public function test_tempat_tanggal_lahir_salah_satu_kosong(): void
    {
        $a1 = $this->buatAkun('Anggota Bidang', 'ttl_satu');
        $this->buatAnggota($a1, ['tempat_lahir' => 'Bandung']);
        $this->assertSame('Bandung', $this->profil($a1)->viewData('anggota')->tempat_tanggal_lahir);

        $a2 = $this->buatAkun('Anggota Bidang', 'ttl_dua');
        $this->buatAnggota($a2, ['tanggal_lahir' => '1975-01-05']);
        $this->assertSame('5 Januari 1975', $this->profil($a2)->viewData('anggota')->tempat_tanggal_lahir);
    }

    public function test_posisi_berdasarkan_peran_dan_jabatan(): void
    {
        $kasus = [
            ['Penasihat', 'p1', [], 'Penasihat'],
            ['Admin', 'p2', [], 'Sekretariat'],
            ['Bendahara', 'p3', [], 'Bendahara'],
            ['Pimpinan Bidang', 'p4', ['jabatan_bidang' => 'ketua'], 'Ketua Bidang'],
            ['Anggota Bidang', 'p5', [], 'Anggota Bidang'],
            ['Penanggung Jawab', 'p6', [], 'Penanggung Jawab Bidang'],
            ['Anggota Bidang', 'p7', ['jabatan_bidang' => 'wakil_ketua'], 'Wakil Ketua DWP'],
            ['Penanggung Jawab', 'p8', ['jabatan_bidang' => 'wakil_ketua'], 'Wakil Ketua DWP, Penanggung Jawab Bidang'],
        ];

        foreach ($kasus as [$peran, $username, $ekstra, $harapan]) {
            $akun = $this->buatAkun($peran, $username);
            $this->buatAnggota($akun, $ekstra);

            $this->assertSame($harapan, $this->profil($akun)->viewData('anggota')->posisi_kepengurusan, "Kasus {$username}");
        }
    }

    public function test_posisi_dari_jabatan_dwp(): void
    {
        $ketua = $this->buatAkun('Anggota Bidang', 'ketua_dwp');
        $idKetua = $this->buatAnggota($ketua);
        $wakil = $this->buatAkun('Anggota Bidang', 'wakil_dwp');
        $idWakil = $this->buatAnggota($wakil);

        DB::table('jabatan_dwp')->insert([
            ['nama_jabatan' => 'Ketua DWP', 'jenis' => 'ketua', 'urutan' => 1, 'id_anggota' => $idKetua, 'created_at' => now(), 'updated_at' => now()],
            ['nama_jabatan' => 'Wakil Ketua I', 'jenis' => 'wakil_ketua', 'urutan' => 1, 'id_anggota' => $idWakil, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame('Ketua DWP', $this->profil($ketua)->viewData('anggota')->posisi_kepengurusan);
        $this->assertSame('Wakil Ketua DWP', $this->profil($wakil)->viewData('anggota')->posisi_kepengurusan);    }

    public function test_penanggung_jawab_menampilkan_semua_bidangnya(): void
    {
        $pj = $this->buatAkun('Penanggung Jawab', 'pj_banyak');
        $this->buatAnggota($pj);

        foreach ([$this->bidangB, $this->bidangA] as $b) {
            DB::table('pengguna_bidang')->insert([
                'id_pengguna' => $pj->id_pengguna,
                'id_bidang'   => $b->id_bidang,
            ]);
        }

        $this->assertSame('Bidang Uji A, Bidang Uji B', $this->profil($pj)->viewData('anggota')->bidang_kepengurusan);
    }
}