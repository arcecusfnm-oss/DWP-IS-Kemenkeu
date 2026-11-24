<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tes awal T-009 (B-024). Self-contained: semua data dibuat di sini.
 * Kalau gaya factory/seeder tim berbeda, boleh dipindah atau dirapikan di T-011.
 */
class PenanggungJawabTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $admin;
    private Bidang $bidangA;
    private Bidang $bidangB;
    private Pengguna $pj1;   // PJ: bidang A
    private Pengguna $pj2;   // PJ: bidang A dan B

    protected function setUp(): void
    {
        parent::setUp();

        $this->bidangA = Bidang::firstOrCreate(['nama_bidang' => 'Bidang Uji A']);
        $this->bidangB = Bidang::firstOrCreate(['nama_bidang' => 'Bidang Uji B']);

        $this->admin = $this->buatAkun('Admin', 'admin_uji', $this->bidangA->id_bidang);

        $this->pj1 = $this->buatAkun('Penanggung Jawab', 'pj_satu', null);
        $this->pj2 = $this->buatAkun('Penanggung Jawab', 'pj_dua', null);

        $this->hubungkan($this->pj1, [$this->bidangA]);
        $this->hubungkan($this->pj2, [$this->bidangA, $this->bidangB]);
    }

    private function buatAkun(string $peran, string $username, ?int $idBidang, string $status = 'aktif'): Pengguna
    {
        $p = Peran::firstOrCreate(['nama_peran' => $peran]);

        return Pengguna::create([
            'nama_lengkap'           => ucfirst(str_replace('_', ' ', $username)),
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => 'Rahasia#12345',
            'status'                 => $status,
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $p->id_peran,
            'id_bidang'              => $idBidang,
        ]);
    }

    private function hubungkan(Pengguna $akun, array $daftarBidang): void
    {
        foreach ($daftarBidang as $b) {
            DB::table('pengguna_bidang')->insert([
                'id_pengguna' => $akun->id_pengguna,
                'id_bidang'   => $b->id_bidang,
            ]);
        }
    }

    private function pjSistem(Bidang $b): ?int
    {
        $nilai = DB::table('bidang')->where('id_bidang', $b->id_bidang)->value('id_penanggung_jawab_sistem');

        return $nilai === null ? null : (int) $nilai;
    }

    private function tetapkan(Bidang $b, $idPengguna)
    {
        return $this->put(
            route('admin.bidang.penanggung-jawab-sistem.update', $b->id_bidang),
            ['id_pengguna' => $idPengguna]
        );
    }

    // AC 1
    public function test_admin_dapat_menetapkan_penanggung_jawab_sistem(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.bidang.index'));

        $this->tetapkan($this->bidangA, $this->pj1->id_pengguna)
            ->assertRedirect(route('admin.bidang.index'))
            ->assertSessionHas('success');

        $this->assertSame($this->pj1->id_pengguna, $this->pjSistem($this->bidangA));
    }

    // AC 2
    public function test_penetapan_baru_menimpa_yang_lama(): void
    {
        $this->actingAs($this->admin);

        $this->tetapkan($this->bidangA, $this->pj1->id_pengguna);
        $this->tetapkan($this->bidangA, $this->pj2->id_pengguna)->assertSessionHas('success');

        $this->assertSame($this->pj2->id_pengguna, $this->pjSistem($this->bidangA));
    }

    // Fleksibel: satu PJ boleh memegang beberapa bidang
    public function test_satu_penanggung_jawab_boleh_memegang_beberapa_bidang(): void
    {
        $this->actingAs($this->admin);

        $this->tetapkan($this->bidangA, $this->pj2->id_pengguna);
        $this->tetapkan($this->bidangB, $this->pj2->id_pengguna);

        $this->assertSame($this->pj2->id_pengguna, $this->pjSistem($this->bidangA));
        $this->assertSame($this->pj2->id_pengguna, $this->pjSistem($this->bidangB));
    }

    // AC 3
    public function test_akun_yang_tidak_terhubung_ke_bidang_ditolak(): void
    {
        $this->actingAs($this->admin);

        // pj1 hanya terhubung ke bidang A
        $this->tetapkan($this->bidangB, $this->pj1->id_pengguna)
            ->assertSessionHasErrors('id_pengguna');

        $this->assertNull($this->pjSistem($this->bidangB));
    }

    public function test_akun_yang_bukan_penanggung_jawab_ditolak(): void
    {
        $biasa = $this->buatAkun('Anggota Bidang', 'anggota_uji', $this->bidangA->id_bidang);

        $this->actingAs($this->admin);

        $this->tetapkan($this->bidangA, $biasa->id_pengguna)
            ->assertSessionHasErrors('id_pengguna');

        $this->assertNull($this->pjSistem($this->bidangA));
    }

    public function test_akun_nonaktif_atau_terhapus_atau_tidak_ada_ditolak(): void
    {
        $nonaktif = $this->buatAkun('Penanggung Jawab', 'pj_nonaktif', null, 'nonaktif');
        $this->hubungkan($nonaktif, [$this->bidangA]);

        $terhapus = $this->buatAkun('Penanggung Jawab', 'pj_terhapus', null);
        $this->hubungkan($terhapus, [$this->bidangA]);
        $terhapus->delete();

        $this->actingAs($this->admin);

        foreach ([$nonaktif->id_pengguna, $terhapus->id_pengguna, 999999] as $id) {
            $this->tetapkan($this->bidangA, $id)->assertSessionHasErrors('id_pengguna');
        }

        $this->assertNull($this->pjSistem($this->bidangA));
    }

    public function test_bidang_tidak_ada_mengembalikan_404(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.bidang.penanggung-jawab-sistem.update', 999999), [
                'id_pengguna' => $this->pj1->id_pengguna,
            ])
            ->assertNotFound();
    }

    public function test_input_kosong_ditolak(): void
    {
        $this->actingAs($this->admin);

        $this->tetapkan($this->bidangA, '')->assertSessionHasErrors('id_pengguna');
        $this->tetapkan($this->bidangA, 'abc')->assertSessionHasErrors('id_pengguna');
    }

    // AC 4
    public function test_non_admin_ditolak(): void
    {
        $this->actingAs($this->pj1);
        $this->tetapkan($this->bidangA, $this->pj1->id_pengguna)->assertForbidden();
        $this->assertNull($this->pjSistem($this->bidangA));
    }

    public function test_tanpa_sesi_diarahkan_ke_login(): void
    {
        $this->tetapkan($this->bidangA, $this->pj1->id_pengguna)->assertRedirect(route('login'));
        $this->assertNull($this->pjSistem($this->bidangA));
    }

    // AC 5: bidang akun diubah dan bidang itu tidak lagi dipegang
    public function test_penanggung_jawab_dikosongkan_saat_dilepas_dari_bidang(): void
    {
        $this->actingAs($this->admin);

        $this->tetapkan($this->bidangA, $this->pj2->id_pengguna);
        $this->tetapkan($this->bidangB, $this->pj2->id_pengguna);

        // pj2 sekarang hanya memegang bidang B
        $this->put(route('admin.akun.bidang.update', $this->pj2->id_pengguna), [
            'bidang' => [$this->bidangB->nama_bidang],
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->pjSistem($this->bidangA));
        $this->assertSame($this->pj2->id_pengguna, $this->pjSistem($this->bidangB));
    }

    // AC 5: akun dihapus
    public function test_penanggung_jawab_dikosongkan_saat_akun_dihapus(): void
    {
        $this->actingAs($this->admin);

        $this->tetapkan($this->bidangA, $this->pj1->id_pengguna);

        $this->delete(route('admin.akun.destroy', $this->pj1->id_pengguna));

        $this->assertNull($this->pjSistem($this->bidangA));
    }
}
