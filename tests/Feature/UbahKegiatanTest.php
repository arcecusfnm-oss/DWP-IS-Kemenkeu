<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UbahKegiatanTest extends TestCase
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

    private function buatPimpinan(int $idBidang, string $username = 'pimpinan_uji092'): Pengguna
    {
        return $this->buatPengguna('Pimpinan Bidang', $idBidang, $username);
    }

    private function buatAnggota(int $idBidang, string $username = 'anggota_uji092'): Pengguna
    {
        return $this->buatPengguna('Anggota Bidang', $idBidang, $username);
    }

    private function buatProgram(int $idBidang, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('program_kerja')->insertGetId(array_merge([
            'id_bidang'        => $idBidang,
            'nama_program'     => 'Program Literasi Keuangan',
            'tujuan'            => 'Meningkatkan pengetahuan keuangan anggota.',
            'sasaran_peserta'  => 'Anggota DWP',
            'metode'             => 'Workshop',
            'tanggal_mulai'    => '2026-11-10',
            'tanggal_selesai'  => '2026-11-25',
            'tempat'              => 'Aula Kemenkeu',
            'anggaran'            => 2500000,
            'keterangan'          => null,
            'dibuat_oleh'        => $dibuatOleh,
            'created_at'         => '2026-01-01 00:00:00',
            'updated_at'         => '2026-01-01 00:00:00',
        ], $ubah), 'id_program_kerja');
    }

    private function buatKegiatan(int $idProgram, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('kegiatan')->insertGetId(array_merge([
            'id_program_kerja'  => $idProgram,
            'nama_kegiatan'     => 'Kegiatan Sebelum Diubah',
            'tanggal_mulai'    => '2026-11-12',
            'tanggal_selesai'  => '2026-11-15',
            'deskripsi'          => 'Deskripsi kegiatan sebelumnya.',
            'dibuat_oleh'       => $dibuatOleh,
            'dihapus_pada'     => null,
            'created_at'        => '2026-01-01 00:00:00',
            'updated_at'        => '2026-01-01 00:00:00',
        ], $ubah), 'id_kegiatan');
    }

    private function dataValid(array $ubah = []): array
    {
        return array_merge([
            'nama_kegiatan'     => 'Workshop Keuangan Digital yang Diperbarui',
            'tanggal_mulai'    => '2026-11-13',
            'tanggal_selesai'  => '2026-11-18',
            'deskripsi'          => 'Deskripsi kegiatan yang sudah diperbarui.',
        ], $ubah);
    }

    private function ubah(Pengguna $pelaku, int $idKegiatan, array $data)
    {
        return $this->actingAs($pelaku)
            ->put(route('kegiatan.update', ['id' => $idKegiatan]), $data);
    }

    private function namaKegiatan(int $idKegiatan): ?string
    {
        return DB::table('kegiatan')->where('id_kegiatan', $idKegiatan)->value('nama_kegiatan');
    }

    // ---------- Berhasil ----------

    public function test_pimpinan_bidang_berhasil_mengubah_kegiatan(): void
    {
        $bidang   = $this->buatBidang('Pendidikan');
        $pimpinan = $this->buatPimpinan($bidang);
        $program  = $this->buatProgram($bidang, $pimpinan->id_pengguna);
        $id       = $this->buatKegiatan($program, $pimpinan->id_pengguna);

        $this->ubah($pimpinan, $id, $this->dataValid())
            ->assertRedirect(route('kegiatan.index'))
            ->assertSessionHas('sukses', 'Kegiatan berhasil diubah.')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan'       => $id,
            'id_program_kerja'  => $program,
            'nama_kegiatan'     => 'Workshop Keuangan Digital yang Diperbarui',
            'tanggal_mulai'    => '2026-11-13',
            'tanggal_selesai'  => '2026-11-18',
            'deskripsi'          => 'Deskripsi kegiatan yang sudah diperbarui.',
            'dibuat_oleh'       => $pimpinan->id_pengguna,
        ]);
        $this->assertSame(1, DB::table('kegiatan')->count());
    }

    public function test_anggota_bidang_berhasil_mengubah_kegiatan(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid([
            'nama_kegiatan' => 'Kegiatan Diperbarui Anggota',
        ]))
            ->assertSessionHas('sukses', 'Kegiatan berhasil diubah.')
            ->assertSessionHasNoErrors();

        $this->assertSame('Kegiatan Diperbarui Anggota', $this->namaKegiatan($id));
    }

    public function test_permintaan_json_mengembalikan_200_dan_id_kegiatan(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->actingAs($anggota)
            ->putJson(route('kegiatan.update', ['id' => $id]), $this->dataValid([
                'nama_kegiatan' => 'Update via JSON',
            ]))
            ->assertOk()
            ->assertJson([
                'pesan'            => 'Kegiatan berhasil diubah.',
                'id_kegiatan'      => $id,
                'id_program_kerja' => $program,
            ])
            ->assertJsonStructure(['pesan', 'id_kegiatan', 'id_program_kerja']);

        $this->assertSame('Update via JSON', $this->namaKegiatan($id));
    }

    public function test_metode_patch_juga_diterima(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->actingAs($anggota)
            ->patch(route('kegiatan.update', ['id' => $id]), $this->dataValid([
                'nama_kegiatan' => 'Update PATCH',
            ]))
            ->assertSessionHas('sukses', 'Kegiatan berhasil diubah.');

        $this->assertSame('Update PATCH', $this->namaKegiatan($id));
    }

    public function test_update_memperbarui_updated_at(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid());

        $updatedAt = DB::table('kegiatan')
            ->where('id_kegiatan', $id)
            ->value('updated_at');

        $this->assertNotSame('2026-01-01 00:00:00', (string) $updatedAt);
    }

    // ---------- Validasi ----------

    public static function kolomWajib(): array
    {
        return [
            'nama kegiatan kosong'   => ['nama_kegiatan', 'Nama kegiatan wajib diisi'],
            'tanggal mulai kosong'  => ['tanggal_mulai', 'Tanggal mulai wajib diisi'],
            'tanggal selesai kosong' => ['tanggal_selesai', 'Tanggal selesai wajib diisi'],
            'deskripsi kosong'      => ['deskripsi', 'Deskripsi wajib diisi'],
        ];
    }

    #[DataProvider('kolomWajib')]
    public function test_kolom_wajib_kosong_ditolak(string $kolom, string $pesan): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid([$kolom => '']))
            ->assertSessionHasErrors([$kolom => $pesan]);

        $this->assertSame('Kegiatan Sebelum Diubah', $this->namaKegiatan($id));
    }

    public function test_json_dengan_kolom_wajib_kosong_mengembalikan_422(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->actingAs($anggota)
            ->putJson(route('kegiatan.update', ['id' => $id]), $this->dataValid([
                'nama_kegiatan' => '',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama_kegiatan');

        $this->assertSame('Kegiatan Sebelum Diubah', $this->namaKegiatan($id));
    }

    public function test_tanggal_selesai_sebelum_tanggal_mulai_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid([
            'tanggal_mulai'   => '2026-11-18',
            'tanggal_selesai' => '2026-11-13',
        ]))->assertSessionHasErrors([
            'tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
        ]);

        $this->assertSame(
            '2026-11-12',
            DB::table('kegiatan')->where('id_kegiatan', $id)->value('tanggal_mulai')
        );
        $this->assertSame(
            '2026-11-15',
            DB::table('kegiatan')->where('id_kegiatan', $id)->value('tanggal_selesai')
        );
    }

    public function test_format_tanggal_mulai_tidak_valid_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid([
            'tanggal_mulai' => '13-11-2026',
        ]))->assertSessionHasErrors([
            'tanggal_mulai' => 'Format tanggal mulai tidak valid',
        ]);
    }

    public function test_tanggal_kegiatan_di_luar_periode_program_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna, [
            'tanggal_mulai'   => '2026-11-10',
            'tanggal_selesai' => '2026-11-25',
        ]);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid([
            'tanggal_mulai'   => '2026-11-05',
            'tanggal_selesai' => '2026-11-30',
        ]))->assertSessionHasErrors([
            'tanggal_mulai'   => 'Tanggal mulai kegiatan harus dalam periode program (10-11-2026 s/d 25-11-2026)',
            'tanggal_selesai' => 'Tanggal selesai kegiatan harus dalam periode program (10-11-2026 s/d 25-11-2026)',
        ]);

        $this->assertSame('Kegiatan Sebelum Diubah', $this->namaKegiatan($id));
    }

    public function test_tanggal_mulai_dan_selesai_sama_dalam_periode_program_diterima(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid([
            'tanggal_mulai'   => '2026-11-20',
            'tanggal_selesai' => '2026-11-20',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan'      => $id,
            'tanggal_mulai'   => '2026-11-20',
            'tanggal_selesai' => '2026-11-20',
        ]);
    }

    // ---------- Hak akses & keamanan ----------

    public function test_kegiatan_program_bidang_lain_ditolak(): void
    {
        $bidangSaya = $this->buatBidang('Pendidikan');
        $bidangLain = $this->buatBidang('Ekonomi');
        $anggota    = $this->buatAnggota($bidangSaya);
        $pimpinanLain = $this->buatPimpinan($bidangLain, 'pimpinan_bidang_lain092');
        $programLain  = $this->buatProgram($bidangLain, $pimpinanLain->id_pengguna);
        $id           = $this->buatKegiatan($programLain, $pimpinanLain->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid([
            'nama_kegiatan' => 'Percobaan Ubah Bidang Lain',
        ]))->assertForbidden();

        $this->assertSame('Kegiatan Sebelum Diubah', $this->namaKegiatan($id));
    }

    public function test_id_program_kerja_dan_dibuat_oleh_dari_request_diabaikan(): void
    {
        $bidangSaya = $this->buatBidang('Pendidikan');
        $bidangLain = $this->buatBidang('Ekonomi');
        $anggota    = $this->buatAnggota($bidangSaya);
        $pembuatLain = $this->buatPimpinan($bidangLain, 'pimpinan_spoof092');
        $program      = $this->buatProgram($bidangSaya, $anggota->id_pengguna);
        $programLain  = $this->buatProgram($bidangLain, $pembuatLain->id_pengguna);
        $id            = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid([
            'nama_kegiatan'   => 'Nama Aman Diperbarui',
            'id_program_kerja' => $programLain,
            'dibuat_oleh'    => $pembuatLain->id_pengguna,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan'      => $id,
            'id_program_kerja' => $program,
            'dibuat_oleh'     => $anggota->id_pengguna,
            'nama_kegiatan'   => 'Nama Aman Diperbarui',
        ]);
    }

    public function test_kegiatan_yang_tidak_ada_mengembalikan_404(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);

        $this->ubah($anggota, 99999, $this->dataValid())->assertNotFound();
    }

    public function test_kegiatan_soft_deleted_mengembalikan_404(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $id      = $this->buatKegiatan($program, $anggota->id_pengguna, [
            'dihapus_pada' => '2026-11-26 12:00:00',
    ]);

        $this->ubah($anggota, $id, $this->dataValid([
            'nama_kegiatan' => 'Tidak Boleh Diubah',
        ]))->assertNotFound();

        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan'   => $id,
            'nama_kegiatan' => 'Kegiatan Sebelum Diubah',
            'dihapus_pada' => '2026-11-26 12:00:00',
        ]);
    }

    public function test_pengguna_tanpa_bidang_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $pembuat = $this->buatPimpinan($bidang);
        $program = $this->buatProgram($bidang, $pembuat->id_pengguna);
        $id      = $this->buatKegiatan($program, $pembuat->id_pengguna);
        $tanpaBidang = $this->buatPengguna('Anggota Bidang', null, 'anggota_tanpa_bidang092');

        $this->actingAs($tanpaBidang)
            ->putJson(route('kegiatan.update', ['id' => $id]), $this->dataValid([
                'nama_kegiatan' => 'Tidak Boleh Diubah',
            ]))
            ->assertStatus(403)
            ->assertJson(['pesan' => 'Akun Anda belum terhubung dengan bidang.']);

        $this->assertSame('Kegiatan Sebelum Diubah', $this->namaKegiatan($id));
    }

    public static function peranYangDitolak(): array
    {
        return [
            'Admin'            => ['Admin'],
            'Penasihat'        => ['Penasihat'],
            'Penanggung Jawab' => ['Penanggung Jawab'],
            'Bendahara'        => ['Bendahara'],
        ];
    }

    #[DataProvider('peranYangDitolak')]
    public function test_peran_selain_pimpinan_dan_anggota_bidang_ditolak(string $peran): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $pembuat = $this->buatPimpinan($bidang);
        $program = $this->buatProgram($bidang, $pembuat->id_pengguna);
        $id      = $this->buatKegiatan($program, $pembuat->id_pengguna);
        $akun    = $this->buatPengguna($peran, $bidang, 'akun_peran_ditolak092');

        $this->ubah($akun, $id, $this->dataValid([
            'nama_kegiatan' => 'Tidak Boleh Diubah',
        ]))->assertForbidden();

        $this->assertSame('Kegiatan Sebelum Diubah', $this->namaKegiatan($id));
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_login(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $pembuat = $this->buatPimpinan($bidang);
        $program = $this->buatProgram($bidang, $pembuat->id_pengguna);
        $id      = $this->buatKegiatan($program, $pembuat->id_pengguna);

        $this->put(route('kegiatan.update', ['id' => $id]), $this->dataValid([
            'nama_kegiatan' => 'Percobaan tanpa login',
        ]))->assertRedirect(route('login'));

        $this->assertSame('Kegiatan Sebelum Diubah', $this->namaKegiatan($id));
    }
}
