<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AkunRbacTest extends TestCase
{
    use RefreshDatabase;

    private function buatAkun(string $namaPeran, string $username): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $namaPeran]);
        $bidang = Bidang::firstOrCreate(['nama_bidang' => 'Sekretariat']);

        return Pengguna::create([
            'nama_lengkap' => 'Akun ' . $username,
            'nama_pengguna' => $username,
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $peran->id_peran,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    private function buatAdmin(): Pengguna
    {
        return $this->buatAkun('Admin', 'admin.uji');
    }

    private function dataValid(array $ubah = []): array
    {
        Peran::firstOrCreate(['nama_peran' => 'Anggota Bidang']);
        Bidang::firstOrCreate(['nama_bidang' => 'Ekonomi']);

        return array_merge([
            'nama'     => 'Budi Santoso',
            'username' => 'budi.santoso',
            'peran'    => 'Anggota Bidang',
            'bidang'   => 'Ekonomi',
            'password' => 'Rahasia123',
        ], $ubah);
    }

    // Pembanding: kalau ini gagal, test lain di bawah belum bermakna
    public function test_admin_dapat_membuat_akun_valid(): void
    {
        $this->actingAs($this->buatAdmin())
            ->post(route('admin.akun.store'), $this->dataValid())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengguna', ['nama_pengguna' => 'budi.santoso']);
    }

    // AC B-004 no. 1: kolom kosong
    public static function kolomWajib(): array
    {
        return [['nama'], ['username'], ['peran'], ['bidang'], ['password']];
    }

    #[DataProvider('kolomWajib')]
    public function test_kolom_kosong_ditolak(string $kolom): void
    {
        $this->actingAs($this->buatAdmin())
            ->post(route('admin.akun.store'), $this->dataValid([$kolom => '']))
            ->assertSessionHasErrors($kolom);

        $this->assertSame(1, Pengguna::count()); // hanya admin pelaku
    }

    // AC B-004 no. 2: nama pengguna ganda
    public function test_nama_pengguna_ganda_ditolak(): void
    {
        $admin = $this->buatAdmin();
        $this->buatAkun('Anggota Bidang', 'budi.santoso');

        $this->actingAs($admin)
            ->post(route('admin.akun.store'), $this->dataValid())
            ->assertSessionHasErrors(['username' => 'Nama pengguna sudah digunakan']);

        $this->assertSame(1, Pengguna::where('nama_pengguna', 'budi.santoso')->count());
    }

    // AC B-004 no. 5: peran bukan Admin
    public static function peranBukanAdmin(): array
    {
        return [['Anggota Bidang'], ['Penanggung Jawab']];
    }

    #[DataProvider('peranBukanAdmin')]
    public function test_peran_bukan_admin_ditolak(string $namaPeran): void
    {
        $pengguna = $this->buatAkun($namaPeran, 'bukan.admin');

        $this->actingAs($pengguna)
            ->post(route('admin.akun.store'), $this->dataValid())
            ->assertForbidden();

        $this->actingAs($pengguna)
            ->get(route('admin.akun.create'))
            ->assertForbidden();

        $this->assertDatabaseMissing('pengguna', ['nama_pengguna' => 'budi.santoso']);
    }

    // AC B-004 no. 5: tanpa sesi
    public function test_tanpa_sesi_ditolak(): void
    {
        $this->post(route('admin.akun.store'), $this->dataValid())
            ->assertRedirect(route('login'));

        $this->get(route('admin.akun.create'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('pengguna', ['nama_pengguna' => 'budi.santoso']);
    }

    // AC B-004 no. 4: kata sandi minimal 8 karakter dan disimpan sebagai hash
    public function test_kata_sandi_7_karakter_ditolak(): void
    {
        $this->actingAs($this->buatAdmin())
            ->post(route('admin.akun.store'), $this->dataValid(['password' => 'Abcde12']))
            ->assertSessionHasErrors(['password' => 'Kata sandi awal minimal 8 karakter']);

        $this->assertDatabaseMissing('pengguna', ['nama_pengguna' => 'budi.santoso']);
    }

    public function test_kata_sandi_8_karakter_diterima_dan_disimpan_sebagai_hash(): void
    {
        $this->actingAs($this->buatAdmin())
            ->post(route('admin.akun.store'), $this->dataValid(['password' => 'Abcdef12']))
            ->assertSessionHasNoErrors();

        $akun = Pengguna::where('nama_pengguna', 'budi.santoso')->first();

        $this->assertNotNull($akun);
        $this->assertNotSame('Abcdef12', $akun->kata_sandi_hash);
        $this->assertTrue(Hash::check('Abcdef12', $akun->kata_sandi_hash));
    }
}