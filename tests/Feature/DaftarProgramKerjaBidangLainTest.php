<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T-90 (B-042): Pengujian daftar program kerja bidang lain.
 * Fokus: daftar hanya baca, bidang sendiri dikecualikan,
 * pencarian, data kosong, dan akses tanpa sesi.
 */
class DaftarProgramKerjaBidangLainTest extends TestCase
{
    use DatabaseTransactions;

    private function buatBidang(string $nama): int
    {
        return DB::table('bidang')->insertGetId([
            'nama_bidang' => $nama . ' ' . Str::random(6),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function buatPeran(string $nama): int
    {
        return DB::table('peran')->where('nama_peran', $nama)->value('id_peran')
            ?? DB::table('peran')->insertGetId([
                'nama_peran' => $nama,
            ]);
    }

    private function buatPengguna(string $peran, ?int $idBidang = null): Pengguna
    {
        $username = 'uji90_' . Str::lower(Str::random(8));

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

    private function buatProgram(int $idBidang, int $pembuat, string $nama): void
    {
        DB::table('program_kerja')->insert([
            'id_bidang'        => $idBidang,
            'nama_program'     => $nama,
            'tujuan'            => 'Tujuan pengujian T-90',
            'sasaran_peserta' => 'Anggota DWP',
            'metode'          => 'Pelatihan luring',
            'tanggal_mulai'    => '2099-01-01',
            'tanggal_selesai'  => '2099-01-10',
            'tempat'          => 'Aula Kemenkeu',
            'anggaran'        => 1000000,
            'dibuat_oleh'      => $pembuat,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function test_daftar_hanya_menampilkan_program_bidang_lain(): void
    {
        $bidangSaya = $this->buatBidang('Bidang Saya');
        $bidangLain = $this->buatBidang('Bidang Lain');
        $pengguna   = $this->buatPengguna('Pimpinan Bidang', $bidangSaya);
        $tag        = Str::random(8);

        $this->buatProgram($bidangSaya, $pengguna->id_pengguna, "Program Sendiri $tag");
        $this->buatProgram($bidangLain, $pengguna->id_pengguna, "Program Lain $tag");

        $this->actingAs($pengguna)
            ->get(route('program-kerja.bidang-lain'))
            ->assertOk()
            ->assertViewIs('program-kerja.bidang-lain')
            ->assertSee("Program Lain $tag")
            ->assertDontSee("Program Sendiri $tag");
    }

    public function test_daftar_bidang_lain_tidak_menyediakan_aksi_ubah_atau_hapus(): void
    {
        $bidangSaya = $this->buatBidang('Bidang Saya');
        $bidangLain = $this->buatBidang('Bidang Lain');
        $pengguna   = $this->buatPengguna('Anggota Bidang', $bidangSaya);
        $tag        = Str::random(8);

        $this->buatProgram($bidangLain, $pengguna->id_pengguna, "Program Read Only $tag");

        $response = $this->actingAs($pengguna)
            ->get(route('program-kerja.bidang-lain'));

        $response->assertOk()
            ->assertSee('Hanya baca')
            ->assertSee('Program tidak dapat diubah dari sini.')
            ->assertSee("Program Read Only $tag");

        $response->assertSee('method="GET"', false)
            ->assertDontSee('/edit', false)
            ->assertDontSee('program-kerja.edit', false)
            ->assertDontSee('program-kerja.destroy', false);
    }

    public function test_json_hanya_mengembalikan_program_bidang_lain(): void
    {
        $bidangSaya = $this->buatBidang('Bidang Saya');
        $bidangLain = $this->buatBidang('Bidang Lain');
        $pengguna   = $this->buatPengguna('Pimpinan Bidang', $bidangSaya);
        $tag        = Str::random(8);

        $this->buatProgram($bidangSaya, $pengguna->id_pengguna, "JSON Sendiri $tag");
        $this->buatProgram($bidangLain, $pengguna->id_pengguna, "JSON Lain $tag");

        $this->actingAs($pengguna)
            ->getJson(route('program-kerja.bidang-lain'))
            ->assertOk()
            ->assertJsonFragment(['nama_program' => "JSON Lain $tag"])
            ->assertJsonMissing(['nama_program' => "JSON Sendiri $tag"])
            ->assertJsonPath('pesan', null);
    }

    public function test_filter_pencarian_hanya_menampilkan_program_yang_cocok(): void
    {
        $bidangSaya = $this->buatBidang('Bidang Saya');
        $bidangA   = $this->buatBidang('Bidang A');
        $bidangB   = $this->buatBidang('Bidang B');
        $pengguna   = $this->buatPengguna('Pimpinan Bidang', $bidangSaya);
        $tag        = Str::random(8);

        $this->buatProgram($bidangA, $pengguna->id_pengguna, "Cari Target $tag");
        $this->buatProgram($bidangB, $pengguna->id_pengguna, "Program Berbeda $tag");

        $this->actingAs($pengguna)
            ->get(route('program-kerja.bidang-lain', ['cari' => "Target $tag"]))
            ->assertOk()
            ->assertSee("Cari Target $tag")
            ->assertDontSee("Program Berbeda $tag");
    }

    public function test_pencarian_tidak_ditemukan_menampilkan_empty_state(): void
    {
        $bidangSaya = $this->buatBidang('Bidang Saya');
        $pengguna   = $this->buatPengguna('Pimpinan Bidang', $bidangSaya);
        $kataUnik   = 'TidakAda_' . Str::random(12);

        $this->actingAs($pengguna)
            ->get(route('program-kerja.bidang-lain', ['cari' => $kataUnik]))
            ->assertOk()
            ->assertSee('Program kerja tidak ditemukan.');
    }

    public function test_tanpa_sesi_ditolak(): void
    {
        $this->getJson(route('program-kerja.bidang-lain'))
            ->assertUnauthorized();

        $this->get(route('program-kerja.bidang-lain'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
