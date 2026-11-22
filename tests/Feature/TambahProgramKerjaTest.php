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
 * Peran yang boleh: Pimpinan Bidang dan Anggota Bidang (user story US-115, US-124).
 * Skenario: berhasil, kolom kosong, tanggal terbalik, peran lain, bidang lain.
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

    private function buatAnggota(int $idBidang, string $username = 'anggota_uji83'): Pengguna
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
            'status'          => 'Diajukan',
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
            'sasaran_peserta' => 'Anggota DWP',
            'metode'          => 'Pelatihan luring',
            'tanggal_mulai'   => '2026-12-01',
            'tanggal_selesai' => '2026-12-10',
            'tempat'          => 'Aula Kemenkeu',
            'anggaran'        => 1000000,
        ]);

        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('program_kerja', [
            'nama_program' => 'Program Minimal',
            'keterangan'   => null,
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

    public function test_total_anggaran_bidang_menjumlahkan_program_tahun_berjalan(): void
    {
        $tahun      = now()->year;
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');
        $pimpinan   = $this->buatPimpinan($bidangSaya);

        $isi = fn (int $idBidang, string $mulai, string $selesai, int $anggaran) =>
            DB::table('program_kerja')->insert([
                'id_bidang'       => $idBidang,
                'nama_program'    => 'Program ' . $anggaran,
                'tujuan'          => 'Uji total anggaran.',
                'sasaran_peserta' => 'Anggota DWP',
                'metode'          => 'Luring',
                'tanggal_mulai'   => $mulai,
                'tanggal_selesai' => $selesai,
                'tempat'          => 'Aula',
                'anggaran'        => $anggaran,
                'dibuat_oleh'     => $pimpinan->id_pengguna,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

        // Dijumlahkan: dua program bidang saya, tahun ini.
        $isi($bidangSaya, "$tahun-03-01", "$tahun-03-10", 1000000);
        $isi($bidangSaya, "$tahun-05-01", "$tahun-05-10", 2500000);
        // Tidak ikut: bidang saya tahun lalu, dan bidang lain tahun ini.
        $isi($bidangSaya, ($tahun - 1) . '-03-01', ($tahun - 1) . '-03-10', 7000000);
        $isi($bidangLain, "$tahun-03-01", "$tahun-03-10", 9000000);

        $res = $this->actingAs($pimpinan)->getJson(route('program-kerja.bidang-saya'));

        $res->assertOk();
        $this->assertEquals(3500000, $res->json('totalAnggaran'));
        $this->assertSame($tahun, $res->json('tahunAnggaran'));
    }

    // ---------- 2. Kolom kosong ----------

    public static function kolomWajib(): array
    {
        return [
            'nama program'    => ['nama_program', 'Nama program wajib diisi'],
            'tujuan'          => ['tujuan', 'Tujuan wajib diisi'],
            'tanggal mulai'   => ['tanggal_mulai', 'Tanggal mulai wajib diisi'],
            'tanggal selesai' => ['tanggal_selesai', 'Tanggal selesai wajib diisi'],
            'sasaran peserta' => ['sasaran_peserta', 'Sasaran peserta wajib diisi'],
            'metode'          => ['metode', 'Metode wajib diisi'],
            'tempat'          => ['tempat', 'Tempat wajib diisi'],
            'anggaran'        => ['anggaran', 'Anggaran wajib diisi'],
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

        $res->assertSessionHasErrors([
            'nama_program', 'tujuan', 'sasaran_peserta', 'metode',
            'tanggal_mulai', 'tanggal_selesai', 'tempat', 'anggaran',
        ]);
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

    // ---------- 3. Tanggal terbalik & anggaran tidak valid ----------

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

    public function test_anggaran_negatif_ditolak(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->tambah($pimpinan, $this->dataValid(['anggaran' => -5000]));

        $res->assertSessionHasErrors(['anggaran' => 'Anggaran tidak boleh negatif']);
        $this->assertSame(0, $this->jumlahProgram());
    }

    public function test_anggaran_bukan_angka_ditolak(): void
    {
        $bidang   = $this->buatBidang('Sosial Budaya');
        $pimpinan = $this->buatPimpinan($bidang);

        $res = $this->tambah($pimpinan, $this->dataValid(['anggaran' => 'bukan-angka']));

        $res->assertSessionHasErrors(['anggaran' => 'Anggaran harus berupa angka']);
        $this->assertSame(0, $this->jumlahProgram());
    }

    // ---------- 4. Peran lain ----------

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

    // ---------- 6. Anggota Bidang ----------

    public function test_anggota_bidang_berhasil_menambah_program_kerja(): void
    {
        $bidang  = $this->buatBidang('Sosial Budaya');
        $anggota = $this->buatAnggota($bidang);

        $this->tambah($anggota, $this->dataValid())
            ->assertSessionHas('sukses', 'Program kerja berhasil ditambahkan.');

        $this->assertDatabaseHas('program_kerja', [
            'id_bidang'   => $bidang,
            'dibuat_oleh' => $anggota->id_pengguna,
        ]);
    }

    public function test_form_tambah_tampil_untuk_pimpinan_dan_anggota(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');

        foreach ([$this->buatPimpinan($bidang), $this->buatAnggota($bidang)] as $akun) {
            $this->actingAs($akun)
                ->get(route('program-kerja.create'))
                ->assertOk()
                ->assertViewIs('program-kerja.tambah')
                ->assertSee('Tambah Program Kerja');
        }
    }

    public function test_form_tambah_ditolak_untuk_peran_lain(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');

        $this->actingAs($this->buatPengguna('Admin', $bidang, 'admin_form83'))
            ->get(route('program-kerja.create'))
            ->assertForbidden();
    }

    public function test_form_tambah_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $this->get(route('program-kerja.create'))
            ->assertRedirect(route('login'));
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
        $this->assertSame(1, $this->jumlahProgram());

        $this->actingAs($pimpinan)
            ->get(route('program-kerja.bidang-saya'))
            ->assertOk()
            ->assertDontSee($teks, false)
            ->assertSee($teks);
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

                return $pesan === 'Program kerja ditambahkan'
                    && $konteks['peran'] === 'Pimpinan Bidang'
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