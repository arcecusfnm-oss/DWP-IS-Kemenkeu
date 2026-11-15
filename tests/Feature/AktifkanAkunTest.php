<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AktifkanAkunTest extends TestCase
{
    use RefreshDatabase;

    private function buatAkun(
        string $peran,
        string $username,
        string $status = 'aktif'
    ): Pengguna {
        $role = Peran::firstOrCreate([
            'nama_peran' => $peran,
        ]);

        $bidang = Bidang::firstOrCreate([
            'nama_bidang' => 'Umum',
        ]);

        return Pengguna::create([
            'nama_lengkap' => 'Pengguna Pengujian',
            'nama_pengguna' => $username,
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => $status,
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $role->id_peran,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    public function test_admin_dapat_mengaktifkan_kembali_akun_nonaktif(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'anggota_test', 'nonaktif');

        $response = $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.aktifkan', $target->id_pengguna));

        $response->assertRedirect(route('admin.akun.index'));
        $response->assertSessionHas('sukses');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'aktif',
            'dihapus_pada' => null,
        ]);
    }

    public function test_akun_yang_sudah_aktif_tidak_diaktifkan_kembali(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'anggota_test');

        $response = $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.aktifkan', $target->id_pengguna));

        $response->assertRedirect(route('admin.akun.index'));
        $response->assertSessionHas('galat');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'aktif',
        ]);
    }

    public function test_non_admin_tidak_dapat_mengaktifkan_akun(): void
    {
        $anggota = $this->buatAkun('Anggota', 'anggota_test');
        $target = $this->buatAkun('Anggota', 'target_test', 'nonaktif');

        $response = $this->actingAs($anggota)
            ->post(route('admin.akun.aktifkan', $target->id_pengguna));

        $response->assertForbidden();

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'nonaktif',
        ]);
    }

    public function test_request_get_tidak_dapat_mengaktifkan_akun(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'target_test', 'nonaktif');

        $response = $this->actingAs($admin)
            ->get(route('admin.akun.aktifkan', $target->id_pengguna));

        $response->assertStatus(405);

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'nonaktif',
        ]);
    }

    public function test_id_akun_yang_tidak_ditemukan_menghasilkan_404(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');

        $response = $this->actingAs($admin)
            ->post(route('admin.akun.aktifkan', 999999));

        $response->assertNotFound();
    }

    public function test_id_akun_tidak_valid_ditolak(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');

        $response = $this->actingAs($admin)
            ->post('/admin/akun/abc/aktifkan');

        $response->assertNotFound();
    }


    public function test_akun_yang_sudah_dihapus_tidak_dapat_diaktifkan(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'target_test', 'nonaktif');

        $target->delete();

        $response = $this->actingAs($admin)
            ->post(route('admin.akun.aktifkan', $target->id_pengguna));

        $response->assertNotFound();

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'nonaktif',
        ]);

        $this->assertDatabaseMissing('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'dihapus_pada' => null,
        ]);
    }


    public function test_password_lama_tetap_berlaku_setelah_aktivasi(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'anggota_test', 'nonaktif');

        $passwordSebelumAktivasi = $target->kata_sandi_hash;

        $this->actingAs($admin)
            ->post(route('admin.akun.aktifkan', $target->id_pengguna))
            ->assertSessionHas('sukses');

        $target->refresh();

        $this->assertSame(
            $passwordSebelumAktivasi,
            $target->kata_sandi_hash
        );

        $this->assertTrue(
            Hash::check('password123', $target->kata_sandi_hash)
        );
    }


    public function test_akun_aktif_sejak_awal_dapat_login_seperti_biasa(): void
    {
        $target = $this->buatAkun('Anggota', 'anggota_aktif_test');

        $response = $this->post('/login', [
            'username' => 'anggota_aktif_test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertAuthenticatedAs($target);
    }


    public function test_akun_yang_dinonaktifkan_kembali_tidak_dapat_login(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'anggota_test');

        // Admin menonaktifkan akun.
        $this->actingAs($admin)
            ->post(route('admin.akun.nonaktifkan', $target->id_pengguna));

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'nonaktif',
        ]);

        // Logout Admin.
        $this->post('/logout');

        // Akun nonaktif mencoba login dengan password yang benar.
        $response = $this->post('/login', [
            'username' => 'anggota_test',
            'password' => 'password123',
        ]);

        $this->assertGuest();

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'nonaktif',
        ]);
    }

    public function test_akun_yang_diaktifkan_dapat_login_kembali(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'anggota_test', 'nonaktif');

        // Admin mengaktifkan kembali akun target.
        $this->actingAs($admin)
            ->post(route('admin.akun.aktifkan', $target->id_pengguna))
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'aktif',
        ]);

        // Admin logout agar target dapat melakukan login.
        $this->post('/logout');

        // Pengguna yang sudah aktif mencoba login kembali.
        $response = $this->post('/login', [
            'username' => 'anggota_test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertAuthenticatedAs($target);
    }
}
