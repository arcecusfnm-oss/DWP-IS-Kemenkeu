<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BidangTest extends TestCase
{
    use RefreshDatabase;

    private function buatAkun(string $namaPeran, string $username): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $namaPeran]);
        $bidang = Bidang::firstOrCreate(['nama_bidang' => 'Umum']);

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

    // Pembanding: kalau ini gagal, test lain di bawah belum bermakna
    public function test_admin_dapat_menambah_bidang_valid(): void
    {
        $this->actingAs($this->buatAdmin())
            ->post(route('admin.bidang.store'), ['nama_bidang' => 'Pendidikan'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.bidang.index'));

        $this->assertDatabaseHas('bidang', ['nama_bidang' => 'Pendidikan']);
    }

    // Kosong: string kosong, spasi saja (di-trim jadi null), dan field tidak dikirim
    public static function isianKosong(): array
    {
        return [
            'string kosong' => [['nama_bidang' => '']],
            'spasi saja'    => [['nama_bidang' => '   ']],
            'tidak dikirim' => [[]],
        ];
    }

    #[DataProvider('isianKosong')]
    public function test_bidang_kosong_ditolak(array $data): void
    {
        $this->actingAs($this->buatAdmin())
            ->post(route('admin.bidang.store'), $data)
            ->assertSessionHasErrors(['nama_bidang' => 'Nama bidang wajib diisi.']);

        // Hanya bidang "Umum" milik akun admin yang ada
        $this->assertSame(1, Bidang::count());
    }

    // Nama ganda
    public function test_nama_bidang_ganda_ditolak(): void
    {
        $admin = $this->buatAdmin();
        Bidang::create(['nama_bidang' => 'Pendidikan']);

        $this->actingAs($admin)
            ->post(route('admin.bidang.store'), ['nama_bidang' => 'Pendidikan'])
            ->assertSessionHasErrors(['nama_bidang' => 'Nama bidang tersebut sudah tersedia.']);

        $this->assertSame(1, Bidang::where('nama_bidang', 'Pendidikan')->count());
    }

    // Non-Admin
    public static function peranBukanAdmin(): array
    {
        return [['Anggota'], ['Ketua Bidang']];
    }

    #[DataProvider('peranBukanAdmin')]
    public function test_peran_bukan_admin_ditolak(string $namaPeran): void
    {
        $pengguna = $this->buatAkun($namaPeran, 'bukan.admin');

        $this->actingAs($pengguna)
            ->post(route('admin.bidang.store'), ['nama_bidang' => 'Pendidikan'])
            ->assertForbidden();

        $this->actingAs($pengguna)->get(route('admin.bidang.index'))->assertForbidden();
        $this->actingAs($pengguna)->get(route('admin.bidang.create'))->assertForbidden();

        $this->assertDatabaseMissing('bidang', ['nama_bidang' => 'Pendidikan']);
    }

    // Tanpa sesi
    public function test_tanpa_sesi_ditolak(): void
    {
        $this->post(route('admin.bidang.store'), ['nama_bidang' => 'Pendidikan'])
            ->assertRedirect(route('login'));

        $this->get(route('admin.bidang.index'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('bidang', ['nama_bidang' => 'Pendidikan']);
    }

    // Tipe data bukan string
    public function test_nama_bidang_bukan_string_ditolak(): void
    {
        $this->actingAs($this->buatAdmin())
            ->post(route('admin.bidang.store'), [
                'nama_bidang' => ['Pendidikan'],
            ])
            ->assertSessionHasErrors([
                'nama_bidang' => 'Nama bidang harus berupa teks.',
            ]);

        $this->assertDatabaseMissing('bidang', [
            'nama_bidang' => 'Pendidikan',
        ]);
    }

    // Nama bidang melebihi 255 karakter
    public function test_nama_bidang_lebih_dari_255_karakter_ditolak(): void
    {
        $namaBidang = str_repeat('A', 256);

        $this->actingAs($this->buatAdmin())
            ->post(route('admin.bidang.store'), [
                'nama_bidang' => $namaBidang,
            ])
            ->assertSessionHasErrors([
                'nama_bidang' => 'Nama bidang maksimal 255 karakter.',
            ]);

        $this->assertDatabaseMissing('bidang', [
            'nama_bidang' => $namaBidang,
        ]);
    }
}
