<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssignBidangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Anggota Bidang'] as $peran) {
            Peran::firstOrCreate(['nama_peran' => $peran]);
        }

        foreach (['Ekonomi', 'Sosial Budaya', 'Pendidikan', 'Sekretariat', 'Bendahara'] as $nama) {
            Bidang::firstOrCreate(['nama_bidang' => $nama]);
        }
    }

    private function idPeran(string $nama): int
    {
        return Peran::where('nama_peran', $nama)->value('id_peran');
    }

    private function idBidang(string $nama): int
    {
        return Bidang::where('nama_bidang', $nama)->value('id_bidang');
    }

    private function buatAkun(string $username, string $peran, string $bidang): Pengguna
    {
        return Pengguna::create([
            'nama_lengkap' => ucfirst($username),
            'nama_pengguna' => $username,
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $this->idPeran($peran),
            'id_bidang' => $this->idBidang($bidang),
        ]);
    }

    // 1. Admin berhasil mengganti bidang akun
    public function test_admin_berhasil_mengganti_bidang_akun()
    {
        $admin = $this->buatAkun('admin_test', 'Admin', 'Sekretariat');
        $target = $this->buatAkun('target_test', 'Anggota Bidang', 'Ekonomi');

        $this->actingAs($admin)
            ->put(route('admin.akun.bidang.update', $target->id_pengguna), [
                'bidang' => ['Pendidikan'], // Array nama bidang
            ])
            ->assertRedirect(route('admin.akun.index'));

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'id_bidang' => $this->idBidang('Pendidikan'),
        ]);
    }

    // 2. Bidang yang tidak terdaftar ditolak
    public function test_bidang_tidak_terdaftar_ditolak()
    {
        $admin = $this->buatAkun('admin_test', 'Admin', 'Sekretariat');
        $target = $this->buatAkun('target_test', 'Anggota Bidang', 'Ekonomi');

        $this->actingAs($admin)
            ->put(route('admin.akun.bidang.update', $target->id_pengguna), [
                'bidang' => ['Bidang Tidak Terdaftar'],
            ])
            ->assertSessionHasErrors('bidang.0'); // Gunakan 'bidang.0'

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'id_bidang' => $this->idBidang('Ekonomi'),
        ]);
    }

    // 3. Bidang wajib diisi
    public function test_id_bidang_wajib_diisi()
    {
        $admin = $this->buatAkun('admin_test', 'Admin', 'Sekretariat');
        $target = $this->buatAkun('target_test', 'Anggota Bidang', 'Ekonomi');

        $this->actingAs($admin)
            ->put(route('admin.akun.bidang.update', $target->id_pengguna), [
                'bidang' => [],
            ])
            ->assertSessionHasErrors('bidang');
    }

    // 4. Non-Admin tidak boleh menetapkan bidang
    public function test_non_admin_ditolak_menetapkan_bidang()
    {
        $nonAdmin = $this->buatAkun('anggota_test', 'Anggota Bidang', 'Ekonomi');
        $target = $this->buatAkun('target_test', 'Anggota Bidang', 'Ekonomi');

        $this->actingAs($nonAdmin)
            ->put(route('admin.akun.bidang.update', $target->id_pengguna), [
                'bidang' => ['Pendidikan'],
            ])
            ->assertStatus(403);
    }
}