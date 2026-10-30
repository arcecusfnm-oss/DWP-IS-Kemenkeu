<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private function buatPengguna(): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => 'Admin']);
        $bidang = Bidang::firstOrCreate(['nama_bidang' => 'Umum']);

        return Pengguna::create([
            'nama_lengkap' => 'Fahira Putri Ananta',
            'nama_pengguna' => 'fahira',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $peran->id_peran,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    // AC 2: tombol kembali tidak membuka halaman terlindungi
    public function test_halaman_terlindungi_ditolak_setelah_keluar(): void
    {
        $this->actingAs($this->buatPengguna())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    // AC 3: sesi lama tidak berlaku lagi
    public function test_id_sesi_berubah_setelah_keluar(): void
    {
        $this->actingAs($this->buatPengguna())
            ->withSession(['data_lama' => 'aktif']);
        $idLama = session()->getId();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertNotSame($idLama, session()->getId());
        $this->assertFalse(session()->has('data_lama'));
    }

    public function test_keluar_tanpa_login_dialihkan_ke_halaman_masuk(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}