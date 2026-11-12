<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UbahAkunTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    // 1. Skenario Berhasil
    public function test_admin_berhasil_mengubah_data_akun()
    {
        $idAdminPeran = Peran::value('id_peran') ?? Peran::create(['nama_peran' => 'Admin'])->id_peran;
        $bidang = Bidang::first() ?? Bidang::create(['nama_bidang' => 'Sekretariat']);

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Lama',
            'nama_pengguna' => 'target_lama',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.akun.update', $target->id_pengguna), [
                'nama' => 'Target Baru',
                'username' => 'target_baru',
                'bidang' => [$bidang->nama_bidang],
            ]);

        $response->assertRedirect(route('admin.akun.index'));
        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'nama_lengkap' => 'Target Baru',
            'nama_pengguna' => 'target_baru',
        ]);
    }

    // 2. Skenario Gagal: Nama Pengguna Ganda (Sudah Terpakai)
    public function test_gagal_ubah_akun_jika_username_sudah_dipakai()
    {
        $idAdminPeran = Peran::value('id_peran') ?? Peran::create(['nama_peran' => 'Admin'])->id_peran;
        $bidang = Bidang::first() ?? Bidang::create(['nama_bidang' => 'Sekretariat']);

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $userLain = Pengguna::create([
            'nama_lengkap' => 'User Lain',
            'nama_pengguna' => 'user_lain',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Update',
            'nama_pengguna' => 'target_update',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.akun.update', $target->id_pengguna), [
                'nama' => 'Nama Baru',
                'username' => 'user_lain', // Sengaja dibuat bentrok
                'bidang' => [$bidang->nama_bidang],
            ]);

        $response->assertSessionHasErrors('username');
    }

    // 3. Skenario Gagal: Kolom Kosong
    public function test_gagal_ubah_akun_jika_kolom_wajib_kosong()
    {
        $idAdminPeran = Peran::value('id_peran') ?? Peran::create(['nama_peran' => 'Admin'])->id_peran;
        $bidang = Bidang::first() ?? Bidang::create(['nama_bidang' => 'Sekretariat']);

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Update',
            'nama_pengguna' => 'target_update',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.akun.update', $target->id_pengguna), [
                'nama' => '',
                'username' => '',
                'bidang' => [],
            ]);

        $response->assertSessionHasErrors(['nama', 'username', 'bidang']);
    }

    // 4. Skenario Gagal: Akses Non-Admin (RBAC)
    public function test_non_admin_ditolak_mengubah_akun()
    {
        $perans = Peran::pluck('id_peran');
        $idNonAdmin = $perans[1] ?? Peran::create(['nama_peran' => 'Anggota'])->id_peran;
        $bidang = Bidang::first() ?? Bidang::create(['nama_bidang' => 'Sekretariat']);

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
            'nama_lengkap' => 'Target Update',
            'nama_pengguna' => 'target_update',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idNonAdmin,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($nonAdmin)
            ->put(route('admin.akun.update', $target->id_pengguna), [
                'nama' => 'Target Baru',
                'username' => 'target_baru',
                'bidang' => [$bidang->nama_bidang],
            ]);

        $response->assertStatus(403);
    }

    // 5. Username lama milik akun sendiri tetap boleh digunakan
    public function test_username_lama_akun_sendiri_tidak_dianggap_duplikat()
    {
        $idAdminPeran = Peran::value('id_peran') ?? Peran::create(['nama_peran' => 'Admin'])->id_peran;
        $bidang = Bidang::first() ?? Bidang::create(['nama_bidang' => 'Sekretariat']);

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Lama',
            'nama_pengguna' => 'target_lama',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.akun.update', $target->id_pengguna), [
                'nama' => 'Target Diperbarui',
                'username' => 'target_lama',
                'bidang' => [$bidang->nama_bidang],
            ]);

        $response->assertRedirect(route('admin.akun.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'nama_lengkap' => 'Target Diperbarui',
            'nama_pengguna' => 'target_lama',
        ]);
    }

    // 6. Bidang yang tidak terdaftar harus ditolak
    public function test_gagal_ubah_akun_jika_bidang_tidak_terdaftar()
    {
        $idAdminPeran = Peran::value('id_peran') ?? Peran::create(['nama_peran' => 'Admin'])->id_peran;
        $bidang = Bidang::first() ?? Bidang::create(['nama_bidang' => 'Sekretariat']);

        $admin = Pengguna::create([
            'nama_lengkap' => 'Admin Test',
            'nama_pengguna' => 'admin_test',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $target = Pengguna::create([
            'nama_lengkap' => 'Target Lama',
            'nama_pengguna' => 'target_lama',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $idAdminPeran,
            'id_bidang' => $bidang->id_bidang,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.akun.update', $target->id_pengguna), [
                'nama' => 'Target Baru',
                'username' => 'target_baru',
                'bidang' => ['Bidang Tidak Terdaftar'],
            ]);

        $response->assertSessionHasErrors('bidang.0'); // Gunakan 'bidang.0'

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'nama_lengkap' => 'Target Lama',
            'nama_pengguna' => 'target_lama',
        ]);
    }
}