<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * B-128 T-075: smoke test endpoint catat dan daftar SK. Self-contained.
 * Test lengkap (T-077) dikerjakan terpisah.
 */
class SuratKeputusanSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->buatAkun('Admin', 'admin_uji');
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

    private function payload(array $override = []): array
    {
        return array_merge([
            'nomor_sk'         => 'SK-001/2026',
            'tanggal'          => '2026-11-20',
            'perihal'          => 'Penetapan pengurus',
            'status_penetapan' => 'sudah',
        ], $override);
    }

    private function catat(array $override = [])
    {
        return $this->postJson(route('api.surat-keputusan.store'), $this->payload($override));
    }

    public function test_admin_dapat_mencatat_sk(): void
    {
        $this->actingAs($this->admin);

        $this->catat()->assertCreated();

        $this->assertDatabaseHas('surat_keputusan', [
            'nomor_sk'         => 'SK-001/2026',
            'perihal'          => 'Penetapan pengurus',
            'status_penetapan' => 'sudah',
            'dibuat_oleh'      => $this->admin->id_pengguna,
        ]);
    }

    public function test_nomor_sk_duplikat_ditolak(): void
    {
        $this->actingAs($this->admin);

        $this->catat()->assertCreated();
        $this->catat(['perihal' => 'Lain'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nomor_sk');

        $this->assertSame(1, DB::table('surat_keputusan')->count());
    }

    public function test_penetapan_di_luar_pilihan_ditolak(): void
    {
        $this->actingAs($this->admin);

        $this->catat(['status_penetapan' => 'mungkin'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status_penetapan');

        $this->assertSame(0, DB::table('surat_keputusan')->count());
    }

    public function test_kolom_wajib_kosong_dan_tanggal_tidak_sah_ditolak(): void
    {
        $this->actingAs($this->admin);

        $this->catat(['nomor_sk' => '', 'perihal' => '', 'tanggal' => '2026-02-30'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_sk', 'perihal', 'tanggal']);

        $this->assertSame(0, DB::table('surat_keputusan')->count());
    }

    public function test_sk_tampil_di_daftar(): void
    {
        $this->actingAs($this->admin);

        $this->catat()->assertCreated();

        $this->getJson(route('api.surat-keputusan.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nomor_sk', 'SK-001/2026')
            ->assertJsonPath('data.0.status_penetapan', 'sudah');
    }

    public function test_non_admin_ditolak(): void
    {
        $this->actingAs($this->buatAkun('Anggota Bidang', 'anggota_uji'));

        $this->catat()->assertForbidden();
        $this->getJson(route('api.surat-keputusan.index'))->assertForbidden();

        $this->assertSame(0, DB::table('surat_keputusan')->count());
    }

    public function test_tanpa_sesi_ditolak(): void
    {
        $this->catat()->assertUnauthorized();
        $this->getJson(route('api.surat-keputusan.index'))->assertUnauthorized();
    }
}