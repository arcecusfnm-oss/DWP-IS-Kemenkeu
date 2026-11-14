<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T-44 (B-020): uji endpoint daftar lengkap anggota (T-42).
 * Mencakup filter bidang, data kosong, dan akses non-Admin.
 */
class DaftarAnggotaTest extends TestCase
{
    use RefreshDatabase;

    private function buatPengguna(string $namaPeran, string $username): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $namaPeran]);

        $id = DB::table('pengguna')->insertGetId([
            'nama_lengkap'           => 'Akun ' . $username,
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => Hash::make('Rahasia123!'),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => 0,
            'id_peran'               => $peran->id_peran,
            'id_bidang'              => null,
            'created_at'             => now(),
            'updated_at'             => now(),
        ], 'id_pengguna');

        return Pengguna::find($id);
    }

    private function buatAnggota(string $nama, ?int $idBidang, int $dibuatOleh, bool $dihapus = false): int
    {
        return DB::table('anggota')->insertGetId([
            'nama_lengkap'  => $nama,
            'nomor_telepon' => '081234567890',
            'id_bidang'     => $idBidang,
            'status'        => 'aktif',
            'dibuat_oleh'   => $dibuatOleh,
            'dihapus_pada'  => $dihapus ? now() : null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], 'id_anggota');
    }

    private function namaDariRespons($respons): array
    {
        return collect($respons->json('anggota.data'))->pluck('nama_lengkap')->all();
    }

    public function test_admin_melihat_seluruh_anggota_dari_semua_bidang(): void
    {
        $admin    = $this->buatPengguna('Admin', 'admin.uji');
        $ekonomi  = Bidang::firstOrCreate(['nama_bidang' => 'Ekonomi']);
        $pendidik = Bidang::firstOrCreate(['nama_bidang' => 'Pendidikan']);

        $this->buatAnggota('Ani', $ekonomi->id_bidang, $admin->id_pengguna);
        $this->buatAnggota('Budi', $pendidik->id_bidang, $admin->id_pengguna);
        $this->buatAnggota('Citra', null, $admin->id_pengguna);

        $respons = $this->actingAs($admin)->getJson(route('admin.anggota.index'));

        $respons->assertOk()->assertJsonPath('anggota.total', 3);
        $this->assertEqualsCanonicalizing(['Ani', 'Budi', 'Citra'], $this->namaDariRespons($respons));
    }

    public function test_filter_bidang_hanya_menampilkan_anggota_bidang_itu(): void
    {
        $admin    = $this->buatPengguna('Admin', 'admin.uji');
        $ekonomi  = Bidang::firstOrCreate(['nama_bidang' => 'Ekonomi']);
        $pendidik = Bidang::firstOrCreate(['nama_bidang' => 'Pendidikan']);

        $this->buatAnggota('Ani', $ekonomi->id_bidang, $admin->id_pengguna);
        $this->buatAnggota('Dewi', $ekonomi->id_bidang, $admin->id_pengguna);
        $this->buatAnggota('Budi', $pendidik->id_bidang, $admin->id_pengguna);

        $respons = $this->actingAs($admin)
            ->getJson(route('admin.anggota.index', ['bidang' => $ekonomi->id_bidang]));

        $respons->assertOk()->assertJsonPath('anggota.total', 2);
        $this->assertEqualsCanonicalizing(['Ani', 'Dewi'], $this->namaDariRespons($respons));
    }

    public function test_bidang_tanpa_anggota_menghasilkan_daftar_kosong_tanpa_error(): void
    {
        $admin  = $this->buatPengguna('Admin', 'admin.uji');
        $kosong = Bidang::firstOrCreate(['nama_bidang' => 'Kesehatan']);

        $respons = $this->actingAs($admin)
            ->getJson(route('admin.anggota.index', ['bidang' => $kosong->id_bidang]));

        $respons->assertOk()
            ->assertJsonCount(0, 'anggota.data')
            ->assertJsonPath('anggota.total', 0);
    }

    public function test_tidak_ada_anggota_sama_sekali_menghasilkan_daftar_kosong(): void
    {
        $admin = $this->buatPengguna('Admin', 'admin.uji');

        $this->actingAs($admin)
            ->getJson(route('admin.anggota.index'))
            ->assertOk()
            ->assertJsonCount(0, 'anggota.data');
    }

    public function test_anggota_yang_sudah_dihapus_tidak_ikut_ditampilkan(): void
    {
        $admin   = $this->buatPengguna('Admin', 'admin.uji');
        $ekonomi = Bidang::firstOrCreate(['nama_bidang' => 'Ekonomi']);

        $this->buatAnggota('Ani', $ekonomi->id_bidang, $admin->id_pengguna);
        $this->buatAnggota('Sudah Dihapus', $ekonomi->id_bidang, $admin->id_pengguna, true);

        $respons = $this->actingAs($admin)->getJson(route('admin.anggota.index'));

        $this->assertSame(['Ani'], $this->namaDariRespons($respons));
    }

    public function test_filter_bidang_tidak_valid_ditolak(): void
    {
        $admin = $this->buatPengguna('Admin', 'admin.uji');

        $this->actingAs($admin)
            ->getJson(route('admin.anggota.index', ['bidang' => 'abc']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('bidang');
    }

    public function test_respons_tidak_memuat_data_sensitif(): void
    {
        $admin   = $this->buatPengguna('Admin', 'admin.uji');
        $ekonomi = Bidang::firstOrCreate(['nama_bidang' => 'Ekonomi']);
        $this->buatAnggota('Ani', $ekonomi->id_bidang, $admin->id_pengguna);

        $respons = $this->actingAs($admin)->getJson(route('admin.anggota.index'));
        $baris   = $respons->json('anggota.data.0');

        $this->assertEqualsCanonicalizing(
            ['id_anggota', 'nama_lengkap', 'nomor_telepon', 'status', 'nama_bidang', 'nama_unit_kerja'],
            array_keys($baris)
        );
        $this->assertStringNotContainsString('kata_sandi', $respons->getContent());
        $this->assertStringNotContainsString('remember_token', $respons->getContent());
    }

    public function test_peran_selain_admin_ditolak(): void
    {
        foreach (['Pimpinan Bidang', 'Anggota Bidang', 'Penasehat'] as $i => $peran) {
            $akun = $this->buatPengguna($peran, 'akun.uji.' . $i);

            $this->actingAs($akun)
                ->getJson(route('admin.anggota.index'))
                ->assertForbidden();
        }
    }

    public function test_tamu_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('admin.anggota.index'))
            ->assertRedirect(route('login'));
    }
}