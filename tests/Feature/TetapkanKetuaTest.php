<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * T-32 (B-017): pengujian endpoint tetapkan ketua bidang.
 */
class TetapkanKetuaTest extends TestCase
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
        return $this->buatPengguna('Admin', $this->buatBidang('Sekretariat'), 'admin_uji32');
    }

    private function buatAnggota(int $idBidang, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('anggota')->insertGetId(array_merge([
            'nama_lengkap'  => 'Anggota Uji',
            'nomor_telepon' => '08112233445',
            'id_bidang'     => $idBidang,
            'status'        => 'aktif',
            'dibuat_oleh'   => $dibuatOleh,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $ubah), 'id_anggota');
    }

    private function tetapkan(Pengguna $pelaku, int $idBidang, $idAnggota)
    {
        return $this->actingAs($pelaku)->put(
            route('admin.bidang.ketua.update', $idBidang),
            ['id_anggota' => $idAnggota]
        );
    }

    private function jabatan(int $idAnggota): ?string
    {
        return DB::table('anggota')->where('id_anggota', $idAnggota)->value('jabatan_bidang');
    }

    private function jumlahKetua(int $idBidang): int
    {
        return DB::table('anggota')
            ->where('id_bidang', $idBidang)
            ->where('jabatan_bidang', 'ketua')
            ->count();
    }

    // ---------- AC 1: berhasil ----------

    public function test_admin_berhasil_menetapkan_ketua(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna);

        $res = $this->tetapkan($admin, $bidang, $anggota);

        $res->assertRedirect(route('admin.bidang.index'));
        $res->assertSessionHas('success');
        $res->assertSessionHasNoErrors();
        $this->assertSame('ketua', $this->jabatan($anggota));
    }

    public function test_anggota_tanpa_akun_boleh_menjadi_ketua(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, ['id_pengguna' => null]);

        $this->tetapkan($admin, $bidang, $anggota)->assertSessionHasNoErrors();

        $this->assertSame('ketua', $this->jabatan($anggota));
    }

    // ---------- AC 2: satu ketua, diganti ----------

    public function test_ketua_baru_menggantikan_ketua_lama(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $admin  = $this->buatAdmin();
        $lama   = $this->buatAnggota($bidang, $admin->id_pengguna, ['nama_lengkap' => 'Ketua Lama']);
        $baru   = $this->buatAnggota($bidang, $admin->id_pengguna, ['nama_lengkap' => 'Ketua Baru']);

        $this->tetapkan($admin, $bidang, $lama);
        $this->tetapkan($admin, $bidang, $baru)->assertSessionHasNoErrors();

        $this->assertNull($this->jabatan($lama));
        $this->assertSame('ketua', $this->jabatan($baru));
        $this->assertSame(1, $this->jumlahKetua($bidang));
    }

    public function test_wakil_ketua_yang_dijadikan_ketua_jabatannya_berganti(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $admin  = $this->buatAdmin();
        $wakil  = $this->buatAnggota($bidang, $admin->id_pengguna, ['jabatan_bidang' => 'wakil_ketua']);

        $this->tetapkan($admin, $bidang, $wakil)->assertSessionHasNoErrors();

        $this->assertSame('ketua', $this->jabatan($wakil));
    }

    public function test_ketua_bidang_lain_tidak_ikut_berubah(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $lain   = $this->buatBidang('Ekonomi');
        $admin  = $this->buatAdmin();
        $ketuaLain = $this->buatAnggota($lain, $admin->id_pengguna, ['jabatan_bidang' => 'ketua']);
        $anggota   = $this->buatAnggota($bidang, $admin->id_pengguna);

        $this->tetapkan($admin, $bidang, $anggota);

        $this->assertSame('ketua', $this->jabatan($ketuaLain));
    }

    public function test_anggota_yang_sudah_menjadi_ketua_ditolak(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna);

        $this->tetapkan($admin, $bidang, $anggota);
        $res = $this->tetapkan($admin, $bidang, $anggota);

        $res->assertSessionHasErrors('id_anggota');
        $this->assertSame('ketua', $this->jabatan($anggota));
    }

    // ---------- AC 3: ditolak dengan pesan umum ----------

    public function test_anggota_bidang_lain_ditolak(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $lain     = $this->buatBidang('Ekonomi');
        $admin    = $this->buatAdmin();
        $dariLain = $this->buatAnggota($lain, $admin->id_pengguna);

        $res = $this->tetapkan($admin, $bidang, $dariLain);

        $res->assertSessionHasErrors(['id_anggota' => 'Anggota tidak dapat ditetapkan sebagai ketua.']);
        $this->assertNull($this->jabatan($dariLain));
    }

    public function test_anggota_nonaktif_ditolak(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, ['status' => 'nonaktif']);

        $res = $this->tetapkan($admin, $bidang, $anggota);

        $res->assertSessionHasErrors(['id_anggota' => 'Anggota tidak dapat ditetapkan sebagai ketua.']);
        $this->assertNull($this->jabatan($anggota));
    }

    public function test_anggota_yang_sudah_dihapus_ditolak(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, ['dihapus_pada' => now()]);

        $res = $this->tetapkan($admin, $bidang, $anggota);

        $res->assertSessionHasErrors(['id_anggota' => 'Anggota tidak dapat ditetapkan sebagai ketua.']);
        $this->assertNull($this->jabatan($anggota));
    }

    public function test_anggota_dengan_akun_nonaktif_ditolak(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $admin  = $this->buatAdmin();
        $akun   = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_nonaktif');
        DB::table('pengguna')->where('id_pengguna', $akun->id_pengguna)->update(['status' => 'nonaktif']);
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, ['id_pengguna' => $akun->id_pengguna]);

        $res = $this->tetapkan($admin, $bidang, $anggota);

        $res->assertSessionHasErrors(['id_anggota' => 'Anggota tidak dapat ditetapkan sebagai ketua.']);
        $this->assertNull($this->jabatan($anggota));
    }

    public function test_anggota_tidak_ada_ditolak(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $admin  = $this->buatAdmin();

        $res = $this->tetapkan($admin, $bidang, 9999);

        $res->assertSessionHasErrors(['id_anggota' => 'Anggota tidak dapat ditetapkan sebagai ketua.']);
        $this->assertSame(0, $this->jumlahKetua($bidang));
    }

    public function test_id_anggota_wajib_diisi(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $admin  = $this->buatAdmin();

        $this->tetapkan($admin, $bidang, '')->assertSessionHasErrors('id_anggota');
    }

    public function test_bidang_tidak_ada_mengembalikan_404(): void
    {
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($this->buatBidang('Sosial Budaya'), $admin->id_pengguna);

        $this->tetapkan($admin, 9999, $anggota)->assertNotFound();
    }

    // ---------- AC 5: keamanan ----------

    public function test_pengguna_bukan_admin_ditolak(): void
    {
        $bidang     = $this->buatBidang('Sosial Budaya');
        $admin      = $this->buatAdmin();
        $bukanAdmin = $this->buatPengguna('Anggota Bidang', $bidang, 'akun_biasa');
        $anggota    = $this->buatAnggota($bidang, $admin->id_pengguna);

        $this->tetapkan($bukanAdmin, $bidang, $anggota)->assertForbidden();

        $this->assertNull($this->jabatan($anggota));
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');

        $this->put(route('admin.bidang.ketua.update', $bidang), ['id_anggota' => 1])
            ->assertRedirect(route('login'));

        $this->assertSame(0, $this->jumlahKetua($bidang));
    }

    // ---------- AC 6: log ----------

    public function test_penetapan_ketua_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'email'          => 'ani@kemenkeu.go.id',
            'alamat_lengkap' => 'Jl. Contoh No. 1',
        ]);

        $this->tetapkan($admin, $bidang, $anggota);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($admin, $bidang, $anggota) {
                $json = json_encode($konteks);

                return $pesan === 'Admin menetapkan ketua bidang'
                    && $konteks['admin_id'] === $admin->id_pengguna
                    && (int) $konteks['bidang_id'] === $bidang
                    && (int) $konteks['anggota_id'] === $anggota
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, '08112233445')
                    && ! str_contains($json, 'ani@kemenkeu.go.id')
                    && ! str_contains($json, 'Jl. Contoh');
            })
            ->once();
    }
}