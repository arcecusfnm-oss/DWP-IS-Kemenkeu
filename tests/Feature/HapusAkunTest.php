<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HapusAkunTest extends TestCase
{
    use RefreshDatabase;

    private function buatAdmin(): Pengguna
    {
        $peran = Peran::firstOrCreate([
            'nama_peran' => 'Admin',
        ]);

        $bidang = Bidang::firstOrCreate([
            'nama_bidang' => 'Umum',
        ]);

        return Pengguna::create([
            'nama_lengkap' => 'Admin Pengujian',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $peran->id_peran,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    private function buatPengguna(): Pengguna
    {
        $peran = Peran::firstOrCreate([
            'nama_peran' => 'Anggota',
        ]);

        $bidang = Bidang::firstOrCreate([
            'nama_bidang' => 'Pendidikan',
        ]);

        return Pengguna::create([
            'nama_lengkap' => 'Anggota Pengujian',
            'nama_pengguna' => 'anggota_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $peran->id_peran,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    public function test_admin_dapat_menghapus_akun_dengan_soft_delete(): void
    {
        $admin = $this->buatAdmin();
        $pengguna = $this->buatPengguna();

        $response = $this->actingAs($admin)
            ->delete(route('admin.akun.destroy', $pengguna->id_pengguna));

        $response->assertRedirect(route('admin.akun.index'));

        // Akun tetap ada, tetapi sudah memiliki tanggal penghapusan.
        $this->assertDatabaseMissing('pengguna', [
            'id_pengguna' => $pengguna->id_pengguna,
            'dihapus_pada' => null,
        ]);

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $pengguna->id_pengguna,
        ]);
    }

    public function test_admin_tidak_dapat_menghapus_akunnya_sendiri(): void
    {
        $admin = $this->buatAdmin();

        $response = $this->actingAs($admin)
            ->delete(route('admin.akun.destroy', $admin->id_pengguna));

        $response->assertForbidden();

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $admin->id_pengguna,
            'dihapus_pada' => null,
        ]);
    }

    public function test_akun_yang_sudah_dihapus_tidak_bisa_masuk(): void
    {
        $admin = $this->buatAdmin();
        $pengguna = $this->buatPengguna();

        // Admin menghapus akun pengguna (Soft Delete)
        $this->actingAs($admin)
            ->delete(route('admin.akun.destroy', $pengguna->id_pengguna));

        // Bersihkan sesi admin agar target bisa mencoba masuk
        \Illuminate\Support\Facades\Auth::logout();

        // Target mencoba login
        $loginBerhasil = \Illuminate\Support\Facades\Auth::attempt([
            'nama_pengguna' => 'anggota_test',
            'password'      => 'password123'
        ]);

        // Ekspektasi: Gagal login karena akun sudah terhapus
        $this->assertFalse($loginBerhasil);
    }
}
