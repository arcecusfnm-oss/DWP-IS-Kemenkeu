<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-105 (B-046): pengujian endpoint ubah program kerja (T-103) dan form ubah (T-104).
 * Peran yang boleh: Pimpinan Bidang dan Anggota Bidang, hanya untuk bidangnya sendiri.
 * Skenario: berhasil, program bidang lain, tanggal terbalik, kolom kosong, peran lain, log.
 */
class UbahProgramKerjaTest extends TestCase
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

    private function buatPimpinan(int $idBidang, string $username = 'pimpinan_uji105'): Pengguna
    {
        return $this->buatPengguna('Pimpinan Bidang', $idBidang, $username);
    }

    private function buatAnggota(int $idBidang, string $username = 'anggota_uji105'): Pengguna
    {
        return $this->buatPengguna('Anggota Bidang', $idBidang, $username);
    }

    private function dataValid(array $ubah = []): array
    {
        return array_merge([
            'nama_program'    => 'Pelatihan Kewirausahaan',
            'tujuan'          => 'Meningkatkan keterampilan usaha anggota.',
            'sasaran_peserta' => 'Anggota DWP',
            'metode'          => 'Pelatihan luring',
            'tanggal_mulai'   => '2026-12-01',
            'tanggal_selesai' => '2026-12-10',
            'tempat'          => 'Aula Kemenkeu',
            'anggaran'        => 2500000,
            'keterangan'      => 'Bawa alat tulis.',
        ], $ubah);
    }

    /** Buat program kerja langsung di DB. updated_at sengaja dibuat lama. */
    private function buatProgram(int $idBidang, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('program_kerja')->insertGetId(array_merge(
            $this->dataValid(),
            [
                'id_bidang'   => $idBidang,
                'dibuat_oleh' => $dibuatOleh,
                'created_at'  => '2026-01-01 00:00:00',
                'updated_at'  => '2026-01-01 00:00:00',
            ],
            $ubah
        ), 'id_program_kerja');
    }

    private function ubah(Pengguna $pelaku, int $idProgram, array $data)
    {
        return $this->actingAs($pelaku)
            ->put(route('program-kerja.update', ['id' => $idProgram]), $data);
    }

    private function namaProgram(int $idProgram): ?string
    {
        return DB::table('program_kerja')->where('id_program_kerja', $idProgram)->value('nama_program');
    }

    // ---------- 1. Berhasil ----------

    public function test_pimpinan_bidang_berhasil_mengubah_program_kerja(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna, ['nama_program' => 'Program Awal']);

        $res = $this->ubah($pimpinan, $id, $this->dataValid([
            'nama_program'    => 'Program Diperbarui',
            'tanggal_selesai' => '2026-12-20',
            'anggaran'        => 3000000,
        ]));

        $res->assertRedirect(route('program-kerja.bidang-saya'));
        $res->assertSessionHas('sukses', 'Program kerja berhasil diubah.');
        $res->assertSessionHasNoErrors();

        $this->assertDatabaseHas('program_kerja', [
            'id_program_kerja' => $id,
            'id_bidang'        => $bidang,
            'dibuat_oleh'      => $pimpinan->id_pengguna,
            'nama_program'     => 'Program Diperbarui',
            'tanggal_mulai'    => '2026-12-01',
            'tanggal_selesai'  => '2026-12-20',
        ]);
        $this->assertEquals(3000000, (float) DB::table('program_kerja')->where('id_program_kerja', $id)->value('anggaran'));
        $this->assertSame(1, DB::table('program_kerja')->count());
    }

    public function test_perubahan_memperbarui_updated_at(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->ubah($pimpinan, $id, $this->dataValid(['nama_program' => 'Nama Berubah']));

        $baru = DB::table('program_kerja')->where('id_program_kerja', $id)->value('updated_at');
        $this->assertNotSame('2026-01-01 00:00:00', (string) $baru);
    }

    public function test_permintaan_json_mengembalikan_200_dan_id_program(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->actingAs($pimpinan)
            ->putJson(route('program-kerja.update', ['id' => $id]), $this->dataValid(['nama_program' => 'Via JSON']))
            ->assertOk()
            ->assertJson(['pesan' => 'Program kerja berhasil diubah.', 'id_program_kerja' => $id]);

        $this->assertSame('Via JSON', $this->namaProgram($id));
    }

    public function test_metode_patch_juga_diterima(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->actingAs($pimpinan)
            ->patch(route('program-kerja.update', ['id' => $id]), $this->dataValid(['nama_program' => 'Via PATCH']))
            ->assertSessionHas('sukses', 'Program kerja berhasil diubah.');

        $this->assertSame('Via PATCH', $this->namaProgram($id));
    }

    public function test_anggota_bidang_berhasil_mengubah_program_bidangnya(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $anggota = $this->buatAnggota($bidang);
        $id      = $this->buatProgram($bidang, $anggota->id_pengguna);

        $this->ubah($anggota, $id, $this->dataValid(['nama_program' => 'Diubah Anggota']))
            ->assertSessionHas('sukses', 'Program kerja berhasil diubah.');

        $this->assertSame('Diubah Anggota', $this->namaProgram($id));
    }

    public function test_kolom_opsional_boleh_dikosongkan(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->ubah($pimpinan, $id, [
            'nama_program'    => 'Program Minimal',
            'tujuan'          => 'Uji kolom opsional.',
            'tanggal_mulai'   => '2026-12-01',
            'tanggal_selesai' => '2026-12-10',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('program_kerja', [
            'id_program_kerja' => $id,
            'nama_program'     => 'Program Minimal',
            'sasaran_peserta'  => null,
            'metode'           => null,
            'tempat'           => null,
            'anggaran'         => null,
            'keterangan'       => null,
        ]);
    }

    public function test_tanpa_perubahan_tidak_menyentuh_data_dan_tidak_dicatat_di_log(): void
    {
        Log::spy();

        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->ubah($pimpinan, $id, $this->dataValid())
            ->assertSessionHas('sukses', 'Tidak ada perubahan pada program kerja.');

        $this->assertSame(
            '2026-01-01 00:00:00',
            (string) DB::table('program_kerja')->where('id_program_kerja', $id)->value('updated_at')
        );

        Log::shouldNotHaveReceived('info', ['Program kerja diubah', \Mockery::any()]);
    }

    // ---------- 2. Program bidang lain ----------

    public function test_program_bidang_lain_ditolak_dan_data_tidak_berubah(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $pimpinan   = $this->buatPimpinan($bidangSaya);
        $orangLain  = $this->buatPimpinan($bidangLain, 'pimpinan_lain105');
        $idLain     = $this->buatProgram($bidangLain, $orangLain->id_pengguna, ['nama_program' => 'Program Ekonomi']);

        $this->ubah($pimpinan, $idLain, $this->dataValid(['nama_program' => 'Dibajak']))
            ->assertForbidden();

        $this->assertSame('Program Ekonomi', $this->namaProgram($idLain));
        $this->assertDatabaseMissing('program_kerja', ['nama_program' => 'Dibajak']);
    }

    public function test_program_bidang_lain_ditolak_juga_untuk_permintaan_json(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $anggota    = $this->buatAnggota($bidangSaya);
        $orangLain  = $this->buatPimpinan($bidangLain, 'pimpinan_lain105');
        $idLain     = $this->buatProgram($bidangLain, $orangLain->id_pengguna, ['nama_program' => 'Program Ekonomi']);

        $this->actingAs($anggota)
            ->putJson(route('program-kerja.update', ['id' => $idLain]), $this->dataValid(['nama_program' => 'Dibajak']))
            ->assertForbidden();

        $this->assertSame('Program Ekonomi', $this->namaProgram($idLain));
    }

    public function test_id_bidang_dan_dibuat_oleh_dari_request_diabaikan(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $pimpinan   = $this->buatPimpinan($bidangSaya);
        $orangLain  = $this->buatPimpinan($bidangLain, 'pimpinan_lain105');
        $id         = $this->buatProgram($bidangSaya, $pimpinan->id_pengguna);

        $this->ubah($pimpinan, $id, $this->dataValid([
            'nama_program' => 'Nama Baru',
            'id_bidang'    => $bidangLain,
            'dibuat_oleh'  => $orangLain->id_pengguna,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('program_kerja', [
            'id_program_kerja' => $id,
            'nama_program'     => 'Nama Baru',
            'id_bidang'        => $bidangSaya,
            'dibuat_oleh'      => $pimpinan->id_pengguna,
        ]);
    }

    public function test_program_yang_tidak_ada_mengembalikan_404(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $this->ubah($pimpinan, 99999, $this->dataValid())->assertNotFound();
    }

    public function test_pimpinan_tanpa_bidang_ditolak(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pembuat  = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pembuat->id_pengguna, ['nama_program' => 'Program Awal']);
        $tanpa    = $this->buatPengguna('Pimpinan Bidang', null, 'pimpinan_tanpa_bidang105');

        $this->actingAs($tanpa)
            ->putJson(route('program-kerja.update', ['id' => $id]), $this->dataValid(['nama_program' => 'Dibajak']))
            ->assertStatus(403)
            ->assertJson(['pesan' => 'Akun Anda belum terhubung dengan bidang.']);

        $this->assertSame('Program Awal', $this->namaProgram($id));
    }

    // ---------- 3. Tanggal terbalik ----------

    public function test_tanggal_selesai_sebelum_tanggal_mulai_ditolak(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna, ['nama_program' => 'Program Awal']);

        $res = $this->ubah($pimpinan, $id, $this->dataValid([
            'nama_program'    => 'Tidak Boleh Tersimpan',
            'tanggal_mulai'   => '2026-12-10',
            'tanggal_selesai' => '2026-12-01',
        ]));

        $res->assertSessionHasErrors([
            'tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
        ]);
        $this->assertSame('Program Awal', $this->namaProgram($id));
        $this->assertDatabaseHas('program_kerja', [
            'id_program_kerja' => $id,
            'tanggal_mulai'    => '2026-12-01',
            'tanggal_selesai'  => '2026-12-10',
        ]);
    }

    public function test_tanggal_selesai_sama_dengan_tanggal_mulai_diterima(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->ubah($pimpinan, $id, $this->dataValid([
            'tanggal_mulai'   => '2026-12-05',
            'tanggal_selesai' => '2026-12-05',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('program_kerja', [
            'id_program_kerja' => $id,
            'tanggal_mulai'    => '2026-12-05',
            'tanggal_selesai'  => '2026-12-05',
        ]);
    }

    public function test_format_tanggal_tidak_valid_ditolak(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->ubah($pimpinan, $id, $this->dataValid(['tanggal_mulai' => '10-12-2026']))
            ->assertSessionHasErrors(['tanggal_mulai' => 'Format tanggal mulai tidak valid']);
    }

    // ---------- 4. Kolom wajib kosong ----------

    public static function kolomWajib(): array
    {
        return [
            'nama program'    => ['nama_program', 'Nama program wajib diisi'],
            'tujuan'          => ['tujuan', 'Tujuan wajib diisi'],
            'tanggal mulai'   => ['tanggal_mulai', 'Tanggal mulai wajib diisi'],
            'tanggal selesai' => ['tanggal_selesai', 'Tanggal selesai wajib diisi'],
        ];
    }

    #[DataProvider('kolomWajib')]
    public function test_kolom_wajib_yang_kosong_ditolak(string $kolom, string $pesan): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna, ['nama_program' => 'Program Awal']);

        $this->ubah($pimpinan, $id, $this->dataValid([$kolom => '']))
            ->assertSessionHasErrors([$kolom => $pesan]);

        $this->assertSame('Program Awal', $this->namaProgram($id));
    }

    public function test_permintaan_json_dengan_kolom_kosong_mengembalikan_422(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->actingAs($pimpinan)
            ->putJson(route('program-kerja.update', ['id' => $id]), $this->dataValid(['nama_program' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama_program');
    }

    // ---------- 5. Peran lain dan tanpa sesi ----------

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
        $bidang  = $this->buatBidang('Sosial Budaya');
        $pembuat = $this->buatPimpinan($bidang);
        $id      = $this->buatProgram($bidang, $pembuat->id_pengguna, ['nama_program' => 'Program Awal']);
        $akun    = $this->buatPengguna($peran, $bidang, 'akun_peran_lain105');

        $this->ubah($akun, $id, $this->dataValid(['nama_program' => 'Dibajak']))->assertForbidden();

        $this->assertSame('Program Awal', $this->namaProgram($id));
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $pembuat = $this->buatPimpinan($bidang);
        $id      = $this->buatProgram($bidang, $pembuat->id_pengguna, ['nama_program' => 'Program Awal']);

        $this->put(route('program-kerja.update', ['id' => $id]), $this->dataValid(['nama_program' => 'Dibajak']))
            ->assertRedirect(route('login'));

        $this->assertSame('Program Awal', $this->namaProgram($id));
    }

    // ---------- 6. Log ----------

    public function test_perubahan_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->ubah($pimpinan, $id, $this->dataValid([
            'nama_program' => 'Nama Baru Uji',
            'tujuan'       => 'Tujuan baru uji.',
        ]));

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($pimpinan, $bidang, $id) {
                $json   = json_encode($konteks);
                $kolom  = $konteks['kolom_berubah'] ?? [];
                sort($kolom);

                return $pesan === 'Program kerja diubah'
                    && $konteks['peran'] === 'Pimpinan Bidang'
                    && $konteks['pengubah_id'] === $pimpinan->id_pengguna
                    && $konteks['pengubah_username'] === $pimpinan->nama_pengguna
                    && (int) $konteks['id_program_kerja'] === $id
                    && (int) $konteks['id_bidang'] === $bidang
                    && $kolom === ['nama_program', 'tujuan']
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, 'Nama Baru Uji')
                    && ! str_contains($json, 'Tujuan baru uji.')
                    && ! str_contains($json, 'Password123!')
                    && ! str_contains($json, (string) $pimpinan->kata_sandi_hash);
            })
            ->once();
    }

    public function test_percobaan_ubah_program_bidang_lain_tercatat_sebagai_peringatan(): void
    {
        Log::spy();

        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $pimpinan   = $this->buatPimpinan($bidangSaya);
        $orangLain  = $this->buatPimpinan($bidangLain, 'pimpinan_lain105');
        $idLain     = $this->buatProgram($bidangLain, $orangLain->id_pengguna);

        $this->ubah($pimpinan, $idLain, $this->dataValid(['nama_program' => 'Dibajak']));

        Log::shouldHaveReceived('warning')
            ->withArgs(function ($pesan, $konteks = []) use ($pimpinan, $idLain) {
                return $pesan === 'Ubah program kerja bidang lain ditolak'
                    && $konteks['pengguna_id'] === $pimpinan->id_pengguna
                    && (int) $konteks['id_program_kerja'] === $idLain
                    && ! str_contains(json_encode($konteks), 'Dibajak');
            })
            ->once();
    }

    // ---------- 7. Form ubah (T-104) ----------

    public function test_form_ubah_tampil_terisi_untuk_pimpinan_dan_anggota(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $pembuat = $this->buatPimpinan($bidang);
        $id      = $this->buatProgram($bidang, $pembuat->id_pengguna, ['nama_program' => 'Program Terisi']);

        foreach ([$pembuat, $this->buatAnggota($bidang)] as $akun) {
            $this->actingAs($akun)
                ->get(route('program-kerja.edit', ['id' => $id]))
                ->assertOk()
                ->assertViewIs('program-kerja.edit')
                ->assertSee('Ubah Program Kerja')
                ->assertSee('Program Terisi');
        }
    }

    public function test_form_ubah_program_bidang_lain_ditolak(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $pimpinan   = $this->buatPimpinan($bidangSaya);
        $orangLain  = $this->buatPimpinan($bidangLain, 'pimpinan_lain105');
        $idLain     = $this->buatProgram($bidangLain, $orangLain->id_pengguna);

        $this->actingAs($pimpinan)
            ->get(route('program-kerja.edit', ['id' => $idLain]))
            ->assertForbidden();
    }

    public function test_form_ubah_program_tidak_ada_404_peran_lain_403_tanpa_sesi_ke_login(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $pembuat = $this->buatPimpinan($bidang);
        $id      = $this->buatProgram($bidang, $pembuat->id_pengguna);

        $this->actingAs($pembuat)->get(route('program-kerja.edit', ['id' => 99999]))->assertNotFound();

        $this->actingAs($this->buatPengguna('Admin', $bidang, 'admin_form105'))
            ->get(route('program-kerja.edit', ['id' => $id]))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->get(route('program-kerja.edit', ['id' => $id]))->assertRedirect(route('login'));
    }

    // ---------- 8. Tampilan daftar ----------

    public function test_daftar_bidang_sendiri_menampilkan_tombol_ubah(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);
        $id       = $this->buatProgram($bidang, $pimpinan->id_pengguna);

        $this->actingAs($pimpinan)
            ->get(route('program-kerja.bidang-saya'))
            ->assertOk()
            ->assertSee("program-kerja/{$id}/edit", false);
    }

    public function test_daftar_bidang_lain_tidak_menampilkan_aksi_ubah(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $pimpinan   = $this->buatPimpinan($bidangSaya);
        $orangLain  = $this->buatPimpinan($bidangLain, 'pimpinan_lain105');
        $idLain     = $this->buatProgram($bidangLain, $orangLain->id_pengguna, ['nama_program' => 'Program Ekonomi']);

        $this->actingAs($pimpinan)
            ->get(route('program-kerja.bidang-lain'))
            ->assertOk()
            ->assertSee('Program Ekonomi')
            ->assertDontSee("program-kerja/{$idLain}/edit", false);
    }
}