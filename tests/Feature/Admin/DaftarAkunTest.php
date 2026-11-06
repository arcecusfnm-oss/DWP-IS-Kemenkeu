<?php

namespace Tests\Feature\Admin;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaftarAkunTest extends TestCase
{
    use RefreshDatabase;

    private function buatAkun(string $namaPeran, string $namaBidang, string $username): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $namaPeran]);
        $bidang = Bidang::firstOrCreate(['nama_bidang' => $namaBidang]);

        return Pengguna::create([
            'nama_lengkap'           => 'Akun ' . $username,
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => bcrypt('password123'),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $peran->id_peran,
            'id_bidang'              => $bidang->id_bidang,
        ]);
    }

    public function test_admin_bisa_melihat_daftar_akun_utama()
    {
        $admin = $this->buatAkun('Admin', 'Sekretariat', 'admin.satu');
        $this->buatAkun('Bendahara', 'Keuangan', 'bendahara.satu');

        $this->actingAs($admin)
            ->get(route('admin.akun.index'))
            ->assertStatus(200)
            ->assertSee('admin.satu')
            ->assertSee('bendahara.satu')
            ->assertDontSee('kata_sandi_hash'); // AC 5: Pastikan password tidak bocor
    }

    public function test_filter_berdasarkan_peran()
    {
        $admin = $this->buatAkun('Admin', 'Sekretariat', 'admin.utama');
        $this->buatAkun('Penasihat', 'Sekretariat', 'penasihat.satu');
        $this->buatAkun('Ketua', 'Umum', 'ketua.satu');

        $this->actingAs($admin)
            ->get(route('admin.akun.index', ['peran' => 'Penasihat']))
            ->assertStatus(200)
            ->assertSee('penasihat.satu') // Harus muncul
            ->assertDontSee('ketua.satu'); // Tidak boleh muncul
    }

    public function test_filter_berdasarkan_bidang()
    {
        $admin = $this->buatAkun('Admin', 'Sekretariat', 'admin.utama');
        $this->buatAkun('Anggota', 'Sosial Budaya', 'anggota.sosbud');
        $this->buatAkun('Anggota', 'Ekonomi', 'anggota.ekonomi');

        $this->actingAs($admin)
            ->get(route('admin.akun.index', ['bidang' => 'Sosial Budaya']))
            ->assertStatus(200)
            ->assertSee('anggota.sosbud') // Harus muncul
            ->assertDontSee('anggota.ekonomi'); // Tidak boleh muncul
    }

    public function test_akses_ditolak_untuk_tanpa_sesi()
    {
        $this->get(route('admin.akun.index'))
            ->assertRedirect(route('login')); // Harus dilempar ke login
    }

    public function test_akses_ditolak_untuk_peran_selain_admin()
    {
        $bukanAdmin = $this->buatAkun('Anggota', 'Pendidikan', 'anggota.biasa');

        $this->actingAs($bukanAdmin)
            ->get(route('admin.akun.index'))
            ->assertStatus(403); // Harus Forbidden
    }
}