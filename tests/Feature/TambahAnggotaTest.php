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
 *
 * Disesuaikan dengan perubahan form/controller:
 * - Bidang tidak wajib (Penasihat, Ketua DWP, Wakil DWP tidak terikat bidang).
 * - Nama dan bidang diambil dari akun di server (anti-tamper).
 * - Nama panggilan dinormalisasi berawalan "Ny.".
 * - Kolom "bekerja" (Ya/Tidak); instansi dikosongkan bila Tidak.
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

    private function buatPengguna(string $peran, ?int $idBidang, string $username): Pengguna
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

    private function payload(Pengguna $akun, ?int $idBidang, array $ubah = []): array
    {
        return array_merge([
            'id_pengguna'   => $akun->id_pengguna,
            'nama'          => $akun->nama_lengkap,
            'id_bidang'     => $idBidang,
            'nomor_telepon' => '08112233445',
        ], $ubah);
    }

    private function nilaiAnggota(Pengguna $akun, string $kolom): mixed
    {
        return DB::table('anggota')->where('id_pengguna', $akun->id_pengguna)->value($kolom);
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
        $res->assertSessionHas('success', 'Data berhasil disimpan.');
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

    // ---------- Skenario 1b: akun tanpa bidang (Penasihat) ----------

    public function test_akun_tanpa_bidang_tetap_bisa_disimpan(): void
    {
        $admin     = $this->buatAdmin();
        $penasihat = $this->buatPengguna('Penasihat', null, 'penasihat_uji');

        $res = $this->actingAs($admin)->post(
            route('admin.anggota.store'),
            $this->payload($penasihat, null)
        );

        $res->assertRedirect(route('admin.anggota.index'));
        $res->assertSessionHas('success', 'Data berhasil disimpan.');
        $res->assertSessionHasNoErrors();

        $this->assertDatabaseHas('anggota', [
            'id_pengguna' => $penasihat->id_pengguna,
            'status'      => 'aktif',
        ]);
        $this->assertNull($this->nilaiAnggota($penasihat, 'id_bidang'));
    }

    // ---------- Skenario 2: kolom wajib kosong ----------
    // Bidang tidak termasuk kolom wajib lagi (lihat test akun tanpa bidang di atas).

    public static function kolomWajib(): array
    {
        return [
            'nama kosong'          => ['nama'],
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

    // ---------- Anti-tamper: nama dan bidang dari akun ----------

    public function test_bidang_dan_nama_diambil_dari_akun_bukan_dari_input(): void
    {
        $bidangAsli = $this->buatBidang('Ekonomi');
        $bidangLain = $this->buatBidang('Pendidikan');
        $admin      = $this->buatAdmin();
        $akun       = $this->buatPengguna('Anggota Bidang', $bidangAsli, 'akun_satu');

        $this->actingAs($admin)
            ->post(route('admin.anggota.store'), $this->payload($akun, $bidangLain, ['nama' => 'Nama Palsu']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('anggota', [
            'id_pengguna'  => $akun->id_pengguna,
            'nama_lengkap' => $akun->nama_lengkap,
            'id_bidang'    => $bidangAsli,
        ]);
        $this->assertDatabaseMissing('anggota', ['nama_lengkap' => 'Nama Palsu']);
    }

    public function test_akun_tanpa_bidang_tidak_bisa_diberi_bidang_lewat_input(): void
    {
        $bidangLain = $this->buatBidang('Pendidikan');
        $admin      = $this->buatAdmin();
        $penasihat  = $this->buatPengguna('Penasihat', null, 'penasihat_uji');

        $this->actingAs($admin)
            ->post(route('admin.anggota.store'), $this->payload($penasihat, $bidangLain))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->nilaiAnggota($penasihat, 'id_bidang'));
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

    // ---------- Nama panggilan berawalan "Ny." ----------

    public static function namaPanggilan(): array
    {
        return [
            'tanpa awalan'          => ['Wiwik Suryo', 'Ny. Wiwik Suryo'],
            'sudah berawalan Ny.'   => ['Ny. Wiwik Suryo', 'Ny. Wiwik Suryo'],
            'Ny tanpa titik'        => ['Ny Wiwik', 'Ny. Wiwik'],
            'huruf kecil'           => ['ny. wiwik', 'Ny. wiwik'],
            'awalan Nyonya'         => ['Nyonya Wiwik', 'Ny. Wiwik'],
            'Nyoman tidak terpotong' => ['Nyoman', 'Ny. Nyoman'],
            'spasi ganda dirapikan' => ['Wiwik   Suryo', 'Ny. Wiwik Suryo'],
            'kosong menjadi NULL'   => ['', null],
        ];
    }

    #[DataProvider('namaPanggilan')]
    public function test_nama_panggilan_dinormalisasi(string $masuk, ?string $harapan): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $this->actingAs($admin)
            ->post(route('admin.anggota.store'), $this->payload($akun, $bidang, ['nama_panggilan' => $masuk]))
            ->assertSessionHasNoErrors();

        $this->assertSame($harapan, $this->nilaiAnggota($akun, 'nama_panggilan'));
    }

    // ---------- Bekerja (Ya/Tidak) ----------

    public function test_bekerja_ya_menyimpan_instansi(): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $this->actingAs($admin)
            ->post(route('admin.anggota.store'), $this->payload($akun, $bidang, [
                'bekerja'        => 'Ya',
                'instansi_kerja' => 'Kementerian Keuangan',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Ya', $this->nilaiAnggota($akun, 'bekerja'));
        $this->assertSame('Kementerian Keuangan', $this->nilaiAnggota($akun, 'instansi_kerja'));
    }

    public function test_bekerja_tidak_mengosongkan_instansi_di_server(): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $this->actingAs($admin)
            ->post(route('admin.anggota.store'), $this->payload($akun, $bidang, [
                'bekerja'        => 'Tidak',
                'instansi_kerja' => 'Kementerian Keuangan',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Tidak', $this->nilaiAnggota($akun, 'bekerja'));
        $this->assertNull($this->nilaiAnggota($akun, 'instansi_kerja'));
    }

    public function test_pilihan_bekerja_tidak_valid_ditolak(): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        $res = $this->actingAs($admin)->post(
            route('admin.anggota.store'),
            $this->payload($akun, $bidang, ['bekerja' => 'Mungkin'])
        );

        $res->assertSessionHasErrors(['bekerja' => 'Pilihan bekerja tidak valid.']);
        $this->assertDatabaseCount('anggota', 0);
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

    public function test_akun_milik_anggota_yang_dihapus_tetap_tidak_muncul_dan_tidak_bisa_dipakai(): void
    {
        $bidang = $this->buatBidang();
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_satu');

        // Anggota yang sudah di-soft-delete: id_pengguna unik, jadi akun tetap terpakai.
        DB::table('anggota')->insert([
            'id_pengguna'   => $akun->id_pengguna,
            'nama_lengkap'  => 'Anggota Lama',
            'nomor_telepon' => '08112233445',
            'status'        => 'aktif',
            'dihapus_pada'  => now(),
            'dibuat_oleh'   => $admin->id_pengguna,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.anggota.create'))
            ->assertOk()
            ->assertDontSee('akun_satu');

        $this->actingAs($admin)
            ->post(route('admin.anggota.store'), $this->payload($akun, $bidang))
            ->assertSessionHasErrors('id_pengguna');
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