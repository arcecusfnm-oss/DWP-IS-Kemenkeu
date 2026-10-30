<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function buatPengguna(
        string $username = 'fahira',
        string $password = 'password123',
        string $status = 'aktif'
    ): Pengguna {
        $peran = Peran::firstOrCreate(
            ['nama_peran' => 'Admin']
        );

        $bidang = Bidang::firstOrCreate(
            ['nama_bidang' => 'Umum']
        );

        return Pengguna::create([
            'nama_lengkap' => 'Fahira Putri Ananta',
            'nama_pengguna' => $username,
            'kata_sandi_hash' => Hash::make($password),
            'status' => $status,
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $peran->id_peran,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    public function test_pengguna_aktif_dapat_login_dengan_kata_sandi_benar(): void
    {
        $this->buatPengguna();

        $response = $this->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_login_gagal_jika_kata_sandi_salah(): void
    {
        $this->buatPengguna();

        $response = $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'salah123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'login' => 'Nama pengguna atau kata sandi salah.',
        ]);

        $this->assertGuest();
    }

    public function test_login_gagal_jika_username_tidak_ditemukan(): void
    {
        $response = $this->from('/login')->post('/login', [
            'username' => 'tidak_ada',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'login' => 'Nama pengguna atau kata sandi salah.',
        ]);

        $this->assertGuest();
    }

    public function test_akun_nonaktif_diperiksa_setelah_password(): void
    {
        $this->buatPengguna(status: 'nonaktif');

        // Password salah harus menghasilkan pesan kredensial salah.
        $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'salah123',
        ])->assertSessionHasErrors([
            'login' => 'Nama pengguna atau kata sandi salah.',
        ]);

        // Password benar harus menghasilkan pesan akun nonaktif.
        $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'login' => 'Akun tidak aktif. Hubungi Admin Sekretariat.',
        ]);

        $this->assertGuest();
    }

    public function test_username_dan_kata_sandi_wajib_diisi(): void
    {
        $response = $this->from('/login')->post('/login', [
            'username' => '',
            'password' => '',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'username',
            'password',
        ]);

        $this->assertGuest();
    }

    public function test_karakter_khusus_ditangani_tanpa_error_server(): void
    {
        $this->buatPengguna();

        $response = $this->from('/login')->post('/login', [
            'username' => "' OR 1=1 --",
            'password' => "' OR 1=1 --",
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'login' => 'Nama pengguna atau kata sandi salah.',
        ]);

        $this->assertGuest();
    }

    // AC 6: setelah 5 kali gagal, percobaan berikutnya ditolak
    public function test_lima_kali_gagal_lalu_percobaan_berikutnya_ditolak(): void
    {
        $this->buatPengguna();

        // Empat percobaan pertama masih menampilkan kredensial salah.
        for ($i = 0; $i < 4; $i++) {
            $this->from('/login')->post('/login', [
                'username' => 'fahira',
                'password' => 'salah123',
            ])->assertSessionHasErrors('login');
        }

        // Percobaan kelima langsung mengaktifkan lockout.
        $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'salah123',
        ])->assertSessionHasErrors('throttle');

        // Percobaan keenam dengan password benar tetap ditolak.
        $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertSessionHasErrors('throttle');

        $this->assertGuest();
    }

    // AC 6: penolakan berakhir setelah 5 menit
    public function test_penolakan_berakhir_setelah_lima_menit(): void
    {
        $this->buatPengguna();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'fahira',
                'password' => 'salah123',
            ]);
        }

        $this->travel(301)->seconds();

        $this->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_login_logout_berulang_enam_kali_tidak_menyebabkan_lockout(): void
    {
        $this->buatPengguna();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', [
                'username' => 'fahira',
                'password' => 'password123',
            ])->assertRedirect(route('admin.dashboard'));

            $this->assertAuthenticated();

            $this->post('/logout')->assertRedirect('/login');

            $this->assertGuest();
        }
    }

    public function test_hitungan_kegagalan_direset_setelah_login_berhasil(): void
    {
        $this->buatPengguna();

        // Empat kali gagal.
        for ($i = 0; $i < 4; $i++) {
            $this->from('/login')->post('/login', [
                'username' => 'fahira',
                'password' => 'salah123',
            ])->assertSessionHasErrors('login');
        }

        // Login berhasil harus mereset hitungan kegagalan.
        $this->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();

        // Logout, lalu empat kegagalan baru seharusnya belum lockout.
        $this->post('/logout')->assertRedirect('/login');

        for ($i = 0; $i < 4; $i++) {
            $this->from('/login')->post('/login', [
                'username' => 'fahira',
                'password' => 'salah123',
            ])->assertSessionHasErrors('login');
        }

        $this->assertGuest();
    }
}
