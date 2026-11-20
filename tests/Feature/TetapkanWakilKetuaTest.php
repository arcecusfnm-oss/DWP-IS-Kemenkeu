<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * T-39 (B-019): Pengujian endpoint tetapkan wakil ketua bidang.
 */
class TetapkanWakilKetuaTest extends TestCase
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
            'nama_lengkap'            => 'Nama ' . $username,
            'nama_pengguna'           => $username,
            'kata_sandi_hash'         => 'Password123!',
            'status'                  => 'aktif',
            'perlu_ganti_kata_sandi'  => false,
            'id_peran'                => $this->buatPeran($peran),
            'id_bidang'               => $idBidang,
        ]);
    }

    private function buatAdmin(): Pengguna
    {
        return $this->buatPengguna(
            'Admin',
            $this->buatBidang('Sekretariat'),
            'admin_uji_wakil'
        );
    }

    private function buatAnggota(int $idBidang, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('anggota')->insertGetId(array_merge([
            'nama_lengkap'    => 'Anggota Uji',
            'nomor_telepon'  => '08112233445',
            'id_bidang'      => $idBidang,
            'status'          => 'aktif',
            'dibuat_oleh'    => $dibuatOleh,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $ubah), 'id_anggota');
    }

    private function tetapkan(Pengguna $pelaku, int $idBidang, $idAnggota)
    {
        return $this->actingAs($pelaku)->put(
            route('admin.bidang.wakil-ketua.update', $idBidang),
            ['id_anggota' => $idAnggota]
        );
    }

    private function jabatan(int $idAnggota): ?string
    {
        return DB::table('anggota')
            ->where('id_anggota', $idAnggota)
            ->value('jabatan_bidang');
    }

    private function jumlahWakil(int $idBidang): int
    {
        return DB::table('anggota')
            ->where('id_bidang', $idBidang)
            ->where('jabatan_bidang', 'wakil_ketua')
            ->count();
    }

    // ---------- AC 1: berhasil ----------

    public function test_admin_berhasil_menetapkan_wakil_ketua(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna);

        $res = $this->tetapkan($admin, $bidang, $anggota);

        $res->assertRedirect(route('admin.bidang.index'));
        $res->assertSessionHas('success');
        $res->assertSessionHasNoErrors();

        $this->assertSame('wakil_ketua', $this->jabatan($anggota));
        $this->assertSame(1, $this->jumlahWakil($bidang));
    }

    public function test_anggota_tanpa_akun_boleh_menjadi_wakil_ketua(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'id_pengguna' => null,
        ]);

        $this->tetapkan($admin, $bidang, $anggota)->assertSessionHasNoErrors();

        $this->assertSame('wakil_ketua', $this->jabatan($anggota));
    }

    // ---------- AC 2: penggantian wakil ketua ----------

    public function test_wakil_ketua_baru_menggantikan_wakil_lama(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $admin  = $this->buatAdmin();

        $lama = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nama_lengkap'     => 'Wakil Lama',
            'jabatan_bidang'  => 'wakil_ketua',
        ]);

        $baru = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'nama_lengkap' => 'Wakil Baru',
        ]);

        $this->tetapkan($admin, $bidang, $baru)->assertSessionHasNoErrors();

        $this->assertNull($this->jabatan($lama));
        $this->assertSame('wakil_ketua', $this->jabatan($baru));
        $this->assertSame(1, $this->jumlahWakil($bidang));
    }

    // ---------- AC 3: validasi kandidat ----------

    public function test_ketua_bidang_tidak_boleh_dijadikan_wakil_ketua(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $ketua  = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'jabatan_bidang' => 'ketua',
        ]);

        $res = $this->tetapkan($admin, $bidang, $ketua);

        $res->assertSessionHasErrors([
            'id_anggota' => 'Ketua bidang tidak dapat ditetapkan sebagai wakil ketua.',
        ]);

        $this->assertSame('ketua', $this->jabatan($ketua));
        $this->assertSame(0, $this->jumlahWakil($bidang));
    }

    public function test_anggota_bidang_lain_ditolak(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $lain   = $this->buatBidang('Ekonomi');
        $admin  = $this->buatAdmin();

        $anggotaLain = $this->buatAnggota($lain, $admin->id_pengguna);

        $res = $this->tetapkan($admin, $bidang, $anggotaLain);

        $res->assertSessionHasErrors([
            'id_anggota' => 'Anggota tidak dapat ditetapkan sebagai wakil ketua.',
        ]);

        $this->assertNull($this->jabatan($anggotaLain));
    }

    public function test_anggota_nonaktif_ditolak(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'status' => 'nonaktif',
        ]);

        $res = $this->tetapkan($admin, $bidang, $anggota);

        $res->assertSessionHasErrors([
            'id_anggota' => 'Anggota tidak dapat ditetapkan sebagai wakil ketua.',
        ]);

        $this->assertNull($this->jabatan($anggota));
    }

    public function test_anggota_dengan_akun_nonaktif_ditolak(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $admin  = $this->buatAdmin();

        $akun = $this->buatPengguna(
            'Anggota Bidang',
            $bidang,
            'akun_nonaktif_wakil'
        );

        DB::table('pengguna')
            ->where('id_pengguna', $akun->id_pengguna)
            ->update(['status' => 'nonaktif']);

        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'id_pengguna' => $akun->id_pengguna,
        ]);

        $res = $this->tetapkan($admin, $bidang, $anggota);

        $res->assertSessionHasErrors([
            'id_anggota' => 'Anggota tidak dapat ditetapkan sebagai wakil ketua.',
        ]);

        $this->assertNull($this->jabatan($anggota));
    }

    // ---------- AC 4: keamanan ----------

    public function test_pengguna_bukan_admin_ditolak(): void
    {
        $bidang     = $this->buatBidang('Sosial Budaya');
        $admin      = $this->buatAdmin();
        $bukanAdmin = $this->buatPengguna(
            'Anggota Bidang',
            $bidang,
            'akun_biasa_wakil'
        );

        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna);

        $this->tetapkan($bukanAdmin, $bidang, $anggota)->assertForbidden();

        $this->assertNull($this->jabatan($anggota));
    }

    // ---------- AC 5: audit log ----------

    public function test_penetapan_wakil_ketua_tercatat_di_log(): void
    {
        Log::spy();

        $bidang  = $this->buatBidang('Sosial Budaya');
        $admin   = $this->buatAdmin();
        $anggota = $this->buatAnggota($bidang, $admin->id_pengguna, [
            'email'           => 'uji@example.test',
            'alamat_lengkap'  => 'Alamat Rahasia Uji',
        ]);

        $this->tetapkan($admin, $bidang, $anggota);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($admin, $bidang, $anggota) {
                $json = json_encode($konteks);

                return $pesan === 'Admin menetapkan wakil ketua bidang'
                    && $konteks['admin_id'] === $admin->id_pengguna
                    && (int) $konteks['bidang_id'] === $bidang
                    && (int) $konteks['anggota_id'] === $anggota
                    && array_key_exists('wakil_lama_id', $konteks)
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, '08112233445')
                    && ! str_contains($json, 'uji@example.test')
                    && ! str_contains($json, 'Alamat Rahasia Uji');
            })
            ->once();
    }
}
