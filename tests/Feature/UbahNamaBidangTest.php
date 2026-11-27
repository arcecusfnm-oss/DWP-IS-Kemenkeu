<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * B-025 T-014: ubah nama bidang. Self-contained.
 */
class UbahNamaBidangTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $admin;
    private Pengguna $pj;
    private Bidang $bidangA;
    private Bidang $bidangB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bidangA = Bidang::firstOrCreate(['nama_bidang' => 'Bidang Uji A']);
        $this->bidangB = Bidang::firstOrCreate(['nama_bidang' => 'Bidang Uji B']);

        $this->admin = $this->buatAkun('Admin', 'admin_uji', $this->bidangA->id_bidang);
        $this->pj    = $this->buatAkun('Penanggung Jawab', 'pj_uji', null);
    }

    private function buatAkun(string $peran, string $username, ?int $idBidang): Pengguna
    {
        $p = Peran::firstOrCreate(['nama_peran' => $peran]);

        return Pengguna::create([
            'nama_lengkap'           => ucfirst(str_replace('_', ' ', $username)),
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => 'Rahasia#12345',
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $p->id_peran,
            'id_bidang'              => $idBidang,
        ]);
    }

    private function namaBidang(Bidang $b): string
    {
        return DB::table('bidang')->where('id_bidang', $b->id_bidang)->value('nama_bidang');
    }

    private function ubah(Bidang $b, $nama)
    {
        return $this->put(route('admin.bidang.update', $b->id_bidang), ['nama_bidang' => $nama]);
    }

    public function test_admin_dapat_mengubah_nama_bidang(): void
    {
        $this->actingAs($this->admin);

        $this->ubah($this->bidangA, 'Bidang Baru')
            ->assertRedirect(route('admin.bidang.index'))
            ->assertSessionHas('success');

        $this->assertSame('Bidang Baru', $this->namaBidang($this->bidangA));
    }

    public function test_halaman_edit_tampil_dengan_nama_dan_token_csrf(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.bidang.edit', $this->bidangA->id_bidang))
            ->assertOk()
            ->assertSee('Bidang Uji A')
            ->assertSee('name="_token"', false);
    }

    public function test_relasi_tetap_tersambung_setelah_nama_diubah(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'anggota_uji', $this->bidangA->id_bidang);

        $this->actingAs($this->admin);
        $this->ubah($this->bidangA, 'Bidang Baru');

        $this->assertSame($this->bidangA->id_bidang, (int) $akun->fresh()->id_bidang);

        $namaLewatJoin = DB::table('pengguna')
            ->join('bidang', 'bidang.id_bidang', '=', 'pengguna.id_bidang')
            ->where('pengguna.id_pengguna', $akun->id_pengguna)
            ->value('bidang.nama_bidang');

        $this->assertSame('Bidang Baru', $namaLewatJoin);
    }

    public function test_nama_kosong_atau_hanya_spasi_ditolak(): void
    {
        $this->actingAs($this->admin);

        foreach (['', '   ', null] as $nilai) {
            $this->ubah($this->bidangA, $nilai)->assertSessionHasErrors('nama_bidang');
        }

        $this->assertSame('Bidang Uji A', $this->namaBidang($this->bidangA));
    }

    public function test_nama_sama_dengan_bidang_lain_ditolak(): void
    {
        $this->actingAs($this->admin);

        $this->ubah($this->bidangA, 'Bidang Uji B')->assertSessionHasErrors('nama_bidang');

        $this->assertSame('Bidang Uji A', $this->namaBidang($this->bidangA));
    }

    public function test_nama_sendiri_tidak_dianggap_duplikat(): void
    {
        $this->actingAs($this->admin);

        $this->ubah($this->bidangA, 'Bidang Uji A')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.bidang.index'));

        $this->assertSame('Bidang Uji A', $this->namaBidang($this->bidangA));
    }

    public function test_spasi_awal_dan_akhir_dipangkas(): void
    {
        $this->actingAs($this->admin);

        $this->ubah($this->bidangA, '   Bidang Rapi   ')->assertSessionHasNoErrors();

        $this->assertSame('Bidang Rapi', $this->namaBidang($this->bidangA));
    }

    public function test_panjang_maksimal_100_karakter(): void
    {
        $this->actingAs($this->admin);

        $this->ubah($this->bidangA, str_repeat('a', 101))->assertSessionHasErrors('nama_bidang');
        $this->assertSame('Bidang Uji A', $this->namaBidang($this->bidangA));

        $seratus = str_repeat('a', 100);
        $this->ubah($this->bidangA, $seratus)->assertSessionHasNoErrors();
        $this->assertSame($seratus, $this->namaBidang($this->bidangA));
    }

    public function test_bidang_sistem_dari_config_tidak_dapat_diubah(): void
    {
        $sekretariat = Bidang::firstOrCreate(['nama_bidang' => 'Sekretariat']);

        $this->actingAs($this->admin);

        $this->ubah($sekretariat, 'Sekretariat Baru')->assertSessionHasErrors('nama_bidang');

        $this->assertSame('Sekretariat', $this->namaBidang($sekretariat));
    }

    public function test_bidang_tidak_ada_mengembalikan_404(): void
    {
        $this->actingAs($this->admin);

        $this->put(route('admin.bidang.update', 999999), ['nama_bidang' => 'X'])->assertNotFound();
        $this->get(route('admin.bidang.edit', 999999))->assertNotFound();
    }

    public function test_non_admin_ditolak(): void
    {
        $this->actingAs($this->pj);

        $this->ubah($this->bidangA, 'Dibajak')->assertForbidden();
        $this->get(route('admin.bidang.edit', $this->bidangA->id_bidang))->assertForbidden();

        $this->assertSame('Bidang Uji A', $this->namaBidang($this->bidangA));
    }

    public function test_tanpa_sesi_diarahkan_ke_login(): void
    {
        $this->ubah($this->bidangA, 'Dibajak')->assertRedirect(route('login'));
        $this->get(route('admin.bidang.edit', $this->bidangA->id_bidang))->assertRedirect(route('login'));

        $this->assertSame('Bidang Uji A', $this->namaBidang($this->bidangA));
    }

    public function test_perubahan_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $this->actingAs($this->admin);
        $this->ubah($this->bidangA, 'Bidang Baru');

        Log::shouldHaveReceived('info')->withArgs(function ($pesan, $konteks) {
            return $pesan === 'Admin mengubah nama bidang'
                && $konteks['admin_id'] === $this->admin->id_pengguna
                && $konteks['admin_username'] === 'admin_uji'
                && $konteks['bidang_id'] === $this->bidangA->id_bidang
                && $konteks['nama_lama'] === 'Bidang Uji A'
                && $konteks['nama_baru'] === 'Bidang Baru'
                && isset($konteks['dicatat_pada'])
                && ! array_key_exists('kata_sandi_hash', $konteks);
        })->once();
    }

    public function test_nama_baru_tampil_di_daftar_akun(): void
    {
        // admin_uji dari setUp() terhubung ke bidangA
        $this->actingAs($this->admin);
        $this->ubah($this->bidangA, 'Bidang Baru');

        $this->get(route('admin.akun.index'))
            ->assertOk()
            ->assertSee('Bidang Baru')
            ->assertDontSee('Bidang Uji A');
    }

    public function test_nama_baru_tampil_di_bagan_struktur(): void
    {
        $this->actingAs($this->admin);
        $this->ubah($this->bidangA, 'Bidang Baru');

        $this->getJson(route('api.struktur-organisasi'))
            ->assertOk()
            ->assertSee('Bidang Baru')
            ->assertDontSee('Bidang Uji A');
    }

    public function test_nama_baru_tampil_di_daftar_anggota(): void
    {
        DB::table('anggota')->insert([
            'nama_lengkap'  => 'Anggota Uji',
            'nomor_telepon' => '081234567890',
            'id_bidang'     => $this->bidangA->id_bidang,
            'status'        => 'aktif',
            'dibuat_oleh'   => $this->admin->id_pengguna,
            'dihapus_pada'  => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($this->admin);
        $this->ubah($this->bidangA, 'Bidang Baru');

        $this->getJson(route('admin.anggota.index'))
            ->assertOk()
            ->assertSee('Bidang Baru')
            ->assertDontSee('Bidang Uji A');
    }
}