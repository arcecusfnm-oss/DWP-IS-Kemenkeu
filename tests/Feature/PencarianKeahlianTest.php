<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PencarianKeahlianTest extends TestCase
{
    use RefreshDatabase;

    private function buatPengguna(string $namaPeran): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $namaPeran]);
        $username = 'uji.keahlian.' . uniqid();

        $id = DB::table('pengguna')->insertGetId([
            'nama_lengkap' => 'Akun Uji',
            'nama_pengguna' => $username,
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

    private function buatAnggota(
        int $dibuatOleh,
        string $nama,
        string $keahlian,
        string $status = 'aktif',
        bool $dihapus = false,
        ?int $idBidang = null,
        ?int $idUnit = null
    ): void {
        DB::table('anggota')->insert([
            'nama_lengkap' => $nama,
            'nomor_telepon' => '081234567890',
            'email' => 'rahasia@example.test',
            'alamat_lengkap' => 'Alamat Rahasia',
            'keahlian' => $keahlian,
            'id_bidang' => $idBidang,
            'id_unit_kerja' => $idUnit,
            'status' => $status,
            'dibuat_oleh' => $dibuatOleh,
            'dihapus_pada' => $dihapus ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_dapat_mencari_anggota_berdasarkan_keahlian(): void
    {
        $admin = $this->buatPengguna('Admin');

        $bidangId = DB::table('bidang')->insertGetId([
            'nama_bidang' => 'Ekonomi',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_bidang');

        $unitId = DB::table('unit_kerja')->insertGetId([
            'nama_unit_kerja' => 'Biro Uji',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_unit_kerja');

        $this->buatAnggota(
            $admin->id_pengguna,
            'Ani',
            'Desain Grafis dan Ilustrasi',
            'aktif',
            false,
            $bidangId,
            $unitId
        );

        $respons = $this->actingAs($admin)
            ->getJson(route('api.anggota.cari-keahlian', ['q' => 'Grafis']))
            ->assertOk()
            ->assertJsonStructure([
                'hasil' => [
                    '*' => ['nama_lengkap', 'nama_bidang', 'nama_unit_kerja'],
                ],
            ])
            ->assertJsonPath('hasil.0.nama_lengkap', 'Ani')
            ->assertJsonPath('hasil.0.nama_bidang', 'Ekonomi')
            ->assertJsonPath('hasil.0.nama_unit_kerja', 'Biro Uji');

        $this->assertStringNotContainsString('081234567890', $respons->getContent());
        $this->assertStringNotContainsString('rahasia@example.test', $respons->getContent());
        $this->assertStringNotContainsString('Alamat Rahasia', $respons->getContent());
    }

    public function test_penasihat_dapat_mengakses_pencarian_keahlian(): void
    {
        $penasihat = $this->buatPengguna('Penasehat');

        $this->actingAs($penasihat)
            ->getJson(route('api.anggota.cari-keahlian', ['q' => 'Desain']))
            ->assertOk()
            ->assertJsonStructure(['hasil']);
    }

    public function test_role_selain_admin_dan_penasihat_ditolak(): void
    {
        foreach (['Pimpinan Bidang', 'Anggota Bidang'] as $i => $namaPeran) {
            $akun = $this->buatPengguna($namaPeran);

            $this->actingAs($akun)
                ->getJson(route('api.anggota.cari-keahlian', ['q' => 'Desain']))
                ->assertForbidden();
        }
    }

    public function test_tamu_tidak_dapat_mengakses_pencarian(): void
    {
        $this->getJson(route('api.anggota.cari-keahlian', ['q' => 'Desain']))
            ->assertUnauthorized();
    }

    public function test_parameter_pencarian_wajib_dan_minimal_dua_karakter(): void
    {
        $admin = $this->buatPengguna('Admin');

        $this->actingAs($admin)
            ->getJson(route('api.anggota.cari-keahlian'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);

        $this->getJson(route('api.anggota.cari-keahlian', ['q' => 'A']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_hanya_anggota_aktif_dan_belum_dihapus_yang_ditemukan(): void
    {
        $admin = $this->buatPengguna('Admin');

        $this->buatAnggota($admin->id_pengguna, 'Ani Aktif', 'Desain Grafis');
        $this->buatAnggota($admin->id_pengguna, 'Budi Nonaktif', 'Desain Grafis', 'nonaktif');
        $this->buatAnggota($admin->id_pengguna, 'Citra Terhapus', 'Desain Grafis', 'aktif', true);

        $respons = $this->actingAs($admin)
            ->getJson(route('api.anggota.cari-keahlian', ['q' => 'Grafis']))
            ->assertOk();

        $nama = collect($respons->json('hasil'))->pluck('nama_lengkap')->all();

        $this->assertSame(['Ani Aktif'], $nama);
    }

    public function test_pencarian_tanpa_hasil_mengembalikan_array_kosong(): void
    {
        $admin = $this->buatPengguna('Admin');

        $this->actingAs($admin)
            ->getJson(route('api.anggota.cari-keahlian', ['q' => 'TidakAdaSkill']))
            ->assertOk()
            ->assertExactJson(['hasil' => []]);
    }
}
