<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * T-18 (B-015): pengujian endpoint pindah bidang anggota.
 */
class PindahBidangTest extends TestCase
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
        return $this->buatPengguna('Admin', $this->buatBidang('Sekretariat'), 'admin_uji18');
    }

    private function buatAnggota(?int $idBidang, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('anggota')->insertGetId(array_merge([
            'nama_lengkap'  => 'Anggota Uji Pindah',
            'nomor_telepon' => '081234567890',
            'id_bidang'     => $idBidang,
            'status'        => 'aktif',
            'dibuat_oleh'   => $dibuatOleh,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $ubah), 'id_anggota');
    }

    private function pindah(Pengguna $pelaku, int $idAnggota, mixed $idBidang)
    {
        return $this->actingAs($pelaku)->put(
            route('admin.anggota.pindah-bidang', $idAnggota),
            ['id_bidang' => $idBidang]
        );
    }

    // ---------- AC 1: Skenario utama (pindah & tampil di daftar anggota + daftar akun) ----------

    public function test_admin_berhasil_memindahkan_bidang_dan_tampil_di_daftar_anggota_serta_akun(): void
    {
        $bidangAsal   = $this->buatBidang('Pendidikan');
        $bidangTujuan = $this->buatBidang('Ekonomi');
        $admin        = $this->buatAdmin();

        $akun = $this->buatPengguna('Anggota Bidang', $bidangAsal, 'rina_pindah', [
            'nama_lengkap' => 'Ny. Rina Kusuma',
        ]);
        $anggota = $this->buatAnggota($bidangAsal, $admin->id_pengguna, [
            'id_pengguna'  => $akun->id_pengguna,
            'nama_lengkap' => 'Ny. Rina Kusuma',
        ]);

        $res = $this->pindah($admin, $anggota, $bidangTujuan);

        $res->assertRedirect(route('admin.anggota.index'));
        $res->assertSessionHas('success', 'Bidang anggota berhasil dipindahkan.');
        $res->assertSessionHasNoErrors();

        // Cek basis data anggota & akun terhubung
        $this->assertSame(
            $bidangTujuan,
            (int) DB::table('anggota')->where('id_anggota', $anggota)->value('id_bidang')
        );
        $this->assertSame(
            $bidangTujuan,
            (int) DB::table('pengguna')->where('id_pengguna', $akun->id_pengguna)->value('id_bidang')
        );

        // Cek tampil pada halaman daftar anggota
        $this->actingAs($admin)
            ->get(route('admin.anggota.index', ['q' => 'Rina Kusuma']))
            ->assertOk()
            ->assertSee('Ny. Rina Kusuma')
            ->assertSee('Ekonomi');

        // Cek tampil pada halaman daftar akun
        $this->actingAs($admin)
            ->get(route('admin.akun.index', ['q' => 'rina_pindah']))
            ->assertOk()
            ->assertSee('rina_pindah')
            ->assertSee('Ekonomi');
    }

    public function test_pindah_bidang_melepas_jabatan_ketua_atau_wakil_ketua(): void
    {
        $bidangAsal   = $this->buatBidang('Pendidikan');
        $bidangTujuan = $this->buatBidang('Ekonomi');
        $admin        = $this->buatAdmin();

        $ketua = $this->buatAnggota($bidangAsal, $admin->id_pengguna, [
            'jabatan_bidang' => 'ketua',
        ]);
        $wakil = $this->buatAnggota($bidangAsal, $admin->id_pengguna, [
            'jabatan_bidang' => 'wakil_ketua',
        ]);

        $this->pindah($admin, $ketua, $bidangTujuan)->assertSessionHasNoErrors();
        $this->pindah($admin, $wakil, $bidangTujuan)->assertSessionHasNoErrors();

        $this->assertNull(
            DB::table('anggota')->where('id_anggota', $ketua)->value('jabatan_bidang')
        );
        $this->assertNull(
            DB::table('anggota')->where('id_anggota', $wakil)->value('jabatan_bidang')
        );
    }

    public function test_pindah_bidang_anggota_tanpa_akun_tetap_berhasil(): void
    {
        $bidangAsal   = $this->buatBidang('Pendidikan');
        $bidangTujuan = $this->buatBidang('Ekonomi');
        $admin        = $this->buatAdmin();
        $anggota      = $this->buatAnggota($bidangAsal, $admin->id_pengguna, [
            'id_pengguna' => null,
        ]);

        $res = $this->pindah($admin, $anggota, $bidangTujuan);

        $res->assertRedirect(route('admin.anggota.index'));
        $res->assertSessionHas('success');
        $this->assertSame(
            $bidangTujuan,
            (int) DB::table('anggota')->where('id_anggota', $anggota)->value('id_bidang')
        );
    }

    public function test_pindah_bidang_tidak_memengaruhi_anggota_lain(): void
    {
        $bidangAsal   = $this->buatBidang('Pendidikan');
        $bidangTujuan = $this->buatBidang('Ekonomi');
        $admin        = $this->buatAdmin();

        $target  = $this->buatAnggota($bidangAsal, $admin->id_pengguna);
        $lainnya = $this->buatAnggota($bidangAsal, $admin->id_pengguna, [
            'jabatan_bidang' => 'ketua',
        ]);

        $this->pindah($admin, $target, $bidangTujuan)->assertSessionHasNoErrors();

        $dataLain = DB::table('anggota')->where('id_anggota', $lainnya)->first();
        $this->assertSame($bidangAsal, (int) $dataLain->id_bidang);
        $this->assertSame('ketua', $dataLain->jabatan_bidang);
    }

    // ---------- AC 2: Data (pindah dua kali berturut-turut, hanya 1 bidang aktif terakhir) ----------

    public function test_pindah_bidang_dua_kali_berturut_turut_hanya_menyimpan_satu_bidang_aktif_terakhir(): void
    {
        $bidangPertama = $this->buatBidang('Pendidikan');
        $bidangKedua   = $this->buatBidang('Ekonomi');
        $bidangKetiga  = $this->buatBidang('Sosial Budaya');
        $admin         = $this->buatAdmin();

        $akun = $this->buatPengguna('Anggota Bidang', $bidangPertama, 'anggota_dua_kali');
        $anggota = $this->buatAnggota($bidangPertama, $admin->id_pengguna, [
            'id_pengguna' => $akun->id_pengguna,
        ]);

        // Pemindahan pertama: Pendidikan -> Ekonomi
        $this->pindah($admin, $anggota, $bidangKedua)->assertSessionHasNoErrors();
        $this->assertSame(
            $bidangKedua,
            (int) DB::table('anggota')->where('id_anggota', $anggota)->value('id_bidang')
        );

        // Pemindahan kedua berturut-turut: Ekonomi -> Sosial Budaya
        $this->pindah($admin, $anggota, $bidangKetiga)->assertSessionHasNoErrors();

        // Basis data hanya menyimpan 1 baris anggota dengan bidang aktif terakhir (Sosial Budaya)
        $barisAnggota = DB::table('anggota')->where('id_anggota', $anggota)->get();
        $this->assertCount(1, $barisAnggota);
        $this->assertSame($bidangKetiga, (int) $barisAnggota->first()->id_bidang);
        $this->assertNotSame($bidangPertama, (int) $barisAnggota->first()->id_bidang);
        $this->assertNotSame($bidangKedua, (int) $barisAnggota->first()->id_bidang);

        $this->assertSame(
            $bidangKetiga,
            (int) DB::table('pengguna')->where('id_pengguna', $akun->id_pengguna)->value('id_bidang')
        );
    }

    // ---------- AC 3: Skenario gagal (bidang sama & bidang tidak ada / ID acak API client) ----------

    public function test_pindah_ke_bidang_yang_sama_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'jabatan_bidang' => 'ketua',
        ]);

        $res = $this->from(route('admin.anggota.index'))
            ->pindah($admin, $anggota, $bidang);

        $res->assertRedirect(route('admin.anggota.index'));
        $res->assertSessionHas('error', 'Bidang tujuan harus berbeda dari bidang saat ini.');

        $data = DB::table('anggota')->where('id_anggota', $anggota)->first();
        $this->assertSame($bidang, (int) $data->id_bidang);
        $this->assertSame('ketua', $data->jabatan_bidang);
    }

    public function test_bidang_tujuan_tidak_ada_ditolak_di_form_maupun_api_client(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna);

        // Lewat request form biasa
        $resForm = $this->pindah($admin, $anggota, 99999);
        $resForm->assertSessionHasErrors(['id_bidang' => 'Bidang tujuan tidak tersedia.']);

        // Lewat API client (Accept: application/json) dengan ID bidang acak
        $resApi = $this->actingAs($admin)->putJson(
            route('admin.anggota.pindah-bidang', $anggota),
            ['id_bidang' => 88888]
        );
        $resApi->assertUnprocessable()
            ->assertJsonValidationErrors(['id_bidang']);

        $this->assertSame(
            $bidang,
            (int) DB::table('anggota')->where('id_anggota', $anggota)->value('id_bidang')
        );
    }

    public function test_bidang_tujuan_kosong_dan_bukan_angka_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna);

        $this->pindah($admin, $anggota, '')
            ->assertSessionHasErrors(['id_bidang' => 'Bidang tujuan wajib dipilih.']);

        $this->pindah($admin, $anggota, 'bukan-angka')
            ->assertSessionHasErrors(['id_bidang' => 'Bidang tujuan tidak valid.']);
    }

    public function test_anggota_tidak_ditemukan_atau_belum_punya_bidang_ditolak(): void
    {
        $bidangAsal   = $this->buatBidang('Pendidikan');
        $bidangTujuan = $this->buatBidang('Ekonomi');
        $admin        = $this->buatAdmin();

        $terhapus = $this->buatAnggota($bidangAsal, $admin->id_pengguna, [
            'dihapus_pada' => now(),
        ]);
        $tanpaBidang = $this->buatAnggota(null, $admin->id_pengguna);

        $this->pindah($admin, 99999, $bidangTujuan)
            ->assertSessionHas('error', 'Data anggota tidak ditemukan.');

        $this->pindah($admin, $terhapus, $bidangTujuan)
            ->assertSessionHas('error', 'Data anggota tidak ditemukan.');

        $this->pindah($admin, $tanpaBidang, $bidangTujuan)
            ->assertSessionHas('error', 'Anggota belum memiliki bidang saat ini.');
    }

    // ---------- AC 4: Keamanan (tanpa sesi & peran lain ditolak di server) ----------

    public function test_non_admin_ditolak(): void
    {
        $bidangAsal   = $this->buatBidang('Pendidikan');
        $bidangTujuan = $this->buatBidang('Ekonomi');
        $admin        = $this->buatAdmin();
        $anggota      = $this->buatAnggota($bidangAsal, $admin->id_pengguna, [
            'jabatan_bidang' => 'ketua',
        ]);

        $peranLain = ['Pimpinan Bidang', 'Anggota Bidang', 'Penanggung Jawab', 'Bendahara', 'Penasihat'];

        foreach ($peranLain as $idx => $namaPeran) {
            $pelaku = $this->buatPengguna($namaPeran, $bidangAsal, 'non_admin_18_' . $idx);

            $this->pindah($pelaku, $anggota, $bidangTujuan)->assertForbidden();
        }

        $data = DB::table('anggota')->where('id_anggota', $anggota)->first();
        $this->assertSame($bidangAsal, (int) $data->id_bidang);
        $this->assertSame('ketua', $data->jabatan_bidang);
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $bidangAsal   = $this->buatBidang('Pendidikan');
        $bidangTujuan = $this->buatBidang('Ekonomi');
        $admin        = $this->buatAdmin();
        $anggota      = $this->buatAnggota($bidangAsal, $admin->id_pengguna);

        $this->put(route('admin.anggota.pindah-bidang', $anggota), ['id_bidang' => $bidangTujuan])
            ->assertRedirect(route('login'));

        $this->assertSame(
            $bidangAsal,
            (int) DB::table('anggota')->where('id_anggota', $anggota)->value('id_bidang')
        );
    }

    // ---------- AC 5: Keamanan log (waktu, Admin pelaku, anggota, bidang asal, bidang tujuan, tanpa data sensitif) ----------

    public function test_perpindahan_bidang_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $bidangAsal   = $this->buatBidang('Pendidikan');
        $bidangTujuan = $this->buatBidang('Ekonomi');
        $admin        = $this->buatAdmin();
        $anggota      = $this->buatAnggota($bidangAsal, $admin->id_pengguna, [
            'nama_lengkap'   => 'Ny. Siti Rahma',
            'nomor_telepon'  => '089876543210',
            'email'          => 'siti.rahma@kemenkeu.go.id',
            'alamat_lengkap' => 'Jl. Merdeka Selatan No. 9',
        ]);

        $this->pindah($admin, $anggota, $bidangTujuan)->assertSessionHasNoErrors();

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($admin, $anggota, $bidangAsal, $bidangTujuan) {
                $json = json_encode($konteks);

                return $pesan === 'Admin memindahkan bidang anggota'
                    && $konteks['admin_id'] === $admin->id_pengguna
                    && $konteks['admin_username'] === $admin->nama_pengguna
                    && (int) $konteks['anggota_id'] === $anggota
                    && $konteks['nama_anggota'] === 'Ny. Siti Rahma'
                    && (int) $konteks['id_bidang_lama'] === $bidangAsal
                    && $konteks['bidang_lama'] === 'Pendidikan'
                    && (int) $konteks['id_bidang_baru'] === $bidangTujuan
                    && $konteks['bidang_baru'] === 'Ekonomi'
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, '089876543210')
                    && ! str_contains($json, 'siti.rahma@kemenkeu.go.id')
                    && ! str_contains($json, 'Jl. Merdeka Selatan');
            })
            ->once();
    }
}