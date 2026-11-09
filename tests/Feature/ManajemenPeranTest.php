<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Bidang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManajemenPeranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Admin', 'Penanggung Jawab', 'Anggota Bidang'] as $peran) {
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

    public function test_admin_dapat_mengubah_peran_pengguna_dengan_peran_valid()
    {
        $admin = $this->buatAkun('admin_test', 'Admin', 'Sekretariat');
        $target = $this->buatAkun('target_user', 'Penanggung Jawab', 'Ekonomi');

        $this->actingAs($admin)
            ->put(route('admin.akun.peran.update', $target->id_pengguna), [
                'peran' => 'Anggota Bidang',
            ])
            ->assertRedirect(route('admin.akun.index'));

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'id_peran' => $this->idPeran('Anggota Bidang'),
            'id_bidang' => $this->idBidang('Ekonomi'), // bidang lama dipertahankan
        ]);
    }

    public function test_gagal_mengubah_peran_jika_peran_di_luar_daftar()
    {
        $admin = $this->buatAkun('admin_test', 'Admin', 'Sekretariat');
        $target = $this->buatAkun('target_user', 'Anggota Bidang', 'Ekonomi');

        $this->actingAs($admin)
            ->put(route('admin.akun.peran.update', $target->id_pengguna), [
                'peran' => 'PeranHantu',
            ])
            ->assertSessionHasErrors('peran');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'id_peran' => $this->idPeran('Anggota Bidang'),
        ]);
    }

    public function test_admin_tidak_dapat_mengubah_peran_akun_sendiri()
    {
        $admin = $this->buatAkun('admin_test', 'Admin', 'Sekretariat');

        $this->actingAs($admin)
            ->put(route('admin.akun.peran.update', $admin->id_pengguna), [
                'peran' => 'Anggota Bidang',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $admin->id_pengguna,
            'id_peran' => $this->idPeran('Admin'),
        ]);
    }
}