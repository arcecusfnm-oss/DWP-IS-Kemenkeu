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

    protected $seed = true;

    public function test_admin_dapat_mengubah_peran_pengguna_dengan_peran_valid()
    {
        // Ambil ID peran pertama dan kedua yang ada di database secara aman
        $perans = Peran::pluck('id_peran');
        $idAdminPeran = $perans[0] ?? Peran::create(['nama_peran' => 'Admin'])->id_peran;
        $idTargetPeran = $perans[1] ?? Peran::create(['nama_peran' => 'Anggota'])->id_peran;
        $idBidang = Bidang::value('id_bidang') ?? Bidang::create(['nama_bidang' => 'Umum'])->id_bidang;

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $idBidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target User',
            'nama_pengguna' => 'target_user',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idTargetPeran,
            'id_bidang' => $idBidang,
        ]);

        // Ambil nama peran tujuan secara dinamis biar validasinya tembus
        $namaPeranAdmin = Peran::find($idAdminPeran)->nama_peran;

        $response = $this->actingAs($admin)
                         ->put(route('admin.akun.peran.update', $target->id_pengguna), [
                             'peran' => $namaPeranAdmin
                         ]);

        $response->assertRedirect(route('admin.akun.index'));
        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'id_peran' => $idAdminPeran,
        ]);
    }

    public function test_gagal_mengubah_peran_jika_peran_di_luar_daftar()
    {
        $perans = Peran::pluck('id_peran');
        $idAdminPeran = $perans[0] ?? Peran::create(['nama_peran' => 'Admin'])->id_peran;
        $idTargetPeran = $perans[1] ?? Peran::create(['nama_peran' => 'Anggota'])->id_peran;
        $idBidang = Bidang::value('id_bidang') ?? Bidang::create(['nama_bidang' => 'Umum'])->id_bidang;

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $idBidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target User',
            'nama_pengguna' => 'target_user',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idTargetPeran,
            'id_bidang' => $idBidang,
        ]);

        $response = $this->actingAs($admin)
                         ->put(route('admin.akun.peran.update', $target->id_pengguna), [
                             'peran' => 'PeranHantu'
                         ]);

        $response->assertSessionHasErrors('peran');
    }

    public function test_admin_tidak_dapat_mengubah_peran_akun_sendiri()
    {
        $idAdminPeran = Peran::value('id_peran') ?? Peran::create(['nama_peran' => 'Admin'])->id_peran;
        $idBidang = Bidang::value('id_bidang') ?? Bidang::create(['nama_bidang' => 'Umum'])->id_bidang;

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $idBidang,
        ]);

        $namaPeranLain = Peran::where('id_peran', '!=', $idAdminPeran)->value('nama_peran') ?? 'Anggota';

        $response = $this->actingAs($admin)
                         ->put(route('admin.akun.peran.update', $admin->id_pengguna), [
                             'peran' => $namaPeranLain
                         ]);

        $response->assertStatus(403);
    }
}