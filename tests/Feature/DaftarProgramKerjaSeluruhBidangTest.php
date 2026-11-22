<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T-95 (B-044): unit test daftar program kerja SELURUH bidang.
 * Route: GET admin/program-kerja (admin.program-kerja.index), hanya Admin dan Penasihat.
 *
 * Memakai DatabaseTransactions (bukan RefreshDatabase) supaya data asli tidak terhapus.
 * Semua data uji memakai nama unik acak dan tanggal 2099 agar tetap di halaman 1,
 * sehingga test tidak bergantung pada isi tabel sebelumnya.
 */
class DaftarProgramKerjaSeluruhBidangTest extends TestCase
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
            ?? DB::table('peran')->insertGetId(['nama_peran' => $nama]);
    }

    private function buatPengguna(string $peran, ?int $idBidang = null): Pengguna
    {
        $username = 'uji95_' . Str::lower(Str::random(8));

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
            'sasaran_peserta' => 'Anggota DWP',
            'metode'          => 'Pelatihan luring',
            'tanggal_mulai'   => '2099-01-01',
            'tanggal_selesai' => '2099-01-10',
            'tempat'          => 'Aula Kemenkeu',
            'anggaran'        => 1000000,
            'dibuat_oleh'     => $pembuat,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // ---------------------------------------------------------------
    // Akses per peran
    // ---------------------------------------------------------------

    public function test_admin_dan_penasihat_bisa_membuka_halaman(): void
    {
        foreach ([
            'Admin'     => 'admin.program-kerja.index',
            'Penasihat' => 'penasihat.program-kerja.index',
        ] as $peran => $namaRoute) {
            $this->actingAs($this->buatPengguna($peran))
                ->get(route($namaRoute))
                ->assertOk()
                ->assertViewIs('admin.program-kerja.index');
        }
    }

    public function test_peran_tidak_berhak_mendapat_403(): void
    {
        $bidang = $this->buatBidang('Bidang Akses');

        foreach (['Pimpinan Bidang', 'Anggota Bidang', 'Bendahara', 'Penanggung Jawab'] as $peran) {
            $this->actingAs($this->buatPengguna($peran, $bidang))
                ->get(route('admin.program-kerja.index'))
                ->assertForbidden();
        }
    }

    public function test_peran_tidak_berhak_tidak_bisa_lewat_json(): void
    {
        $bidang = $this->buatBidang('Bidang Json');

        $this->actingAs($this->buatPengguna('Anggota Bidang', $bidang))
            ->getJson(route('admin.program-kerja.index'))
            ->assertForbidden();
    }

    public function test_tanpa_sesi_ditolak(): void
    {
        $this->getJson(route('admin.program-kerja.index'))->assertUnauthorized();

        $this->get(route('admin.program-kerja.index'))->assertRedirect();
        $this->assertGuest();
    }

    // ---------------------------------------------------------------
    // Daftar dan filter
    // ---------------------------------------------------------------

    public function test_tanpa_filter_menampilkan_program_dari_semua_bidang(): void
    {
        $admin   = $this->buatPengguna('Admin');
        $bidangA = $this->buatBidang('Bidang A');
        $bidangB = $this->buatBidang('Bidang B');
        $tag     = Str::random(6);

        $this->buatProgram($bidangA, $admin->id_pengguna, "Program A $tag");
        $this->buatProgram($bidangB, $admin->id_pengguna, "Program B $tag");

        $this->actingAs($admin)
            ->get(route('admin.program-kerja.index'))
            ->assertOk()
            ->assertSee("Program A $tag")
            ->assertSee("Program B $tag");
    }

    public function test_filter_bidang_hanya_menampilkan_program_bidang_itu(): void
    {
        $admin   = $this->buatPengguna('Admin');
        $bidangA = $this->buatBidang('Bidang A');
        $bidangB = $this->buatBidang('Bidang B');
        $tag     = Str::random(6);

        $this->buatProgram($bidangA, $admin->id_pengguna, "Program A $tag");
        $this->buatProgram($bidangB, $admin->id_pengguna, "Program B $tag");

        $this->actingAs($admin)
            ->get(route('admin.program-kerja.index', ['bidang' => $bidangA]))
            ->assertOk()
            ->assertSee("Program A $tag")
            ->assertDontSee("Program B $tag");

        $this->actingAs($admin)
            ->get(route('admin.program-kerja.index', ['bidang' => $bidangB]))
            ->assertOk()
            ->assertSee("Program B $tag")
            ->assertDontSee("Program A $tag");
    }

    public function test_filter_bidang_lewat_json_mengembalikan_data_yang_benar(): void
    {
        $penasihat = $this->buatPengguna('Penasihat');
        $bidangA   = $this->buatBidang('Bidang A');
        $bidangB   = $this->buatBidang('Bidang B');

        $this->buatProgram($bidangA, $penasihat->id_pengguna, 'Program JSON A');
        $this->buatProgram($bidangA, $penasihat->id_pengguna, 'Program JSON A2');
        $this->buatProgram($bidangB, $penasihat->id_pengguna, 'Program JSON B');

        $this->actingAs($penasihat)
            ->getJson(route('penasihat.program-kerja.index', ['bidang' => $bidangA]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('bidang', $bidangA)
            ->assertJsonPath('pesan', null);
    }

    public function test_penasihat_dan_admin_tidak_bisa_lintas_route(): void
    {
        $this->actingAs($this->buatPengguna('Penasihat'))
            ->get(route('admin.program-kerja.index'))
            ->assertForbidden();

        $this->actingAs($this->buatPengguna('Admin'))
            ->get(route('penasihat.program-kerja.index'))
            ->assertForbidden();
    }

    public function test_filter_bidang_tidak_ada_ditolak(): void
    {
        $admin = $this->buatPengguna('Admin');

        $this->actingAs($admin)
            ->getJson(route('admin.program-kerja.index', ['bidang' => 99999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bidang']);
    }

    public function test_filter_bidang_bukan_angka_ditolak(): void
    {
        $admin = $this->buatPengguna('Admin');

        $this->actingAs($admin)
            ->getJson(route('admin.program-kerja.index', ['bidang' => 'abc']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bidang']);
    }

    // ---------------------------------------------------------------
    // Data kosong
    // ---------------------------------------------------------------

    public function test_bidang_tanpa_program_menampilkan_pesan_kosong(): void
    {
        $admin       = $this->buatPengguna('Admin');
        $bidangKosong = $this->buatBidang('Bidang Kosong');

        $this->actingAs($admin)
            ->get(route('admin.program-kerja.index', ['bidang' => $bidangKosong]))
            ->assertOk()
            ->assertSee('Belum ada program kerja');
    }

    public function test_data_kosong_lewat_json(): void
    {
        $admin        = $this->buatPengguna('Admin');
        $bidangKosong = $this->buatBidang('Bidang Kosong');

        $this->actingAs($admin)
            ->getJson(route('admin.program-kerja.index', ['bidang' => $bidangKosong]))
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('data', [])
            ->assertJsonPath('pesan', 'Belum ada program kerja');
    }

    // ---------------------------------------------------------------
    // Keamanan tampilan
    // ---------------------------------------------------------------

    public function test_nama_program_di_escape_saat_ditampilkan(): void
    {
        $admin  = $this->buatPengguna('Admin');
        $bidang = $this->buatBidang('Bidang XSS');
        $nama   = '<script>alert(1)</script> ' . Str::random(6);

        $this->buatProgram($bidang, $admin->id_pengguna, $nama);

        $this->actingAs($admin)
            ->get(route('admin.program-kerja.index', ['bidang' => $bidang]))
            ->assertOk()
            ->assertDontSee($nama, false)
            ->assertSee(e($nama), false);
    }
}