<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
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

    public function test_kata_sandi_disimpan_dalam_bentuk_hash(): void
    {
        $pengguna = $this->buatPengguna();

        $hashDiDatabase = $pengguna->fresh()->kata_sandi_hash;

        // Password tidak boleh tersimpan sebagai teks biasa.
        $this->assertNotSame('password123', $hashDiDatabase);

        // Hash harus bisa diverifikasi menggunakan password asli.
        $this->assertTrue(
            Hash::check('password123', $hashDiDatabase)
        );
    }

    public function test_hash_lama_diperbarui_saat_login_berhasil(): void
    {
        $pengguna = $this->buatPengguna();

        // Simulasikan hash lama dengan bcrypt rounds 4.
        $hashLama = Hash::make('password123', ['rounds' => 4]);

        config(['hashing.bcrypt.rounds' => 12]);
        Hash::forgetDrivers();

        DB::table($pengguna->getTable())
            ->where($pengguna->getKeyName(), $pengguna->getKey())
            ->update([
                'kata_sandi_hash' => $hashLama,
            ]);

        $this->assertTrue(
            Hash::needsRehash($pengguna->fresh()->kata_sandi_hash)
        );

        // Login dengan password yang benar.
        $this->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $hashBaru = $pengguna->fresh()->kata_sandi_hash;

        $this->assertFalse(Hash::needsRehash($hashBaru));
        $this->assertTrue(Hash::check('password123', $hashBaru));
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

    public function test_id_sesi_berubah_setelah_login_berhasil(): void
    {
        $this->buatPengguna();

        // Mulai sesi sebelum login.
        $this->get('/login');

        $idSesiSebelum = session()->getId();

        // Login dengan kredensial yang benar.
        $this->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));

        $idSesiSesudah = session()->getId();

        $this->assertNotSame($idSesiSebelum, $idSesiSesudah);
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

        // Password benar pada akun nonaktif menghasilkan pesan khusus.
        $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'login' => 'Akun tidak aktif',
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

        // Percobaan keenam dengan password benar ditolak dengan HTTP 429.
        $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertGuest();
    }

    // AC 6: penolakan berakhir setelah 15 menit
    public function test_penolakan_berakhir_setelah_lima_belas_menit(): void
    {
        $this->buatPengguna();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'fahira',
                'password' => 'salah123',
            ]);
        }

        $this->travel(901)->seconds();

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

    public function test_lockout_tetap_aktif_setelah_halaman_login_di_refresh(): void
    {
        $this->buatPengguna();

        // Lakukan lima kali percobaan login dengan password salah.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'fahira',
                'password' => 'salah123',
            ]);
        }

        // Simulasikan session kehilangan data lockout.
        $this->flushSession();

        // Username tetap diketahui agar controller bisa mencari Cache lockout.
        $this->withSession([
            'login_throttle_username' => 'fahira',
        ]);

        // Buka ulang halaman login.
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Terlalu banyak percobaan masuk.');
        $response->assertSee('Silakan coba lagi dalam:');
        $response->assertSee('15:00');
        $response->assertSee('disabled', false);

        // Pastikan timestamp berhasil dipulihkan dari Cache.
        $response->assertSessionHas('login_throttle_until');
        $response->assertSessionHas(
            'login_throttle_username',
            'fahira'
        );

        // Password benar tetap harus ditolak selama lockout aktif.
        $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertGuest();
    }

    public function test_akun_yang_sudah_dihapus_tidak_dapat_login(): void
    {
        $pengguna = $this->buatPengguna();

        $pengguna->delete();

        $this->from('/login')->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors([
                'login' => 'Nama pengguna atau kata sandi salah.',
            ]);

        $this->assertGuest();
    }
}
