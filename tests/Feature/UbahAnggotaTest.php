<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Database\Seeders\UnitKerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * T-49 (B-021): pengujian endpoint ubah data anggota.
 */
class UbahAnggotaTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Helper ----------

    private function buatBidang(string $nama): int
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

    private function buatPengguna(string $peran, ?int $idBidang, string $username, array $ubah = []): Pengguna
    {
        return Pengguna::create(array_merge([
            'nama_lengkap'           => 'Nama ' . $username,
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => 'Password123!',
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $this->buatPeran($peran),
            'id_bidang'              => $idBidang,
        ], $ubah));
    }

    private function buatAdmin(): Pengguna
    {
        return $this->buatPengguna('Admin', $this->buatBidang('Sekretariat'), 'admin_uji49');
    }

    private function buatAnggota(?int $idBidang, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('anggota')->insertGetId(array_merge([
            'nama_lengkap'       => 'Ny. Ratna Dewi',
            'nama_panggilan'     => 'Ny. Ratna',
            'nomor_telepon'      => '081234567890',
            'tempat_lahir'       => 'Jakarta',
            'tanggal_lahir'      => '1985-05-10',
            'alamat_lengkap'     => 'Jl. Lapangan Banteng No. 1',
            'email'              => 'ratna.lama@kemenkeu.go.id',
            'nama_suami'         => 'Budi Santoso',
            'id_unit_kerja'      => null,
            'tingkat_pendidikan' => 'S1 (Sarjana)',
            'bidang_pendidikan'  => 'Akuntansi',
            'keahlian'           => 'Administrasi',
            'bidang_usaha'       => null,
            'bekerja'            => 'Tidak',
            'instansi_kerja'     => null,
            'id_bidang'          => $idBidang,
            'status'             => 'aktif',
            'dibuat_oleh'        => $dibuatOleh,
            'created_at'         => now(),
            'updated_at'         => now(),
        ], $ubah), 'id_anggota');
    }

    private function payloadValid(int $idUnitKerja, array $timpa = []): array
    {
        return array_merge([
            'nomor_telepon'      => '089876543210',
            'nama_panggilan'     => 'ratna dewi',
            'tempat_lahir'       => 'Bandung',
            'tanggal_lahir'      => '1986-08-17',
            'email'              => 'ratna.baru@kemenkeu.go.id',
            'alamat_lengkap'     => 'Jl. Dr. Wahidin No. 2, Jakarta Pusat',
            'nama_suami'         => 'Drs. Budi Santoso',
            'id_unit_kerja'      => $idUnitKerja,
            'tingkat_pendidikan' => 'S2 (Magister)',
            'bidang_pendidikan'  => 'Manajemen Keuangan',
            'keahlian'           => 'Perencanaan Anggaran',
            'bidang_usaha'       => 'Kuliner',
            'bekerja'            => 'Ya',
            'instansi_kerja'     => 'Kementerian Keuangan',
        ], $timpa);
    }

    // ---------- AC 1: Skenario utama ----------

    public function test_admin_dapat_membuka_formulir_ubah_data_anggota(): void
    {
        $this->seed(UnitKerjaSeeder::class);

        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nama_lengkap'  => 'Ny. Sari Melati',
            'nomor_telepon' => '081211223344',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.anggota.edit', $anggota))
            ->assertOk()
            ->assertSee('Ny. Sari Melati')
            ->assertSee('081211223344')
            ->assertSee('Pendidikan');
    }

    public function test_admin_berhasil_mengubah_data_anggota_dan_tampil_di_daftar_anggota(): void
    {
        $this->seed(UnitKerjaSeeder::class);
        $unitKerja = DB::table('unit_kerja')->first();

        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nama_lengkap' => 'Ny. Ratna Dewi',
            'id_unit_kerja' => null,
        ]);

        $payload = $this->payloadValid($unitKerja->id_unit_kerja);

        $res = $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), $payload);

        $res->assertRedirect(route('admin.anggota.index'));
        $res->assertSessionHas('success', 'Data anggota berhasil diperbarui.');
        $res->assertSessionHasNoErrors();

        // Verifikasi seluruh atribut di basis data
        $tersimpan = DB::table('anggota')->where('id_anggota', $anggota)->first();
        $this->assertSame('Ny. Ratna Dewi', $tersimpan->nama_lengkap);
        $this->assertSame($bidang, (int) $tersimpan->id_bidang);
        $this->assertSame('089876543210', $tersimpan->nomor_telepon);
        $this->assertSame('Ny. ratna dewi', $tersimpan->nama_panggilan);
        $this->assertSame('Bandung', $tersimpan->tempat_lahir);
        $this->assertSame('1986-08-17', $tersimpan->tanggal_lahir);
        $this->assertSame('ratna.baru@kemenkeu.go.id', $tersimpan->email);
        $this->assertSame('Jl. Dr. Wahidin No. 2, Jakarta Pusat', $tersimpan->alamat_lengkap);
        $this->assertSame('Drs. Budi Santoso', $tersimpan->nama_suami);
        $this->assertSame((int) $unitKerja->id_unit_kerja, (int) $tersimpan->id_unit_kerja);
        $this->assertSame('S2 (Magister)', $tersimpan->tingkat_pendidikan);
        $this->assertSame('Manajemen Keuangan', $tersimpan->bidang_pendidikan);
        $this->assertSame('Perencanaan Anggaran', $tersimpan->keahlian);
        $this->assertSame('Kuliner', $tersimpan->bidang_usaha);
        $this->assertSame('Ya', $tersimpan->bekerja);
        $this->assertSame('Kementerian Keuangan', $tersimpan->instansi_kerja);

        // Verifikasi perubahan langsung tampil pada daftar anggota
        $this->actingAs($admin)
            ->get(route('admin.anggota.index', ['q' => 'Ratna Dewi']))
            ->assertOk()
            ->assertSee('Ny. Ratna Dewi')
            ->assertSee('Ny. ratna dewi')
            ->assertSee('089876543210')
            ->assertSee('Lengkap');
    }

    public function test_bekerja_tidak_mengosongkan_instansi_kerja_di_server(): void
    {
        $bidang  = $this->buatBidang('Ekonomi');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'bekerja'        => 'Ya',
            'instansi_kerja' => 'Instansi Lama',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon'  => '081234567890',
                'bekerja'        => 'Tidak',
                'instansi_kerja' => 'Seharusnya Dikosongkan',
            ])
            ->assertSessionHasNoErrors();

        $tersimpan = DB::table('anggota')->where('id_anggota', $anggota)->first();
        $this->assertSame('Tidak', $tersimpan->bekerja);
        $this->assertNull($tersimpan->instansi_kerja);
    }

    public function test_nama_asli_dan_bidang_tidak_berubah_lewat_endpoint_ubah(): void
    {
        $bidangAsli = $this->buatBidang('Pendidikan');
        $bidangLain = $this->buatBidang('Ekonomi');
        $admin      = $this->buatAdmin();
        $anggota    = $this->buatAnggota($bidangAsli, $admin->id_pengguna, [
            'nama_lengkap' => 'Ny. Nama Tetap',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nama'          => 'Nama Manipulasi',
                'nama_lengkap'  => 'Nama Manipulasi',
                'id_bidang'     => $bidangLain,
                'nomor_telepon' => '081299998888',
            ])
            ->assertSessionHasNoErrors();

        $tersimpan = DB::table('anggota')->where('id_anggota', $anggota)->first();
        $this->assertSame('Ny. Nama Tetap', $tersimpan->nama_lengkap);
        $this->assertSame($bidangAsli, (int) $tersimpan->id_bidang);
        $this->assertSame('081299998888', $tersimpan->nomor_telepon);
    }

    // ---------- AC 2: Skenario gagal (validasi kolom wajib & aturan template) ----------

    public function test_nomor_telepon_kosong_atau_tidak_valid_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nomor_telepon' => '081234567890',
        ]);

        $kasusTidakValid = ['', '0812abc', '1234567', '1234567890123456', '0812-3456-789'];

        foreach ($kasusTidakValid as $telepon) {
            $this->actingAs($admin)
                ->put(route('admin.anggota.update', $anggota), [
                    'nomor_telepon' => $telepon,
                ])
                ->assertSessionHasErrors('nomor_telepon');
        }

        $this->assertSame(
            '081234567890',
            DB::table('anggota')->where('id_anggota', $anggota)->value('nomor_telepon')
        );
    }

    public function test_unit_kerja_di_luar_13_unit_kemenkeu_ditolak(): void
    {
        $this->seed(UnitKerjaSeeder::class);

        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'id_unit_kerja' => null,
        ]);

        $res = $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon' => '081234567890',
                'id_unit_kerja' => 99999,
            ]);

        $res->assertSessionHasErrors(['id_unit_kerja' => 'Unit kerja tidak tersedia.']);
        $this->assertNull(
            DB::table('anggota')->where('id_anggota', $anggota)->value('id_unit_kerja')
        );
    }

    public function test_pilihan_tetap_format_email_dan_karakter_terlarang_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'email'              => 'valid@kemenkeu.go.id',
            'tingkat_pendidikan' => 'S1 (Sarjana)',
            'bekerja'            => 'Ya',
        ]);

        // Email tidak valid
        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon' => '081234567890',
                'email'         => 'bukan-email',
            ])
            ->assertSessionHasErrors(['email' => 'Format email tidak valid.']);

        // Tingkat pendidikan di luar pilihan tetap
        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon'      => '081234567890',
                'tingkat_pendidikan' => 'SD',
            ])
            ->assertSessionHasErrors(['tingkat_pendidikan' => 'Tingkat pendidikan tidak valid.']);

        // Pilihan bekerja di luar Ya/Tidak
        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon' => '081234567890',
                'bekerja'       => 'Mungkin',
            ])
            ->assertSessionHasErrors(['bekerja' => 'Pilihan bekerja tidak valid.']);

        // Tanggal lahir melebihi hari ini
        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon' => '081234567890',
                'tanggal_lahir' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['tanggal_lahir' => 'Tanggal lahir tidak boleh melebihi hari ini.']);

        // Karakter terlarang (< >)
        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon'  => '081234567890',
                'alamat_lengkap' => '<script>alert(1)</script>',
            ])
            ->assertSessionHasErrors('alamat_lengkap');

        // Data lama tetap utuh
        $tersimpan = DB::table('anggota')->where('id_anggota', $anggota)->first();
        $this->assertSame('valid@kemenkeu.go.id', $tersimpan->email);
        $this->assertSame('S1 (Sarjana)', $tersimpan->tingkat_pendidikan);
        $this->assertSame('Ya', $tersimpan->bekerja);
    }

    // ---------- AC 3: Skenario gagal (ID anggota tidak ada) ----------

    public function test_ubah_anggota_dengan_id_tidak_ada_atau_terhapus_ditolak_rapi(): void
    {
        $bidang   = $this->buatBidang('Pendidikan');
        $admin    = $this->buatAdmin();
        $terhapus = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'dihapus_pada' => now(),
        ]);

        // Halaman edit pada ID tidak ada
        $this->actingAs($admin)
            ->get(route('admin.anggota.edit', 99999))
            ->assertRedirect(route('admin.anggota.index'))
            ->assertSessionHas('error', 'Data anggota tidak ditemukan.');

        // Request form biasa pada ID tidak ada
        $this->actingAs($admin)
            ->put(route('admin.anggota.update', 99999), ['nomor_telepon' => '081234567890'])
            ->assertRedirect(route('admin.anggota.index'))
            ->assertSessionHas('error', 'Data anggota tidak ditemukan.');

        // Simulasi API client (Accept: application/json) ke ID acak
        $this->actingAs($admin)
            ->putJson(route('admin.anggota.update', 88888), ['nomor_telepon' => '081234567890'])
            ->assertRedirect(route('admin.anggota.index'))
            ->assertSessionHas('error', 'Data anggota tidak ditemukan.');

        // Anggota yang sudah di-soft-delete juga ditolak rapi
        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $terhapus), ['nomor_telepon' => '081234567890'])
            ->assertRedirect(route('admin.anggota.index'))
            ->assertSessionHas('error', 'Data anggota tidak ditemukan.');
    }

    // ---------- AC 4: Keamanan (RBAC & sesi) ----------

    public function test_peran_selain_admin_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nomor_telepon' => '081234567890',
        ]);

        $peranLain = ['Pimpinan Bidang', 'Anggota Bidang', 'Penanggung Jawab', 'Bendahara', 'Penasihat'];

        foreach ($peranLain as $idx => $namaPeran) {
            $pelaku = $this->buatPengguna($namaPeran, $bidang, 'non_admin_49_' . $idx);

            $this->actingAs($pelaku)
                ->get(route('admin.anggota.edit', $anggota))
                ->assertForbidden();

            $this->actingAs($pelaku)
                ->put(route('admin.anggota.update', $anggota), ['nomor_telepon' => '089999999999'])
                ->assertForbidden();
        }

        $this->assertSame(
            '081234567890',
            DB::table('anggota')->where('id_anggota', $anggota)->value('nomor_telepon')
        );
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nomor_telepon' => '081234567890',
        ]);

        $this->get(route('admin.anggota.edit', $anggota))
            ->assertRedirect(route('login'));

        $this->put(route('admin.anggota.update', $anggota), ['nomor_telepon' => '089999999999'])
            ->assertRedirect(route('login'));

        $this->assertSame(
            '081234567890',
            DB::table('anggota')->where('id_anggota', $anggota)->value('nomor_telepon')
        );
    }

    // ---------- AC 5: Keamanan log audit ----------

    public function test_perubahan_anggota_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nama_lengkap'   => 'Ny. Kartika Putri',
            'nomor_telepon'  => '081234567890',
            'email'          => 'kartika.lama@kemenkeu.go.id',
            'alamat_lengkap' => 'Jl. Sudirman Kav. 10',
            'nama_suami'     => 'Hendra Wijaya',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon'  => '087712345678',
                'email'          => 'kartika.baru@kemenkeu.go.id',
                'alamat_lengkap' => 'Jl. Thamrin No. 5',
                'nama_suami'     => 'Ir. Hendra Wijaya',
            ])
            ->assertSessionHasNoErrors();

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($admin, $anggota) {
                $json = json_encode($konteks);

                return $pesan === 'Admin memperbarui data anggota'
                    && $konteks['admin_id'] === $admin->id_pengguna
                    && $konteks['admin_username'] === $admin->nama_pengguna
                    && (int) $konteks['anggota_id'] === $anggota
                    && $konteks['nama_anggota'] === 'Ny. Kartika Putri'
                    && in_array('nomor_telepon', $konteks['kolom_diubah'], true)
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, '087712345678')
                    && ! str_contains($json, 'kartika.baru@kemenkeu.go.id')
                    && ! str_contains($json, 'Jl. Thamrin')
                    && ! str_contains($json, 'Hendra Wijaya');
            })
            ->once();
    }

    public function test_ubah_tanpa_perubahan_tidak_mencatat_log_audit(): void
    {
        Log::spy();

        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nomor_telepon'  => '081234567890',
            'nama_panggilan' => 'Ny. Ratna',
            'tempat_lahir'   => 'Jakarta',
            'tanggal_lahir'  => '1985-05-10',
            'email'          => 'ratna@kemenkeu.go.id',
            'alamat_lengkap' => 'Jl. Melati No. 3',
            'nama_suami'     => 'Andi Pratama',
            'id_unit_kerja'  => null,
            'tingkat_pendidikan' => 'S1 (Sarjana)',
            'bidang_pendidikan'  => 'Hukum',
            'keahlian'           => 'Notariat',
            'bidang_usaha'       => 'Jasa',
            'bekerja'            => 'Ya',
            'instansi_kerja'     => 'Kemenkeu',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.anggota.update', $anggota), [
                'nomor_telepon'      => '081234567890',
                'nama_panggilan'     => 'Ratna',
                'tempat_lahir'       => 'Jakarta',
                'tanggal_lahir'      => '1985-05-10',
                'email'              => 'ratna@kemenkeu.go.id',
                'alamat_lengkap'     => 'Jl. Melati No. 3',
                'nama_suami'         => 'Andi Pratama',
                'id_unit_kerja'      => null,
                'tingkat_pendidikan' => 'S1 (Sarjana)',
                'bidang_pendidikan'  => 'Hukum',
                'keahlian'           => 'Notariat',
                'bidang_usaha'       => 'Jasa',
                'bekerja'            => 'Ya',
                'instansi_kerja'     => 'Kemenkeu',
            ])
            ->assertRedirect(route('admin.anggota.index'))
            ->assertSessionHas('success');

        Log::shouldNotHaveReceived('info');
    }
}