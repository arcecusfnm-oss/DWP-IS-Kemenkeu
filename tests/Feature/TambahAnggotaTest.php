<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-28 (B-016): pengujian buat anggota (T-26).
 */
class TambahAnggotaTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Helper ----------

    private function buatBidang(string $nama = 'Sosial Budaya'): int
    {
        return DB::table('bidang')->where('nama_bidang', $nama)->value('id_bidang')
            ?? DB::table('bidang')->insertGetId([
                'nama_bidang' => $nama,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
    }

    private function buatPeran(string $nama): int
    {
        return DB::table('peran')->where('nama_peran', $nama)->value('id_peran')
            ?? DB::table('peran')->insertGetId(['nama_peran' => $nama]);
    }

    private function buatPengguna(string $peran, int $idBidang, string $username): Pengguna
    {
        return Pengguna::create([
            'nama_lengkap'           => 'Nama ' . $username,
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => 'Password123!',
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $this->buatPeran($peran),
            'id_bidang'              => $idBidang,
        ]);
    }

    private function buatAdmin(): Pengguna
    {
        return $this->buatPengguna('Admin', $this->buatBidang('Sekretariat'), 'admin_uji28');
    }

    private function payload(Pengguna $akun, int $idBidang, array $ubah = []): array
    {
        return array_merge([
            'id_pengguna'   => $akun->id_pengguna,
            'nama'          => $akun->nama_lengkap,
            'id_bidang'     => $idBidang,
            'nomor_telepon' => '08112233445',
        ], $ubah);
    }

    // ---------- Skenario 1: berhasil ----------

    public function test_admin_berhasil_menambahkan_anggota(): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $res = $this->actingAs($admin)->post(route('admin.anggota.store'), $this->payload($akun, $bidang, [
            'email'              => 'ani@kemenkeu.go.id',
            'tanggal_lahir'      => '1990-05-17',
            'tingkat_pendidikan' => 'S1 (Sarjana)',
        ]));

        $res->assertRedirect(route('admin.anggota.index'));
        $res->assertSessionHas('success');
        $res->assertSessionHasNoErrors();

        $this->assertDatabaseHas('anggota', [
            'id_pengguna'        => $akun->id_pengguna,
            'nama_lengkap'       => $akun->nama_lengkap,
            'nomor_telepon'      => '08112233445',
            'email'              => 'ani@kemenkeu.go.id',
            'tingkat_pendidikan' => 'S1 (Sarjana)',
            'id_bidang'          => $bidang,
            'status'             => 'aktif',
            'dibuat_oleh'        => $admin->id_pengguna,
        ]);
    }

    // ---------- Skenario 2: kolom wajib kosong ----------

    public static function kolomWajib(): array
    {
        return [
            'nama kosong'          => ['nama'],
            'bidang kosong'        => ['id_bidang'],
            'nomor telepon kosong' => ['nomor_telepon'],
        ];
    }

    #[DataProvider('kolomWajib')]
    public function test_kolom_wajib_kosong_ditolak(string $kolom): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $res = $this->actingAs($admin)->post(
            route('admin.anggota.store'),
            $this->payload($akun, $bidang, [$kolom => ''])
        );

        $res->assertSessionHasErrors($kolom);
        $this->assertDatabaseCount('anggota', 0);
    }

    // ---------- Skenario 3: bidang tidak tersedia ----------

    public function test_bidang_tidak_tersedia_ditolak(): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $res = $this->actingAs($admin)->post(
            route('admin.anggota.store'),
            $this->payload($akun, 9999)
        );

        $res->assertSessionHasErrors(['id_bidang' => 'Bidang tidak tersedia.']);
        $this->assertDatabaseCount('anggota', 0);
    }

    // ---------- Skenario 4: pengguna bukan Admin ----------

    public function test_pengguna_bukan_admin_ditolak(): void
    {
        $bidang     = $this->buatBidang();
        $bukanAdmin = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_biasa');
        $akun       = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $this->actingAs($bukanAdmin)
            ->post(route('admin.anggota.store'), $this->payload($akun, $bidang))
            ->assertForbidden();

        $this->actingAs($bukanAdmin)
            ->get(route('admin.anggota.create'))
            ->assertForbidden();

        $this->assertDatabaseCount('anggota', 0);
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $this->post(route('admin.anggota.store'), [])->assertRedirect(route('login'));
        $this->get(route('admin.anggota.create'))->assertRedirect(route('login'));

        $this->assertDatabaseCount('anggota', 0);
    }

    // ---------- Validasi tambahan (AC 3 dan AC 5) ----------

    public static function teleponTidakValid(): array
    {
        return [
            'berisi huruf'       => ['sssssssss'],
            'campuran'           => ['0811abc4455'],
            '7 digit'            => ['1234567'],
            '16 digit'           => ['1234567890123456'],
            'ada tanda hubung'   => ['0811-2233-445'],
        ];
    }

    #[DataProvider('teleponTidakValid')]
    public function test_nomor_telepon_tidak_valid_ditolak(string $telepon): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $res = $this->actingAs($admin)->post(
            route('admin.anggota.store'),
            $this->payload($akun, $bidang, ['nomor_telepon' => $telepon])
        );

        $res->assertSessionHasErrors(['nomor_telepon' => 'Nomor telepon harus 8 sampai 15 digit angka.']);
        $this->assertDatabaseCount('anggota', 0);
    }

    public static function teleponBatasValid(): array
    {
        return [
            '8 digit'  => ['08123456'],
            '15 digit' => ['081234567890123'],
        ];
    }

    #[DataProvider('teleponBatasValid')]
    public function test_nomor_telepon_batas_8_dan_15_digit_diterima(string $telepon): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $this->actingAs($admin)
            ->post(route('admin.anggota.store'), $this->payload($akun, $bidang, ['nomor_telepon' => $telepon]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('anggota', ['nomor_telepon' => $telepon]);
    }

    public function test_karakter_terlarang_ditolak(): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $res = $this->actingAs($admin)->post(
            route('admin.anggota.store'),
            $this->payload($akun, $bidang, ['alamat_lengkap' => '<b>tes</b>'])
        );

        $res->assertSessionHasErrors('alamat_lengkap');
        $this->assertDatabaseCount('anggota', 0);
    }

    public function test_akun_yang_sudah_terhubung_ditolak(): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $this->actingAs($admin)->post(route('admin.anggota.store'), $this->payload($akun, $bidang));
        $res = $this->actingAs($admin)->post(route('admin.anggota.store'), $this->payload($akun, $bidang));

        $res->assertSessionHasErrors('id_pengguna');
        $this->assertDatabaseCount('anggota', 1);
    }

    // ---------- Formulir ----------

    public function test_formulir_hanya_menampilkan_akun_yang_belum_menjadi_anggota(): void
    {
        $bidang  = $this->buatBidang();
        $admin   = $this->buatAdmin();
        $akunSatu = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');
        $this->buatPengguna('Anggota Bidang', $bidang, 'akun_dua');

        $this->actingAs($admin)->get(route('admin.anggota.create'))
            ->assertOk()
            ->assertSee('akun_satu')
            ->assertSee('akun_dua');

        $this->actingAs($admin)->post(route('admin.anggota.store'), $this->payload($akunSatu, $bidang));

        $this->actingAs($admin)->get(route('admin.anggota.create'))
            ->assertOk()
            ->assertDontSee('akun_satu')
            ->assertSee('akun_dua');
    }

    // ---------- Log (AC 6) ----------

    public function test_pembuatan_anggota_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $this->actingAs($admin)->post(route('admin.anggota.store'), $this->payload($akun, $bidang, [
            'email'          => 'ani@kemenkeu.go.id',
            'alamat_lengkap' => 'Jl. Contoh No. 1',
        ]));

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($admin, $akun) {
                $json = json_encode($konteks);

                return $pesan === 'Admin membuat anggota baru'
                    && $konteks['admin_id'] === $admin->id_pengguna
                    && $konteks['nama_anggota'] === $akun->nama_lengkap
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, '08112233445')
                    && ! str_contains($json, 'ani@kemenkeu.go.id')
                    && ! str_contains($json, 'Jl. Contoh');
            })
            ->once();
    }
}