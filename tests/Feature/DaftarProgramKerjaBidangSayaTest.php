<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DaftarProgramKerjaBidangSayaTest extends TestCase
{
    use RefreshDatabase;

    private function buatBidang(string $nama): int
    {
        return DB::table('bidang')->insertGetId([
            'nama_bidang' => $nama,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function buatPeran(string $nama): int
    {
        return DB::table('peran')
            ->where('nama_peran', $nama)
            ->value('id_peran')
            ?? DB::table('peran')->insertGetId([
                'nama_peran' => $nama,
            ]);
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

    private function buatProgram(int $idBidang, int $pembuat, string $nama): void
    {
        DB::table('program_kerja')->insert([
            'id_bidang'       => $idBidang,
            'nama_program'    => $nama,
            'tujuan'          => 'Tujuan pengujian',
            'tanggal_mulai'   => '2026-12-01',
            'tanggal_selesai' => '2026-12-10',
            'dibuat_oleh'     => $pembuat,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function test_pimpinan_hanya_melihat_program_kerja_bidangnya(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');

        $pimpinan = $this->buatPengguna(
            'Pimpinan Bidang',
            $bidangSaya,
            'pimpinan_uji98'
        );

        $pimpinanLain = $this->buatPengguna(
            'Pimpinan Bidang',
            $bidangLain,
            'pimpinan_lain98'
        );

        $this->buatProgram($bidangSaya, $pimpinan->id_pengguna, 'Program Sosial Budaya');
        $this->buatProgram($bidangLain, $pimpinanLain->id_pengguna, 'Program Ekonomi');

        $this->actingAs($pimpinan)
            ->get(route('program-kerja.bidang-saya'))
            ->assertOk()
            ->assertViewIs('program-kerja.bidang-saya')
            ->assertSee('Program Sosial Budaya')
            ->assertSee('Sosial Budaya')
            ->assertDontSee('Program Ekonomi');
    }

    public function test_bidang_saya_tanpa_program_kerja_menampilkan_pesan_kosong(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');

        $pimpinan = $this->buatPengguna(
            'Pimpinan Bidang',
            $bidang,
            'pimpinan_kosong100'
        );

        $this->actingAs($pimpinan)
            ->get(route('program-kerja.bidang-saya'))
            ->assertOk()
            ->assertViewIs('program-kerja.bidang-saya')
            ->assertViewHas('pesan', 'Belum ada program kerja untuk bidang ini.')
            ->assertSee('Belum ada program kerja untuk bidang ini.');
    }

    public function test_anggota_bidang_bisa_melihat_program_kerja_bidangnya(): void
    {
        $bidang = $this->buatBidang('Sosial Budaya');

        $anggota = $this->buatPengguna(
            'Anggota Bidang',
            $bidang,
            'anggota_uji98'
        );

        $this->buatProgram($bidang, $anggota->id_pengguna, 'Program Milik Bidang');

        $this->actingAs($anggota)
            ->get(route('program-kerja.bidang-saya'))
            ->assertOk()
            ->assertSee('Program Milik Bidang');
    }

    public function test_program_kerja_bidang_lain_ditolak(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');

        $pimpinan = $this->buatPengguna(
            'Pimpinan Bidang',
            $bidangSaya,
            'pimpinan_uji99'
        );

        $this->buatProgram($bidangSaya, $pimpinan->id_pengguna, 'Program Sendiri');

        $this->actingAs($pimpinan)
            ->get(route('program-kerja.bidang-saya', ['bidang' => $bidangLain]))
            ->assertForbidden();
    }

    public function test_akun_tanpa_bidang_ditolak(): void
    {
        $pimpinan = $this->buatPengguna(
            'Pimpinan Bidang',
            null,
            'pimpinan_nobidang98'
        );

        $this->actingAs($pimpinan)
            ->get(route('program-kerja.bidang-saya'))
            ->assertForbidden();
    }

    public function test_admin_tidak_bisa_mengakses_endpoint_bidang_saya(): void
    {
        $admin = $this->buatPengguna(
            'Admin',
            null,
            'admin_uji98'
        );

        $this->actingAs($admin)
            ->get(route('program-kerja.bidang-saya'))
            ->assertForbidden();
    }

    public function test_pengguna_tanpa_login_diarahkan_ke_login(): void
    {
        $this->get(route('program-kerja.bidang-saya'))
            ->assertRedirect(route('login'));
    }

    public function test_endpoint_json_hanya_mengembalikan_program_bidang_sendiri(): void
    {
        $bidangSaya = $this->buatBidang('Sosial Budaya');
        $bidangLain = $this->buatBidang('Ekonomi');

        $pimpinan = $this->buatPengguna(
            'Pimpinan Bidang',
            $bidangSaya,
            'pimpinan_json98'
        );

        $pimpinanLain = $this->buatPengguna(
            'Pimpinan Bidang',
            $bidangLain,
            'pimpinan_jsonlain98'
        );

        $this->buatProgram($bidangSaya, $pimpinan->id_pengguna, 'Program JSON Sendiri');
        $this->buatProgram($bidangLain, $pimpinanLain->id_pengguna, 'Program JSON Lain');

        $this->actingAs($pimpinan)
            ->getJson(route('program-kerja.bidang-saya'))
            ->assertOk()
            ->assertJson([
                'bidang' => 'Sosial Budaya',
                'total'  => 1,
            ])
            ->assertJsonFragment([
                'nama_program' => 'Program JSON Sendiri',
            ])
            ->assertJsonMissing([
                'nama_program' => 'Program JSON Lain',
            ]);
    }
}
