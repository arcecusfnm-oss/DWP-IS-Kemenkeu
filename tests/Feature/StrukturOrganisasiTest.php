<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B-018 T-003: smoke test endpoint struktur organisasi. Self-contained.
 * Test lengkap (T-005) dikerjakan terpisah.
 */
class StrukturOrganisasiTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Bidang::firstOrCreate(['nama_bidang' => 'Sekretariat']);
        Bidang::firstOrCreate(['nama_bidang' => 'Bendahara']);
        Bidang::firstOrCreate(['nama_bidang' => 'Bidang Uji A']);

        $this->admin = $this->buatAkun('Admin', 'admin_uji');

        DB::table('jabatan_dwp')->insert([
            ['nama_jabatan' => 'Ketua DWP',      'jenis' => 'ketua',       'urutan' => 1, 'id_anggota' => null, 'created_at' => now(), 'updated_at' => now()],
            ['nama_jabatan' => 'Wakil Ketua I',  'jenis' => 'wakil_ketua', 'urutan' => 1, 'id_anggota' => null, 'created_at' => now(), 'updated_at' => now()],
            ['nama_jabatan' => 'Wakil Ketua II', 'jenis' => 'wakil_ketua', 'urutan' => 2, 'id_anggota' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function buatAkun(string $peran, string $username): Pengguna
    {
        $p = Peran::firstOrCreate(['nama_peran' => $peran]);

        return Pengguna::create([
            'nama_lengkap'           => ucfirst(str_replace('_', ' ', $username)),
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => 'Rahasia#12345',
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $p->id_peran,
            'id_bidang'              => null,
        ]);
    }

    public function test_pengguna_login_dapat_melihat_struktur(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('api.struktur-organisasi'))
            ->assertOk()
            ->assertJsonPath('data.ketua_dwp.jabatan', 'Ketua DWP')
            ->assertJsonCount(2, 'data.wakil_ketua_dwp')
            ->assertJsonPath('data.sekretariat.nama', 'Sekretariat')
            ->assertJsonPath('data.bendahara.nama', 'Bendahara');
    }

    public function test_jabatan_kosong_tampil_aman_dengan_nama_null(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('api.struktur-organisasi'))
            ->assertOk()
            ->assertJsonPath('data.ketua_dwp.nama', null)
            ->assertJsonPath('data.wakil_ketua_dwp.0.nama', null);
    }

    public function test_bidang_baru_muncul_otomatis_dan_tidak_dobel_dengan_sekretariat_bendahara(): void
    {
        Bidang::create(['nama_bidang' => 'Bidang Baru']);

        $respons = $this->actingAs($this->admin)
            ->getJson(route('api.struktur-organisasi'))
            ->assertOk();

        $nama = collect($respons->json('data.bidang'))->pluck('nama')->all();

        $this->assertContains('Bidang Baru', $nama);
        $this->assertContains('Bidang Uji A', $nama);
        $this->assertNotContains('Sekretariat', $nama);
        $this->assertNotContains('Bendahara', $nama);
    }

    public function test_respons_tanpa_data_pribadi(): void
    {
        $json = json_encode($this->actingAs($this->admin)
            ->getJson(route('api.struktur-organisasi'))
            ->assertOk()
            ->json());

        foreach (['nomor_telepon', 'alamat', 'email', 'kata_sandi', 'tanggal_lahir', 'foto'] as $kunci) {
            $this->assertStringNotContainsString($kunci, $json);
        }
    }

    public function test_tanpa_sesi_ditolak(): void
    {
        $this->getJson(route('api.struktur-organisasi'))->assertUnauthorized();
    }

    /**
     * B-018 T-004: feature test endpoint struktur organisasi. Self-contained.
     */
    public function test_semua_peran_dapat_melihat_struktur(): void
    {
        foreach (config('dwp.peran') as $i => $namaPeran) {
            $akun = $this->buatAkun($namaPeran, 'peran_uji_' . $i);

            $this->actingAs($akun)
                ->getJson(route('api.struktur-organisasi'))
                ->assertOk()
                ->assertJsonPath('data.ketua_dwp.jabatan', 'Ketua DWP');
        }
    }

    private function buatPejabat(string $nama, string $status = 'aktif', bool $dihapus = false): int
    {
        return DB::table('anggota')->insertGetId([
            'nama_lengkap'  => $nama,
            'nomor_telepon' => '081234567890',
            'id_bidang'     => null,
            'status'        => $status,
            'dibuat_oleh'   => $this->admin->id_pengguna,
            'dihapus_pada'  => $dihapus ? now() : null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], 'id_anggota');
    }

    public function test_nama_pejabat_tampil_saat_jabatan_terisi(): void
    {
        $ketua = $this->buatPejabat('Bu Ketua');
        $wakil = $this->buatPejabat('Bu Wakil Satu');

        DB::table('jabatan_dwp')->where('nama_jabatan', 'Ketua DWP')->update(['id_anggota' => $ketua]);
        DB::table('jabatan_dwp')->where('nama_jabatan', 'Wakil Ketua I')->update(['id_anggota' => $wakil]);

        $this->actingAs($this->admin)
            ->getJson(route('api.struktur-organisasi'))
            ->assertOk()
            ->assertJsonPath('data.ketua_dwp.nama', 'Bu Ketua')
            ->assertJsonPath('data.wakil_ketua_dwp.0.nama', 'Bu Wakil Satu')
            ->assertJsonPath('data.wakil_ketua_dwp.1.nama', null); // Wakil Ketua II masih kosong
    }

    public function test_pejabat_nonaktif_atau_terhapus_tampil_sebagai_kosong(): void
    {
        $nonaktif = $this->buatPejabat('Bu Nonaktif', 'nonaktif');
        $terhapus = $this->buatPejabat('Bu Terhapus', 'aktif', true);

        DB::table('jabatan_dwp')->where('nama_jabatan', 'Ketua DWP')->update(['id_anggota' => $nonaktif]);
        DB::table('jabatan_dwp')->where('nama_jabatan', 'Wakil Ketua I')->update(['id_anggota' => $terhapus]);

        $respons = $this->actingAs($this->admin)
            ->getJson(route('api.struktur-organisasi'))
            ->assertOk()
            ->assertJsonPath('data.ketua_dwp.nama', null)
            ->assertJsonPath('data.wakil_ketua_dwp.0.nama', null);

        $this->assertStringNotContainsString('Bu Nonaktif', json_encode($respons->json()));
        $this->assertStringNotContainsString('Bu Terhapus', json_encode($respons->json()));
    }
}