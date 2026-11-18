<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ValidasiInputTest extends TestCase
{
    use RefreshDatabase;

    private function buatAdmin(): Pengguna
    {
        $peran  = Peran::firstOrCreate(['nama_peran' => 'Admin']);
        $bidang = Bidang::firstOrCreate(['nama_bidang' => 'Sekretariat']);

        return Pengguna::create([
            'nama_lengkap'           => 'Akun admin.uji',
            'nama_pengguna'          => 'admin.uji',
            'kata_sandi_hash'        => Hash::make('password123'),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $peran->id_peran,
            'id_bidang'              => $bidang->id_bidang,
        ]);
    }

    // Test 1: request langsung ke server (tanpa form) dengan karakter SQL dan tag ditolak.
    public function test_input_berbahaya_ditolak_dan_tidak_mengubah_data(): void
    {
        $admin = $this->buatAdmin();
        Peran::firstOrCreate(['nama_peran' => 'Anggota Bidang']);
        $bidang = Bidang::firstOrCreate(['nama_bidang' => 'Ekonomi']);

        $jumlahSebelum = Pengguna::count();

        $this->actingAs($admin)
            ->postJson(route('admin.akun.store'), [
                'nama'     => '<script>alert(1)</script>',
                'username' => "'; DROP TABLE pengguna;--",
                'peran'    => 'Anggota Bidang',
                'bidang'   => [$bidang->nama_bidang],
                'password' => 'Rahasia123!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama', 'username']);

        $this->assertSame($jumlahSebelum, Pengguna::count());
        $this->assertTrue(Schema::hasTable('pengguna'));
        $this->assertDatabaseMissing('pengguna', ['nama_pengguna' => "'; DROP TABLE pengguna;--"]);
    }

    // Test 2: nama berisi <script> yang sudah ada di DB (mis. dari data impor/LDAP)
    // tampil sebagai teks biasa, bukan tag HTML.
    public function test_nama_berisi_tag_script_tampil_sebagai_teks_biasa(): void
    {
        $admin = $this->buatAdmin();
        $peran = Peran::firstOrCreate(['nama_peran' => 'Anggota Bidang']);

        Pengguna::create([
            'nama_lengkap'           => '<script>alert(1)</script>',
            'nama_pengguna'          => 'akun.xss',
            'kata_sandi_hash'        => Hash::make('password123'),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $peran->id_peran,
            'id_bidang'              => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.akun.index'))
            ->assertStatus(200)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}