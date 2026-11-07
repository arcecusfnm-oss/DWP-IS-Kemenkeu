<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Bidang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssignBidangTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        Peran::firstOrCreate([
            'nama_peran' => 'Admin',
        ]);

        Peran::firstOrCreate([
            'nama_peran' => 'Anggota',
        ]);

        foreach (
            [
                'Ekonomi',
                'Sosial Budaya',
                'Pendidikan',
                'Sekretariat',
                'Bendahara',
            ] as $nama
        ) {
            Bidang::firstOrCreate([
                'nama_bidang' => $nama,
            ]);
        }
    }

    // 1. Admin berhasil mengganti bidang akun
    public function test_admin_berhasil_mengganti_bidang_akun()
    {
        $idAdmin = Peran::where('nama_peran', 'Admin')->value('id_peran');
        $bidangLama = Bidang::first();

        $bidangBaru = Bidang::where(
            'id_bidang',
            '!=',
            $bidangLama->id_bidang
        )->first();

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdmin,
            'id_bidang' => $bidangLama->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Test',
            'nama_pengguna' => 'target_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdmin,
            'id_bidang' => $bidangLama->id_bidang,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.akun.bidang.update', $target->id_pengguna), [
                'id_bidang' => $bidangBaru->id_bidang,
            ]);

        $response->assertRedirect(route('admin.akun.index'));

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'id_bidang' => $bidangBaru->id_bidang,
        ]);
    }

    // 2. ID bidang yang tidak terdaftar ditolak
    public function test_bidang_tidak_terdaftar_ditolak()
    {
        $idAdmin = Peran::where('nama_peran', 'Admin')->value('id_peran');
        $bidang = Bidang::first();

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdmin,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Test',
            'nama_pengguna' => 'target_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdmin,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.akun.bidang.update', $target->id_pengguna), [
                'id_bidang' => 999999,
            ]);

        $response->assertSessionHasErrors('id_bidang');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    // 3. ID bidang wajib diisi
    public function test_id_bidang_wajib_diisi()
    {
        $idAdmin = Peran::where('nama_peran', 'Admin')->value('id_peran');
        $bidang = Bidang::first();

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdmin,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Test',
            'nama_pengguna' => 'target_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdmin,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.akun.bidang.update', $target->id_pengguna), [
                'id_bidang' => '',
            ]);

        $response->assertSessionHasErrors('id_bidang');
    }

    // 4. Non-Admin tidak boleh menetapkan bidang
    public function test_non_admin_ditolak_menetapkan_bidang()
    {
        $idNonAdmin = Peran::where('nama_peran', '!=', 'Admin')
            ->value('id_peran');

        $bidang = Bidang::first();

        $nonAdmin = Pengguna::create([
            'nama_lengkap' => 'Anggota Test',
            'nama_pengguna' => 'anggota_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idNonAdmin,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Test',
            'nama_pengguna' => 'target_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idNonAdmin,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($nonAdmin)
            ->put(route('admin.akun.bidang.update', $target->id_pengguna), [
                'id_bidang' => $bidang->id_bidang,
            ]);

        $response->assertStatus(403);
    }
}
