<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-83 (B-041): pengujian endpoint tambah program kerja (T-81).
 *
 * Skenario: berhasil, kolom kosong, tanggal terbalik, peran lain, bidang lain.
 * Ditambah: keamanan input (AC 6) dan log (AC 7).
 */
class TambahProgramKerjaTest extends TestCase
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

    private function buatPimpinan(int $idBidang, string $username = 'pimpinan_uji83'): Pengguna
    {
        return $this->buatPengguna('Pimpinan Bidang', $idBidang, $username);
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

    private function tambah(Pengguna $pelaku, array $data)
    {
        return $this->actingAs($pelaku)->post(route('program-kerja.store'), $data);
    }

    private function jumlahProgram(): int
    {
        return DB::table('program_kerja')->count();
    }

    // ---------- 1. Berhasil ----------

    public function test_pimpinan_bidang_berhasil_menambah_program_kerja(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->tambah($pimpinan, $this->dataValid());

        $res->assertRedirect();
        $res->assertSessionHas('sukses', 'Program kerja berhasil ditambahkan.');
        $res->assertSessionHasNoErrors();

        $this->assertSame(1, $this->jumlahProgram());
        $this->assertDatabaseHas('program_kerja', [
            'id_bidang'       => $bidang,
            'nama_program'    => 'Pelatihan Kewirausahaan',
            'tanggal_mulai'   => '2026-12-01',
            'tanggal_selesai' => '2026-12-10',
            'dibuat_oleh'     => $pimpinan->id_pengguna,
        ]);
    }

    public function test_permintaan_json_mengembalikan_201_dan_id_program(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->actingAs($pimpinan)->postJson(route('program-kerja.store'), $this->dataValid());

        $res->assertStatus(201)
            ->assertJson(['pesan' => 'Program kerja berhasil ditambahkan.'])
            ->assertJsonStructure(['pesan', 'id_program_kerja']);

        $this->assertDatabaseHas('program_kerja', [
            'id_program_kerja' => $res->json('id_program_kerja'),
            'id_bidang'        => $bidang,
        ]);
    }

    public function test_kolom_opsional_boleh_kosong(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->tambah($pimpinan, [
            'nama_program'    => 'Program Minimal',
            'tujuan'          => 'Uji kolom opsional.',
            'tanggal_mulai'   => '2026-12-01',
            'tanggal_selesai' => '2026-12-10',
        ]);

        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('program_kerja', [
            'nama_program'    => 'Program Minimal',
            'sasaran_peserta' => null,
            'metode'          => null,
            'tempat'          => null,
            'anggaran'        => null,
            'keterangan'      => null,
        ]);
    }

    public function test_tanggal_selesai_sama_dengan_tanggal_mulai_diterima(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $this->tambah($pimpinan, $this->dataValid([
            'tanggal_mulai'   => '2026-12-05',
            'tanggal_selesai' => '2026-12-05',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->jumlahProgram());
    }

    // ---------- 2. Kolom kosong ----------

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

        $res = $this->tambah($pimpinan, $this->dataValid([$kolom => '']));

        $res->assertSessionHasErrors([$kolom => $pesan]);
        $this->assertSame(0, $this->jumlahProgram());
    }

    public function test_semua_kolom_wajib_kosong_ditolak_dan_tidak_ada_data_tersimpan(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->tambah($pimpinan, []);

        $res->assertSessionHasErrors(['nama_program', 'tujuan', 'tanggal_mulai', 'tanggal_selesai']);
        $this->assertSame(0, $this->jumlahProgram());
    }

    public function test_permintaan_json_dengan_kolom_kosong_mengembalikan_422(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $this->actingAs($pimpinan)
            ->postJson(route('program-kerja.store'), $this->dataValid(['nama_program' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama_program');
    }

    // ---------- 3. Tanggal terbalik ----------

    public function test_tanggal_selesai_sebelum_tanggal_mulai_ditolak(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->tambah($pimpinan, $this->dataValid([
            'tanggal_mulai'   => '2026-12-10',
            'tanggal_selesai' => '2026-12-01',
        ]));

        $res->assertSessionHasErrors([
            'tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai',
        ]);
        $this->assertSame(0, $this->jumlahProgram());
    }

    public function test_format_tanggal_tidak_valid_ditolak(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->tambah($pimpinan, $this->dataValid(['tanggal_mulai' => '10-12-2026']));

        $res->assertSessionHasErrors(['tanggal_mulai' => 'Format tanggal mulai tidak valid']);
        $this->assertSame(0, $this->jumlahProgram());
    }

    // ---------- 4. Peran lain ----------

    public static function peranSelainPimpinan(): array
    {
        return [
            'Admin'            => ['Admin'],
            'Anggota Bidang'   => ['Anggota Bidang'],
            'Penasihat'        => ['Penasihat'],
            'Penanggung Jawab' => ['Penanggung Jawab'],
            'Bendahara'        => ['Bendahara'],
        ];
    }

    #[DataProvider('peranSelainPimpinan')]
    public function test_peran_selain_pimpinan_bidang_ditolak(string $peran): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');
        $akun   = $this->buatPengguna($peran, $bidang, 'akun_peran_lain');

        $this->tambah($akun, $this->dataValid())->assertForbidden();

        $this->assertSame(0, $this->jumlahProgram());
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $this->post(route('program-kerja.store'), $this->dataValid())
            ->assertRedirect(route('login'));

        $this->assertSame(0, $this->jumlahProgram());
    }

    public function test_pimpinan_tanpa_bidang_ditolak(): void
    {
        $pimpinan = $this->buatPengguna('Pimpinan Bidang', null, 'pimpinan_tanpa_bidang');

        $this->actingAs($pimpinan)
            ->postJson(route('program-kerja.store'), $this->dataValid())
            ->assertStatus(403)
            ->assertJson(['pesan' => 'Akun Anda belum terhubung dengan bidang.']);

        $this->assertSame(0, $this->jumlahProgram());
    }

    // ---------- 5. Bidang lain ----------

    public function test_id_bidang_dari_request_diabaikan_bidang_selalu_dari_akun(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $pimpinan   = $this->buatPimpinan($bidangSaya);
        $orangLain  = $this->buatPimpinan($bidangLain, 'pimpinan_lain83');

        $res = $this->tambah($pimpinan, $this->dataValid([
            'id_bidang'   => $bidangLain,
            'dibuat_oleh' => $orangLain->id_pengguna,
        ]));

        $res->assertSessionHasNoErrors();
        $this->assertSame(1, $this->jumlahProgram());
        $this->assertDatabaseHas('program_kerja', [
            'nama_program' => 'Pelatihan Kewirausahaan',
            'id_bidang'    => $bidangSaya,
            'dibuat_oleh'  => $pimpinan->id_pengguna,
        ]);
        $this->assertDatabaseMissing('program_kerja', ['id_bidang' => $bidangLain]);
    }

    public function test_program_bidang_sendiri_tidak_mengubah_program_bidang_lain(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $pimpinan   = $this->buatPimpinan($bidangSaya);
        $orangLain  = $this->buatPimpinan($bidangLain, 'pimpinan_lain83');

        $this->tambah($orangLain, $this->dataValid(['nama_program' => 'Program Ekonomi']));
        $this->tambah($pimpinan, $this->dataValid(['nama_program' => 'Program Sosbud']));

        $this->assertSame(2, $this->jumlahProgram());
        $this->assertSame(1, DB::table('program_kerja')->where('id_bidang', $bidangLain)->count());
        $this->assertSame(1, DB::table('program_kerja')->where('id_bidang', $bidangSaya)->count());
    }

    // ---------- AC 6: keamanan input ----------

    public static function inputBerbahaya(): array
    {
        return [
            'script XSS'    => ['<script>alert("x")</script>'],
            'SQL injection' => ["Robert'); DROP TABLE program_kerja;--"],
        ];
    }

    #[DataProvider('inputBerbahaya')]
    public function test_input_berbahaya_disimpan_sebagai_teks_biasa_tanpa_error_server(string $teks): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->tambah($pimpinan, $this->dataValid(['nama_program' => $teks]));

        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('program_kerja', ['nama_program' => $teks]);
        $this->assertSame(1, $this->jumlahProgram()); // tabel masih ada dan terbaca
    }

    // ---------- AC 7: log ----------

    public function test_penambahan_program_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $this->tambah($pimpinan, $this->dataValid());

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($pimpinan, $bidang) {
                $json = json_encode($konteks);

                return $pesan === 'Pimpinan Bidang menambah program kerja'
                    && $konteks['pembuat_id'] === $pimpinan->id_pengguna
                    && $konteks['pembuat_username'] === $pimpinan->nama_pengguna
                    && (int) $konteks['id_bidang'] === $bidang
                    && $konteks['nama_program'] === 'Pelatihan Kewirausahaan'
                    && ! empty($konteks['id_program_kerja'])
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, 'Password123!')
                    && ! str_contains($json, (string) $pimpinan->kata_sandi_hash);
            })
            ->once();
    }
}