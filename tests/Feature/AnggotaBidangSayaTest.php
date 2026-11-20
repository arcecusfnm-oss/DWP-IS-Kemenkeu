<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-54 (B-022): unit test daftar anggota bidang saya (endpoint T-52).
 *
 * Skenario: bidang sendiri (AC 1), bidang lain (AC 2), tanpa sesi (AC 4).
 * Tambahan: bidang tanpa anggota (AC 3) dan pembatasan peran.
 *
 * Mandiri: peran, bidang, akun, dan anggota dibuat di dalam test.
 * RefreshDatabase, pola yang sama dengan DaftarAnggotaTest (T-44).
 */
class AnggotaBidangSayaTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_KOSONG = 'Tidak ada anggota yang sesuai';

    // ---------------------------------------------------------------- setup

    private function buatAkun(string $namaPeran, ?int $idBidang): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $namaPeran]);
        $username = 'uji_' . uniqid();

        $id = DB::table('pengguna')->insertGetId([
            'nama_lengkap'           => 'Akun ' . $username,
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => Hash::make('Rahasia123!'),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => 0,
            'id_peran'               => $peran->id_peran,
            'id_bidang'              => $idBidang,
            'created_at'             => now(),
            'updated_at'             => now(),
        ], 'id_pengguna');

        return Pengguna::find($id);
    }

    private function bidang(string $nama): Bidang
    {
        return Bidang::firstOrCreate(['nama_bidang' => $nama]);
    }

    private function buatAnggota(string $nama, ?int $idBidang, int $dibuatOleh, array $tambahan = []): int
    {
        return DB::table('anggota')->insertGetId(array_merge([
            'nama_lengkap'  => $nama,
            'nomor_telepon' => '081234567890',
            'id_bidang'     => $idBidang,
            'status'        => 'aktif',
            'dibuat_oleh'   => $dibuatOleh,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $tambahan), 'id_anggota');
    }

    private function nama($respons): array
    {
        return collect($respons->json('anggota.data'))->pluck('nama_lengkap')->all();
    }

    // ------------------------------------------------- AC 1: bidang sendiri

    #[DataProvider('peranBerhak')]
    public function test_akun_hanya_melihat_anggota_bidangnya_sendiri(string $namaPeran): void
    {
        $ekonomi  = $this->bidang('Ekonomi');
        $pendidik = $this->bidang('Pendidikan');
        $akun     = $this->buatAkun($namaPeran, $ekonomi->id_bidang);

        $this->buatAnggota('Ani Ekonomi', $ekonomi->id_bidang, $akun->id_pengguna);
        $this->buatAnggota('Dewi Ekonomi', $ekonomi->id_bidang, $akun->id_pengguna);
        $this->buatAnggota('Budi Pendidikan', $pendidik->id_bidang, $akun->id_pengguna);
        $this->buatAnggota('Citra Tanpa Bidang', null, $akun->id_pengguna);

        $respons = $this->actingAs($akun)->getJson(route('anggota.bidang-saya'));

        $respons->assertOk()
            ->assertJsonPath('bidang', 'Ekonomi')
            ->assertJsonPath('anggota.total', 2)
            ->assertJsonPath('pesan', null);
        $this->assertEqualsCanonicalizing(['Ani Ekonomi', 'Dewi Ekonomi'], $this->nama($respons));
    }

    public function test_respons_hanya_memuat_nama_jabatan_dan_nomor_telepon(): void
    {
        $ekonomi = $this->bidang('Ekonomi');
        $akun    = $this->buatAkun('Anggota Bidang', $ekonomi->id_bidang);
        $this->buatAnggota('Ani', $ekonomi->id_bidang, $akun->id_pengguna, [
            'email'          => 'ani@uji.id',
            'alamat_lengkap' => 'Jl. Rahasia 1',
        ]);

        $respons = $this->actingAs($akun)->getJson(route('anggota.bidang-saya'));
        $baris   = $respons->json('anggota.data.0');

        $this->assertEqualsCanonicalizing(
            ['id_anggota', 'nama_lengkap', 'jabatan_bidang', 'nomor_telepon'],
            array_keys($baris)
        );
        $this->assertSame('081234567890', $baris['nomor_telepon']);
        $this->assertStringNotContainsString('kata_sandi', $respons->getContent());
        $this->assertStringNotContainsString('ani@uji.id', $respons->getContent());
        $this->assertStringNotContainsString('Jl. Rahasia 1', $respons->getContent());
    }

    public function test_urutan_ketua_lalu_wakil_ketua_lalu_anggota_menurut_nama(): void
    {
        $ekonomi = $this->bidang('Ekonomi');
        $akun    = $this->buatAkun('Pimpinan Bidang', $ekonomi->id_bidang);

        $this->buatAnggota('Zahra', $ekonomi->id_bidang, $akun->id_pengguna);
        $this->buatAnggota('Wati Wakil', $ekonomi->id_bidang, $akun->id_pengguna, ['jabatan_bidang' => 'wakil_ketua']);
        $this->buatAnggota('Ani', $ekonomi->id_bidang, $akun->id_pengguna);
        $this->buatAnggota('Kiki Ketua', $ekonomi->id_bidang, $akun->id_pengguna, ['jabatan_bidang' => 'ketua']);

        $respons = $this->actingAs($akun)->getJson(route('anggota.bidang-saya'));

        $this->assertSame(['Kiki Ketua', 'Wati Wakil', 'Ani', 'Zahra'], $this->nama($respons));
        $this->assertSame('ketua', $respons->json('anggota.data.0.jabatan_bidang'));
        $this->assertSame('wakil_ketua', $respons->json('anggota.data.1.jabatan_bidang'));
    }

    public function test_anggota_yang_sudah_dihapus_tidak_ikut_ditampilkan(): void
    {
        $ekonomi = $this->bidang('Ekonomi');
        $akun    = $this->buatAkun('Anggota Bidang', $ekonomi->id_bidang);

        $this->buatAnggota('Ani', $ekonomi->id_bidang, $akun->id_pengguna);
        $this->buatAnggota('Sudah Dihapus', $ekonomi->id_bidang, $akun->id_pengguna, ['dihapus_pada' => now()]);

        $respons = $this->actingAs($akun)->getJson(route('anggota.bidang-saya'));

        $this->assertSame(['Ani'], $this->nama($respons));
    }

    public function test_halaman_menampilkan_anggota_bidang_sendiri_saja(): void
    {
        $ekonomi  = $this->bidang('Ekonomi');
        $pendidik = $this->bidang('Pendidikan');
        $akun     = $this->buatAkun('Pimpinan Bidang', $ekonomi->id_bidang);

        $this->buatAnggota('Kiki Ketua', $ekonomi->id_bidang, $akun->id_pengguna, [
            'jabatan_bidang' => 'ketua',
            'nomor_telepon'  => '08111222333',
        ]);
        $this->buatAnggota('Budi Pendidikan', $pendidik->id_bidang, $akun->id_pengguna);

        $this->actingAs($akun)
            ->get(route('anggota.bidang-saya'))
            ->assertOk()
            ->assertSee('Anggota Bidang Ekonomi')
            ->assertSee('Kiki Ketua')
            ->assertSee('08111222333')
            ->assertSee('Ketua Bidang')
            ->assertDontSee('Budi Pendidikan');
    }

    // ------------------------------------------------- AC 2: bidang lain

    public function test_permintaan_dengan_id_bidang_lain_ditolak_403_dan_data_tidak_bocor(): void
    {
        $ekonomi  = $this->bidang('Ekonomi');
        $pendidik = $this->bidang('Pendidikan');
        $akun     = $this->buatAkun('Pimpinan Bidang', $ekonomi->id_bidang);
        $this->buatAnggota('Budi Pendidikan', $pendidik->id_bidang, $akun->id_pengguna);

        $respons = $this->actingAs($akun)
            ->getJson(route('anggota.bidang-saya', ['bidang' => $pendidik->id_bidang]));

        $respons->assertForbidden();
        $this->assertStringNotContainsString('Budi Pendidikan', $respons->getContent());
    }

    public function test_permintaan_halaman_dengan_id_bidang_lain_ditolak_403(): void
    {
        $ekonomi  = $this->bidang('Ekonomi');
        $pendidik = $this->bidang('Pendidikan');
        $akun     = $this->buatAkun('Anggota Bidang', $ekonomi->id_bidang);

        $this->actingAs($akun)
            ->get(route('anggota.bidang-saya', ['bidang' => $pendidik->id_bidang]))
            ->assertForbidden();
    }

    #[DataProvider('bidangTidakValid')]
    public function test_nilai_bidang_tidak_valid_ditolak_403(string $query): void
    {
        $ekonomi = $this->bidang('Ekonomi');
        $akun    = $this->buatAkun('Anggota Bidang', $ekonomi->id_bidang);

        $this->actingAs($akun)
            ->getJson(route('anggota.bidang-saya') . '?' . $query)
            ->assertForbidden();
    }

    public function test_id_bidang_milik_sendiri_pada_request_tetap_diterima(): void
    {
        $ekonomi = $this->bidang('Ekonomi');
        $akun    = $this->buatAkun('Anggota Bidang', $ekonomi->id_bidang);
        $this->buatAnggota('Ani', $ekonomi->id_bidang, $akun->id_pengguna);

        $respons = $this->actingAs($akun)
            ->getJson(route('anggota.bidang-saya', ['bidang' => $ekonomi->id_bidang]));

        $respons->assertOk();
        $this->assertSame(['Ani'], $this->nama($respons));
    }

    public function test_akun_tanpa_bidang_ditolak_403(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', null);

        $this->actingAs($akun)
            ->getJson(route('anggota.bidang-saya'))
            ->assertForbidden();
    }

    // ------------------------------------------------- AC 3: bidang tanpa anggota

    public function test_bidang_tanpa_anggota_menampilkan_pesan_tanpa_error(): void
    {
        $ekonomi = $this->bidang('Ekonomi');
        $akun    = $this->buatAkun('Anggota Bidang', $ekonomi->id_bidang);

        $this->actingAs($akun)
            ->getJson(route('anggota.bidang-saya'))
            ->assertOk()
            ->assertJsonCount(0, 'anggota.data')
            ->assertJsonPath('anggota.total', 0)
            ->assertJsonPath('pesan', self::PESAN_KOSONG);

        $this->actingAs($akun)
            ->get(route('anggota.bidang-saya'))
            ->assertOk()
            ->assertSee(self::PESAN_KOSONG);
    }

    // ------------------------------------------------- AC 4: tanpa sesi

    public function test_tanpa_sesi_ditolak_dan_diarahkan_ke_halaman_masuk(): void
    {
        $this->getJson(route('anggota.bidang-saya'))->assertUnauthorized();

        $this->get(route('anggota.bidang-saya'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------- pembatasan peran

    #[DataProvider('perannyaTidakBerhak')]
    public function test_peran_selain_pimpinan_dan_anggota_bidang_ditolak_403(string $namaPeran): void
    {
        $ekonomi = $this->bidang('Ekonomi');
        $akun    = $this->buatAkun($namaPeran, $ekonomi->id_bidang);
        $this->buatAnggota('Ani', $ekonomi->id_bidang, $akun->id_pengguna);

        $this->actingAs($akun)
            ->getJson(route('anggota.bidang-saya'))
            ->assertForbidden();
    }

    // ------------------------------------------------- data provider

    public static function peranBerhak(): array
    {
        return [
            'Pimpinan Bidang' => ['Pimpinan Bidang'],
            'Anggota Bidang'  => ['Anggota Bidang'],
        ];
    }

    public static function perannyaTidakBerhak(): array
    {
        return [
            'Admin'            => ['Admin'],
            'Penanggung Jawab' => ['Penanggung Jawab'],
            'Bendahara'        => ['Bendahara'],
            'Penasihat'        => ['Penasihat'],
        ];
    }

    public static function bidangTidakValid(): array
    {
        return [
            'huruf'     => ['bidang=abc'],
            'array'     => ['bidang[]=1'],
            'negatif'   => ['bidang=-1'],
            'desimal'   => ['bidang=1.5'],
            'injeksi'   => ["bidang=1%20OR%201%3D1"],
        ];
    }
}