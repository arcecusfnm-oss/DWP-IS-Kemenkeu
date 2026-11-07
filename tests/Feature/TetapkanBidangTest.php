<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T-70 (B-010): Unit test tetapkan bidang pada akun.
 *
 * Tiga kelompok sesuai judul tugas:
 *   1. bidang valid       -> AC B-010 no. 1 dan 5
 *   2. bidang tidak ada   -> AC B-010 no. 3
 *   3. satu bidang aktif  -> AC B-010 no. 2
 *
 * Catatan: akses non-Admin (AC no. 4) dan "id_bidang wajib diisi" sudah dicakup
 * AssignBidangTest dari T-68, jadi tidak diulang di sini.
 */
class TetapkanBidangTest extends TestCase
{
    use RefreshDatabase;

    private const NAMA_BIDANG = [
        'Ekonomi',
        'Sosial Budaya',
        'Pendidikan',
        'Sekretariat',
        'Bendahara',
    ];

    private Pengguna $admin;
    private Pengguna $target;
    private int $idPeranAnggota;

    protected function setUp(): void
    {
        parent::setUp();

        $peranAdmin = Peran::firstOrCreate(['nama_peran' => 'Admin']);
        $peranAnggota = Peran::firstOrCreate(['nama_peran' => 'Anggota Bidang']);
        $this->idPeranAnggota = $peranAnggota->id_peran;

        foreach (self::NAMA_BIDANG as $nama) {
            Bidang::firstOrCreate(['nama_bidang' => $nama]);
        }

        // Kolom pengguna.id_bidang NOT NULL, jadi setiap akun harus punya bidang awal.
        // Admin hanya boleh bidang Sekretariat; target berperan Anggota Bidang
        // supaya boleh ditetapkan ke kelima bidang (sesuai config/dwp.php).
        $this->admin  = $this->buatPengguna('admin_t70', $peranAdmin->id_peran, $this->idBidang('Sekretariat'));
        $this->target = $this->buatPengguna('target_t70', $this->idPeranAnggota, $this->idBidang('Sosial Budaya'));
    }

    // ---------- Helper ----------

    private function buatPengguna(string $username, int $idPeran, int $idBidang): Pengguna
    {
        return Pengguna::create([
            'nama_lengkap'           => ucfirst($username),
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => Hash::make('password123'),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $idPeran,
            'id_bidang'              => $idBidang,
        ]);
    }

    private function tetapkan(Pengguna $akun, $idBidang)
    {
        return $this->actingAs($this->admin)
            ->put(route('admin.akun.bidang.update', $akun->id_pengguna), [
                'id_bidang' => $idBidang,
            ]);
    }

    private function bidangAkun(Pengguna $akun): ?int
    {
        $nilai = $akun->fresh()->id_bidang;

        return $nilai === null ? null : (int) $nilai;
    }

    private function idBidang(string $nama): int
    {
        return (int) Bidang::where('nama_bidang', $nama)->value('id_bidang');
    }

    // ---------- 1. Bidang valid ----------

    public function test_bidang_valid_tersimpan_dan_menampilkan_pesan_sukses(): void
    {
        $idPendidikan = $this->idBidang('Pendidikan');

        $this->tetapkan($this->target, $idPendidikan)
            ->assertRedirect(route('admin.akun.index'))
            ->assertSessionHas('sukses', 'Bidang akun berhasil diperbarui.');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $this->target->id_pengguna,
            'id_bidang'   => $idPendidikan,
        ]);
    }

    public function test_kelima_bidang_dari_basis_data_dapat_ditetapkan(): void
    {
        foreach (self::NAMA_BIDANG as $nama) {
            $id = $this->idBidang($nama);

            $this->tetapkan($this->target, $id)->assertSessionHasNoErrors();

            $this->assertSame($id, $this->bidangAkun($this->target), "Bidang {$nama} gagal ditetapkan");
        }
    }

    // ---------- 2. Bidang tidak ada ----------

    public function test_bidang_tidak_ada_ditolak_dengan_pesan_umum_dan_data_tidak_berubah(): void
    {
        $idAwal = $this->idBidang('Ekonomi');
        $this->target->update(['id_bidang' => $idAwal]);

        // Angka yang tidak ada di tabel bidang: lolos 'integer', gagal 'exists'.
        foreach ([999999, 0, -1] as $idTidakAda) {
            $this->tetapkan($this->target, $idTidakAda)
                ->assertSessionHasErrors(['id_bidang' => 'Bidang tidak terdaftar.']);

            $this->assertSame($idAwal, $this->bidangAkun($this->target), "Bidang berubah untuk input {$idTidakAda}");
        }
    }

    public function test_id_bidang_bukan_angka_ditolak_dan_data_tidak_berubah(): void
    {
        $idAwal = $this->idBidang('Ekonomi');
        $this->target->update(['id_bidang' => $idAwal]);

        foreach (['abc', '1; DROP TABLE bidang', "' OR 1=1 --", '1.5'] as $input) {
            $this->tetapkan($this->target, $input)
                ->assertSessionHasErrors(['id_bidang' => 'ID bidang tidak valid.']);

            $this->assertSame($idAwal, $this->bidangAkun($this->target), "Bidang berubah untuk input {$input}");
        }

        // Tabel bidang tetap utuh.
        $this->assertSame(count(self::NAMA_BIDANG), Bidang::whereIn('nama_bidang', self::NAMA_BIDANG)->count());
    }

    public function test_akun_yang_tidak_ada_atau_sudah_dihapus_mengembalikan_404(): void
    {
        $idBidang = $this->idBidang('Sekretariat');

        $this->tetapkan($this->target, $idBidang);
        $this->target->delete(); // soft delete (kolom dihapus_pada)

        $this->tetapkan($this->target, $this->idBidang('Ekonomi'))->assertNotFound();
        $this->actingAs($this->admin)
            ->put(route('admin.akun.bidang.update', 999999), ['id_bidang' => $idBidang])
            ->assertNotFound();
    }

    // ---------- 3. Satu bidang aktif ----------

    public function test_bidang_baru_menggantikan_bidang_lama_sehingga_hanya_satu_bidang_aktif(): void
    {
        $idA = $this->idBidang('Sekretariat');
        $idB = $this->idBidang('Ekonomi');

        $this->tetapkan($this->target, $idA)->assertSessionHasNoErrors();
        $this->assertSame($idA, $this->bidangAkun($this->target));

        $this->tetapkan($this->target, $idB)->assertSessionHasNoErrors();

        // Hanya bidang B yang tersimpan; bidang A tidak lagi menempel pada akun.
        $this->assertSame($idB, $this->bidangAkun($this->target));
        $this->assertDatabaseMissing('pengguna', [
            'id_pengguna' => $this->target->id_pengguna,
            'id_bidang'   => $idA,
        ]);

        // Satu akun tetap satu baris, bidang lama tidak terhapus dari tabel bidang.
        $this->assertSame(1, Pengguna::where('id_pengguna', $this->target->id_pengguna)->count());
        $this->assertDatabaseHas('bidang', ['id_bidang' => $idA]);
    }

    public function test_menetapkan_bidang_hanya_mengubah_akun_yang_dituju(): void
    {
        $idA = $this->idBidang('Pendidikan');
        $idB = $this->idBidang('Bendahara');

        $akunLain = $this->buatPengguna('akun_lain_t70', $this->idPeranAnggota, $idA);

        $this->tetapkan($this->target, $idB)->assertSessionHasNoErrors();

        $this->assertSame($idB, $this->bidangAkun($this->target));
        $this->assertSame($idA, $this->bidangAkun($akunLain), 'Akun lain ikut berubah');
        $this->assertSame($this->idBidang('Sekretariat'), $this->bidangAkun($this->admin), 'Akun Admin ikut berubah');
    }
}