<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnggotaRingkasTest extends TestCase
{
    use RefreshDatabase;

    private function buatPengguna(string $role): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $role]);

        $id = DB::table('pengguna')->insertGetId([
            'nama_lengkap' => 'Akun Test',
            'nama_pengguna' => 'uji.ringkas.' . uniqid(),
            'kata_sandi_hash' => Hash::make('Rahasia123!'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => 0,
            'id_peran' => $peran->id_peran,
            'id_bidang' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_pengguna');

        return Pengguna::findOrFail($id);
    }

    private function buatAnggota(int $adminId, string $nama, string $status = 'aktif', bool $hapus = false): void
    {
        DB::table('anggota')->insert([
            'nama_lengkap' => $nama,
            'nomor_telepon' => '081234567890',
            'email' => 'rahasia@example.test',
            'alamat_lengkap' => 'Alamat Rahasia',
            'status' => $status,
            'dihapus_pada' => $hapus ? now() : null,
            'dibuat_oleh' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_mendapat_daftar_ringkas_seluruh_bidang(): void
    {
        $admin = $this->buatPengguna('Admin');
        $this->buatAnggota($admin->id_pengguna, 'Ani');

        $respons = $this->actingAs($admin)
            ->getJson(route('api.anggota.ringkas-seluruh-bidang'))
            ->assertOk()
            ->assertJsonStructure([
                'anggota' => [
                    '*' => ['nama_lengkap', 'nama_bidang', 'nama_unit_kerja'],
                ],
            ])
            ->assertJsonPath('anggota.0.nama_lengkap', 'Ani');

        $this->assertStringNotContainsString('081234567890', $respons->getContent());
        $this->assertStringNotContainsString('rahasia@example.test', $respons->getContent());
        $this->assertStringNotContainsString('Alamat Rahasia', $respons->getContent());
    }

    public function test_penasihat_dapat_mengakses_daftar_ringkas(): void
    {
        $penasihat = $this->buatPengguna('Penasehat');

        $this->actingAs($penasihat)
            ->getJson(route('api.anggota.ringkas-seluruh-bidang'))
            ->assertOk()
            ->assertJsonStructure(['anggota']);
    }

    public function test_role_lain_ditolak(): void
    {
        foreach (['Pimpinan Bidang', 'Anggota Bidang'] as $role) {
            $akun = $this->buatPengguna($role);

            $this->actingAs($akun)
                ->getJson(route('api.anggota.ringkas-seluruh-bidang'))
                ->assertForbidden();
        }
    }

    public function test_guest_ditolak(): void
    {
        $this->getJson(route('api.anggota.ringkas-seluruh-bidang'))
            ->assertUnauthorized();
    }

    public function test_anggota_nonaktif_dan_terhapus_tidak_muncul(): void
    {
        $admin = $this->buatPengguna('Admin');

        $this->buatAnggota($admin->id_pengguna, 'Ani Aktif');
        $this->buatAnggota($admin->id_pengguna, 'Budi Nonaktif', 'nonaktif');
        $this->buatAnggota($admin->id_pengguna, 'Citra Terhapus', 'aktif', true);

        $respons = $this->actingAs($admin)
            ->getJson(route('api.anggota.ringkas-seluruh-bidang'))
            ->assertOk();

        $nama = collect($respons->json('anggota'))->pluck('nama_lengkap')->all();

        $this->assertSame(['Ani Aktif'], $nama);
    }

    public function test_daftar_kosong_mengembalikan_array_kosong(): void
    {
        $admin = $this->buatPengguna('Admin');

        $this->actingAs($admin)
            ->getJson(route('api.anggota.ringkas-seluruh-bidang'))
            ->assertOk()
            ->assertExactJson(['anggota' => []]);
    }
}
