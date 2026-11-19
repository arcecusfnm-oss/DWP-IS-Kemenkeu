<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-111 (B-048): pengujian endpoint tambah kegiatan (T-109) dan form/daftar kegiatan (T-110).
 * Peran yang boleh: Anggota Bidang (US-127), hanya untuk program kerja milik bidangnya sendiri.
 * Skenario: berhasil (jalur program & dropdown), periode program, kolom kosong,
 *           peran lain/bidang lain/tanpa sesi, relasi antar program, escape output & log.
 */
class TambahKegiatanTest extends TestCase
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

    private function buatAnggota(int $idBidang, string $username = 'anggota_uji111'): Pengguna
    {
        return $this->buatPengguna('Anggota Bidang', $idBidang, $username);
    }

    private function buatProgram(int $idBidang, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('program_kerja')->insertGetId(array_merge([
            'id_bidang'       => $idBidang,
            'nama_program'    => 'Pelatihan Literasi Keuangan',
            'tujuan'          => 'Meningkatkan literasi keuangan keluarga.',
            'sasaran_peserta' => 'Anggota DWP',
            'metode'          => 'Workshop',
            'tanggal_mulai'   => '2026-11-10',
            'tanggal_selesai' => '2026-11-25',
            'tempat'          => 'Aula Kemenkeu',
            'anggaran'        => 5000000,
            'keterangan'      => null,
            'dibuat_oleh'     => $dibuatOleh,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $ubah), 'id_program_kerja');
    }

    private function dataValid(array $ubah = []): array
    {
        return array_merge([
            'nama_kegiatan'   => 'Workshop Manajemen Keuangan Digital',
            'tanggal_mulai'   => '2026-11-12',
            'tanggal_selesai' => '2026-11-15',
            'deskripsi'       => 'Sesi praktik penyusunan anggaran rumah tangga.',
        ], $ubah);
    }

    private function kirim(Pengguna $pelaku, int $idProgram, array $data)
    {
        return $this->actingAs($pelaku)
            ->post(route('program-kerja.kegiatan.store', ['id' => $idProgram]), $data);
    }

    // ---------- 1. Berhasil (AC 1) ----------

    public function test_anggota_bidang_berhasil_menambah_kegiatan_dari_program_kerja(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);

        $res = $this->kirim($anggota, $program, $this->dataValid());

        $res->assertRedirect(route('kegiatan.index'));
        $res->assertSessionHas('sukses', 'Kegiatan berhasil ditambahkan.');
        $res->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kegiatan', [
            'id_program_kerja' => $program,
            'nama_kegiatan'    => 'Workshop Manajemen Keuangan Digital',
            'tanggal_mulai'    => '2026-11-12',
            'tanggal_selesai'  => '2026-11-15',
            'deskripsi'        => 'Sesi praktik penyusunan anggaran rumah tangga.',
            'dibuat_oleh'      => $anggota->id_pengguna,
        ]);
        $this->assertSame(1, DB::table('kegiatan')->count());
    }

    public function test_anggota_bidang_berhasil_menambah_kegiatan_lewat_dropdown_daftar_kegiatan(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);

        $res = $this->actingAs($anggota)
            ->post(route('kegiatan.store'), $this->dataValid([
                'id_program_kerja' => $program,
                'nama_kegiatan'    => 'Kegiatan Jalur Dropdown',
            ]));

        $res->assertRedirect(route('kegiatan.index'));
        $res->assertSessionHas('sukses', 'Kegiatan berhasil ditambahkan.');

        $this->assertDatabaseHas('kegiatan', [
            'id_program_kerja' => $program,
            'nama_kegiatan'    => 'Kegiatan Jalur Dropdown',
            'dibuat_oleh'      => $anggota->id_pengguna,
        ]);
    }

    public function test_permintaan_json_mengembalikan_201_dan_id_kegiatan(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);

        $this->actingAs($anggota)
            ->postJson(route('program-kerja.kegiatan.store', ['id' => $program]), $this->dataValid())
            ->assertCreated()
            ->assertJson([
                'pesan'            => 'Kegiatan berhasil ditambahkan.',
                'id_program_kerja' => $program,
            ])
            ->assertJsonStructure(['pesan', 'id_kegiatan', 'id_program_kerja']);
    }

    public function test_kegiatan_yang_ditambahkan_tampil_di_halaman_daftar_kegiatan(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna, [
            'nama_program' => 'Program Literasi Anak',
        ]);

        $this->kirim($anggota, $program, $this->dataValid([
            'nama_kegiatan' => 'Seminar Parenting Digital',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($anggota)
            ->get(route('kegiatan.index'))
            ->assertOk()
            ->assertViewIs('kegiatan.index')
            ->assertSee('Seminar Parenting Digital')
            ->assertSee('Program Literasi Anak');
    }

    // ---------- 2. Validasi tanggal & periode program (AC 2) ----------

    public function test_tanggal_kegiatan_sebelum_periode_program_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna, [
            'tanggal_mulai'   => '2026-11-10',
            'tanggal_selesai' => '2026-11-25',
        ]);

        $this->kirim($anggota, $program, $this->dataValid([
            'tanggal_mulai'   => '2026-11-05',
            'tanggal_selesai' => '2026-11-15',
        ]))->assertSessionHasErrors([
            'tanggal_mulai' => 'Tanggal mulai kegiatan harus dalam periode program (10-11-2026 s/d 25-11-2026)',
        ]);

        $this->assertSame(0, DB::table('kegiatan')->count());
    }

    public function test_tanggal_kegiatan_melewati_akhir_periode_program_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna, [
            'tanggal_mulai'   => '2026-11-10',
            'tanggal_selesai' => '2026-11-25',
        ]);

        $this->kirim($anggota, $program, $this->dataValid([
            'tanggal_mulai'   => '2026-11-20',
            'tanggal_selesai' => '2026-11-30',
        ]))->assertSessionHasErrors([
            'tanggal_selesai' => 'Tanggal selesai kegiatan harus dalam periode program (10-11-2026 s/d 25-11-2026)',
        ]);

        $this->assertSame(0, DB::table('kegiatan')->count());
    }

    public function test_tanggal_kegiatan_tepat_di_batas_periode_program_diterima(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna, [
            'tanggal_mulai'   => '2026-11-10',
            'tanggal_selesai' => '2026-11-25',
        ]);

        $this->kirim($anggota, $program, $this->dataValid([
            'tanggal_mulai'   => '2026-11-10',
            'tanggal_selesai' => '2026-11-25',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('kegiatan')->count());
    }

    public function test_tanggal_selesai_sebelum_tanggal_mulai_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);

        $this->kirim($anggota, $program, $this->dataValid([
            'tanggal_mulai'   => '2026-11-20',
            'tanggal_selesai' => '2026-11-15',
        ]))->assertSessionHasErrors([
            'tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
        ]);

        $this->assertSame(0, DB::table('kegiatan')->count());
    }

    // ---------- 3. Kolom wajib kosong (AC 3) ----------

    public static function kolomWajib(): array
    {
        return [
            'nama kegiatan'   => ['nama_kegiatan', 'Nama kegiatan wajib diisi'],
            'tanggal mulai'   => ['tanggal_mulai', 'Tanggal mulai wajib diisi'],
            'tanggal selesai' => ['tanggal_selesai', 'Tanggal selesai wajib diisi'],
            'deskripsi'       => ['deskripsi', 'Deskripsi wajib diisi'],
        ];
    }

    #[DataProvider('kolomWajib')]
    public function test_kolom_wajib_yang_kosong_ditolak(string $kolom, string $pesan): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);

        $this->kirim($anggota, $program, $this->dataValid([$kolom => '']))
            ->assertSessionHasErrors([$kolom => $pesan]);

        $this->assertSame(0, DB::table('kegiatan')->count());
    }

    public function test_jalur_dropdown_tanpa_memilih_program_kerja_ditolak(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);

        $this->actingAs($anggota)
            ->post(route('kegiatan.store'), $this->dataValid(['id_program_kerja' => '']))
            ->assertSessionHasErrors([
                'id_program_kerja' => 'Pilih program kerja terlebih dahulu',
            ]);

        $this->assertSame(0, DB::table('kegiatan')->count());
    }

    // ---------- 4. Hak akses, bidang lain, peran lain, tanpa sesi (AC 4) ----------

    public function test_program_bidang_lain_ditolak_di_form_dan_endpoint(): void
    {
        $bidangSaya = $this->buatBidang('Pendidikan');
        $bidangLain = $this->buatBidang('Ekonomi');
        $anggota    = $this->buatAnggota($bidangSaya);
        $pemilik    = $this->buatAnggota($bidangLain, 'anggota_lain111');
        $programLain = $this->buatProgram($bidangLain, $pemilik->id_pengguna);

        $this->actingAs($anggota)
            ->get(route('program-kerja.kegiatan.create', ['id' => $programLain]))
            ->assertForbidden();

        $this->kirim($anggota, $programLain, $this->dataValid())
            ->assertForbidden();

        $this->actingAs($anggota)
            ->post(route('kegiatan.store'), $this->dataValid(['id_program_kerja' => $programLain]))
            ->assertForbidden();

        $this->assertSame(0, DB::table('kegiatan')->count());
    }

    public function test_dibuat_oleh_dari_request_diabaikan(): void
    {
        $bidang   = $this->buatBidang('Pendidikan');
        $anggota1 = $this->buatAnggota($bidang, 'anggota_satu111');
        $anggota2 = $this->buatAnggota($bidang, 'anggota_dua111');
        $program  = $this->buatProgram($bidang, $anggota1->id_pengguna);

        $this->kirim($anggota1, $program, $this->dataValid([
            'dibuat_oleh' => $anggota2->id_pengguna,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kegiatan', [
            'id_program_kerja' => $program,
            'dibuat_oleh'      => $anggota1->id_pengguna,
        ]);
    }

    public static function peranYangDitolak(): array
    {
        return [
            'Pimpinan Bidang'  => ['Pimpinan Bidang'],
            'Admin'            => ['Admin'],
            'Penasihat'        => ['Penasihat'],
            'Penanggung Jawab' => ['Penanggung Jawab'],
            'Bendahara'        => ['Bendahara'],
        ];
    }

    #[DataProvider('peranYangDitolak')]
    public function test_peran_selain_anggota_bidang_ditolak(string $peran): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $pembuat = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $pembuat->id_pengguna);
        $akun    = $this->buatPengguna($peran, $bidang, 'peran_lain111');

        $this->actingAs($akun)->get(route('kegiatan.index'))->assertForbidden();
        $this->actingAs($akun)->get(route('kegiatan.create'))->assertForbidden();
        $this->actingAs($akun)->get(route('program-kerja.kegiatan.create', ['id' => $program]))->assertForbidden();
        $this->kirim($akun, $program, $this->dataValid())->assertForbidden();

        $this->assertSame(0, DB::table('kegiatan')->count());
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_login(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $pembuat = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $pembuat->id_pengguna);

        $this->get(route('kegiatan.index'))->assertRedirect(route('login'));
        $this->get(route('kegiatan.create'))->assertRedirect(route('login'));
        $this->get(route('program-kerja.kegiatan.create', ['id' => $program]))->assertRedirect(route('login'));
        $this->post(route('program-kerja.kegiatan.store', ['id' => $program]), $this->dataValid())
            ->assertRedirect(route('login'));

        $this->assertSame(0, DB::table('kegiatan')->count());
    }

    public function test_program_tidak_ditemukan_mengembalikan_404(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);

        $this->actingAs($anggota)
            ->get(route('program-kerja.kegiatan.create', ['id' => 99999]))
            ->assertNotFound();

        $this->kirim($anggota, 99999, $this->dataValid())
            ->assertNotFound();
    }

    // ---------- 5. Relasi program kerja & isolasi antar program (AC 5) ----------

    public function test_kegiatan_tersimpan_pada_program_yang_benar_dan_tidak_muncul_di_program_lain(): void
    {
        $bidang   = $this->buatBidang('Pendidikan');
        $anggota  = $this->buatAnggota($bidang);
        $programA = $this->buatProgram($bidang, $anggota->id_pengguna, ['nama_program' => 'Program A']);
        $programB = $this->buatProgram($bidang, $anggota->id_pengguna, ['nama_program' => 'Program B']);

        $this->kirim($anggota, $programA, $this->dataValid([
            'nama_kegiatan' => 'Kegiatan Khusus Program A',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($anggota)
            ->get(route('kegiatan.index', ['program' => $programA]))
            ->assertOk()
            ->assertSee('Kegiatan Khusus Program A');

        $this->actingAs($anggota)
            ->get(route('kegiatan.index', ['program' => $programB]))
            ->assertOk()
            ->assertDontSee('Kegiatan Khusus Program A');
    }

    // ---------- 6. Escape output & log tanpa data sensitif (AC 6) ----------

    public function test_output_nama_kegiatan_di_escape_untuk_mencegah_xss(): void
    {
        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $payload = '<script>alert("xss")</script>';

        $this->kirim($anggota, $program, $this->dataValid([
            'nama_kegiatan' => $payload,
        ]))->assertSessionHasNoErrors();

        $this->actingAs($anggota)
            ->get(route('kegiatan.index'))
            ->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee(e($payload), false);
    }

    public function test_penambahan_kegiatan_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $bidang  = $this->buatBidang('Pendidikan');
        $anggota = $this->buatAnggota($bidang);
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);

        $this->kirim($anggota, $program, $this->dataValid([
            'nama_kegiatan' => 'Pelatihan Bendahara',
            'deskripsi'     => 'Detail sensitif deskripsi kegiatan.',
        ]));

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($anggota, $bidang, $program) {
                $json = json_encode($konteks);

                return $pesan === 'Kegiatan ditambahkan'
                    && $konteks['pembuat_id'] === $anggota->id_pengguna
                    && $konteks['pembuat_username'] === $anggota->nama_pengguna
                    && $konteks['peran'] === 'Anggota Bidang'
                    && (int) $konteks['id_program_kerja'] === $program
                    && (int) $konteks['id_bidang'] === $bidang
                    && $konteks['nama_kegiatan'] === 'Pelatihan Bendahara'
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, 'Detail sensitif deskripsi kegiatan.')
                    && ! str_contains($json, 'Password123!')
                    && ! str_contains($json, (string) $anggota->kata_sandi_hash);
            })
            ->once();
    }

    public function test_percobaan_tambah_kegiatan_bidang_lain_tercatat_di_log_warning(): void
    {
        Log::spy();

        $bidangSaya  = $this->buatBidang('Pendidikan');
        $bidangLain  = $this->buatBidang('Ekonomi');
        $anggota     = $this->buatAnggota($bidangSaya);
        $pemilik     = $this->buatAnggota($bidangLain, 'anggota_lain111');
        $programLain = $this->buatProgram($bidangLain, $pemilik->id_pengguna);

        $this->kirim($anggota, $programLain, $this->dataValid());

        Log::shouldHaveReceived('warning')
            ->withArgs(function ($pesan, $konteks = []) use ($anggota, $programLain) {
                return $pesan === 'Tambah kegiatan pada program bidang lain ditolak'
                    && $konteks['pengguna_id'] === $anggota->id_pengguna
                    && (int) $konteks['id_program_kerja'] === $programLain;
            })
            ->once();
    }
}