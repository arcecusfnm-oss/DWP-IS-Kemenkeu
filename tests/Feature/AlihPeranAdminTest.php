<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Database\Seeders\BidangSeeder;
use Database\Seeders\PeranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AlihPeranAdminTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BidangSeeder::class);
        $this->seed(PeranSeeder::class);

        $this->admin = $this->buatAkun('Admin', 'Sekretariat');
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buatAkun(string $peran, string $bidang, string $status = 'aktif'): Pengguna
    {
        static $n = 0;
        $n++;

        return Pengguna::create([
            'nama_lengkap'           => "Akun Uji {$n}",
            'nama_pengguna'          => "uji{$n}",
            'kata_sandi_hash'        => 'kata-sandi-uji-123',
            'status'                 => $status,
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => Peran::where('nama_peran', $peran)->value('id_peran'),
            'id_bidang'              => Bidang::where('nama_bidang', $bidang)->value('id_bidang'),
        ]);
    }

    private function peranDari(Pengguna $akun): string
    {
        return $akun->fresh()->peran->nama_peran;
    }

    private function jumlahAdmin(): int
    {
        return Pengguna::whereHas('peran', fn ($q) => $q->where('nama_peran', 'Admin'))->count();
    }

    private function kirimAlih(int $idTujuan, array $tambahan = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.akun.alih-admin'), array_merge([
            'id_tujuan'   => $idTujuan,
            'peran_turun' => 'Anggota Bidang',
            'bidang'      => ['Pendidikan'],
        ], $tambahan));
    }

    // ---------------------------------------------------------------
    // AC-1: skenario utama
    // ---------------------------------------------------------------

    public function test_alih_peran_berhasil(): void
    {
        $tujuan = $this->buatAkun('Anggota Bidang', 'Ekonomi');

        $this->kirimAlih($tujuan->id_pengguna)
            ->assertRedirect(route('beranda'))
            ->assertSessionHas('sukses');

        $this->assertSame('Admin', $this->peranDari($tujuan));
        $this->assertSame('Anggota Bidang', $this->peranDari($this->admin));
        $this->assertSame(1, $this->jumlahAdmin());
    }

    // ---------------------------------------------------------------
    // AC-2: skenario gagal
    // ---------------------------------------------------------------

    public function test_alih_ke_akun_nonaktif_ditolak(): void
    {
        $tujuan = $this->buatAkun('Anggota Bidang', 'Ekonomi', 'nonaktif');

        $this->kirimAlih($tujuan->id_pengguna)
            ->assertSessionHas('galat', 'Akun tujuan harus berstatus aktif.');

        $this->assertSame('Admin', $this->peranDari($this->admin));
        $this->assertSame('Anggota Bidang', $this->peranDari($tujuan));
        $this->assertSame(1, $this->jumlahAdmin());
    }

    public function test_alih_ke_akun_yang_tidak_ada_ditolak(): void
    {
        $this->kirimAlih(9999)
            ->assertSessionHas('galat', 'Akun tujuan tidak ditemukan.');

        $this->assertSame('Admin', $this->peranDari($this->admin));
        $this->assertSame(1, $this->jumlahAdmin());
    }

    public function test_alih_ke_akun_sendiri_ditolak(): void
    {
        $this->kirimAlih($this->admin->id_pengguna)
            ->assertSessionHas('galat', 'Tidak bisa mengalihkan peran Admin ke akun sendiri.');

        $this->assertSame('Admin', $this->peranDari($this->admin));
        $this->assertSame(1, $this->jumlahAdmin());
    }

    // ---------------------------------------------------------------
    // AC-3: atomik
    // ---------------------------------------------------------------

    public function test_alih_peran_atomik_bila_penyimpanan_gagal(): void
    {
        $tujuan = $this->buatAkun('Anggota Bidang', 'Ekonomi');

        // Gagalkan UPDATE pengguna yang KEDUA (akun asal).
        // Update pertama (akun tujuan -> Admin) sudah sempat jalan,
        // jadi ini harus ikut di-rollback.
        $jumlahUpdate = 0;
        DB::listen(function ($query) use (&$jumlahUpdate) {
            if (preg_match('/^update\s+[`"]?pengguna[`"]?\s/i', $query->sql)) {
                $jumlahUpdate++;
                if ($jumlahUpdate === 2) {
                    throw new \RuntimeException('Simulasi gagal simpan');
                }
            }
        });

        $this->kirimAlih($tujuan->id_pengguna)->assertStatus(500);

        // Pastikan jebakan memang kena (kalau 1, berarti test ini tidak membuktikan apa-apa)
        $this->assertSame(2, $jumlahUpdate);

        $this->assertSame('Admin', $this->peranDari($this->admin));
        $this->assertSame('Anggota Bidang', $this->peranDari($tujuan));
        $this->assertSame(1, $this->jumlahAdmin());
    }

    // ---------------------------------------------------------------
    // AC-4: keamanan
    // ---------------------------------------------------------------

    public function test_non_admin_ditolak(): void
    {
        $anggota = $this->buatAkun('Anggota Bidang', 'Pendidikan');
        $tujuan  = $this->buatAkun('Anggota Bidang', 'Ekonomi');

        $this->actingAs($anggota)
            ->post(route('admin.akun.alih-admin'), [
                'id_tujuan'   => $tujuan->id_pengguna,
                'peran_turun' => 'Anggota Bidang',
                'bidang'      => ['Pendidikan'],
            ])
            ->assertForbidden();

        $this->assertSame('Anggota Bidang', $this->peranDari($tujuan));
        $this->assertSame(1, $this->jumlahAdmin());
    }

    public function test_tanpa_sesi_ditolak(): void
    {
        $tujuan = $this->buatAkun('Anggota Bidang', 'Ekonomi');

        $this->post(route('admin.akun.alih-admin'), [
            'id_tujuan'   => $tujuan->id_pengguna,
            'peran_turun' => 'Anggota Bidang',
            'bidang'      => ['Pendidikan'],
        ])->assertRedirect(route('login'));

        $this->assertSame('Anggota Bidang', $this->peranDari($tujuan));
        $this->assertSame(1, $this->jumlahAdmin());
    }

    // ---------------------------------------------------------------
    // AC-5: log
    // ---------------------------------------------------------------

    public function test_pengalihan_tercatat_di_log_tanpa_data_rahasia(): void
    {
        Log::spy();
        $tujuan = $this->buatAkun('Anggota Bidang', 'Ekonomi');

        $this->kirimAlih($tujuan->id_pengguna);

        Log::shouldHaveReceived('info')->withArgs(function ($pesan, $konteks) use ($tujuan) {
            return $pesan === 'Admin mengalihkan peran Admin'
                && $konteks['admin_lama_id'] === $this->admin->id_pengguna
                && $konteks['admin_baru_id'] === $tujuan->id_pengguna
                && ! empty($konteks['dicatat_pada'])
                && ! array_key_exists('password', $konteks)
                && ! array_key_exists('kata_sandi', $konteks)
                && ! array_key_exists('token', $konteks);
        })->once();
    }
}