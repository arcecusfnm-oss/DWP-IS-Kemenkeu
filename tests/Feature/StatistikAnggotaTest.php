<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StatistikAnggotaTest extends TestCase
{
    use RefreshDatabase;

    private function buatPengguna(string $namaPeran, string $username): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $namaPeran]);

        $id = DB::table('pengguna')->insertGetId([
            'nama_lengkap' => 'Akun ' . $username,
            'nama_pengguna' => $username,
            'kata_sandi_hash' => Hash::make('Rahasia123!'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => 0,
            'id_peran' => $peran->id_peran,
            'id_bidang' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_pengguna');

        return Pengguna::find($id);
    }

    private function buatAnggota(
        string $nama,
        ?int $idBidang,
        int $dibuatOleh,
        string $status = 'aktif',
        bool $dihapus = false
    ): void {
        DB::table('anggota')->insert([
            'nama_lengkap' => $nama,
            'nomor_telepon' => '081234567890',
            'id_bidang' => $idBidang,
            'status' => $status,
            'dibuat_oleh' => $dibuatOleh,
            'dihapus_pada' => $dihapus ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_dapat_mengakses_statistik_anggota(): void
    {
        $admin = $this->buatPengguna('Admin', 'admin.statistik');

        $this->actingAs($admin)
            ->getJson(route('api.statistik.anggota-per-bidang'))
            ->assertOk()
            ->assertJsonStructure(['statistik']);
    }

    public function test_penasihat_dapat_mengakses_statistik_anggota(): void
    {
        $penasihat = $this->buatPengguna('Penasihat', 'penasihat.statistik');

        $this->actingAs($penasihat)
            ->getJson(route('api.statistik.anggota-per-bidang'))
            ->assertOk()
            ->assertJsonStructure(['statistik']);
    }

    public function test_role_selain_admin_dan_penasihat_ditolak(): void
    {
        foreach (['Pimpinan Bidang', 'Anggota Bidang'] as $i => $namaPeran) {
            $akun = $this->buatPengguna($namaPeran, 'role.statistik.' . $i);

            $this->actingAs($akun)
                ->getJson(route('api.statistik.anggota-per-bidang'))
                ->assertForbidden();
        }
    }

    public function test_tamu_tidak_dapat_mengakses_statistik(): void
    {
        $this->getJson(route('api.statistik.anggota-per-bidang'))
            ->assertUnauthorized();
    }

    public function test_statistik_hanya_menghitung_anggota_aktif_yang_belum_dihapus(): void
    {
        $admin = $this->buatPengguna('Admin', 'admin.hitung');
        $ekonomi = Bidang::firstOrCreate(['nama_bidang' => 'Ekonomi']);
        $pendidikan = Bidang::firstOrCreate(['nama_bidang' => 'Pendidikan']);
        $kosong = Bidang::firstOrCreate(['nama_bidang' => 'Bidang Kosong']);

        $this->buatAnggota('Ani', $ekonomi->id_bidang, $admin->id_pengguna);
        $this->buatAnggota('Budi', $ekonomi->id_bidang, $admin->id_pengguna);
        $this->buatAnggota('Anggota Nonaktif', $ekonomi->id_bidang, $admin->id_pengguna, 'nonaktif');
        $this->buatAnggota('Anggota Terhapus', $ekonomi->id_bidang, $admin->id_pengguna, 'aktif', true);
        $this->buatAnggota('Citra', $pendidikan->id_bidang, $admin->id_pengguna);

        $respons = $this->actingAs($admin)
            ->getJson(route('api.statistik.anggota-per-bidang'))
            ->assertOk();

        $data = collect($respons->json('statistik'))
            ->keyBy('nama_bidang');

        $this->assertSame(2, (int) $data['Ekonomi']['jumlah_anggota']);
        $this->assertSame(1, (int) $data['Pendidikan']['jumlah_anggota']);
        $this->assertSame(0, (int) $data['Bidang Kosong']['jumlah_anggota']);
        $this->assertCount(3, $data);
    }
}
