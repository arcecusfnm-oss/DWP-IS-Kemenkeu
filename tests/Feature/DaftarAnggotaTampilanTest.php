<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T-43 (B-020): uji tampilan daftar anggota + filter peran, pencarian, posisi, kelengkapan.
 */
class DaftarAnggotaTampilanTest extends TestCase
{
    use RefreshDatabase;

    private function buatPengguna(string $namaPeran, string $username): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => $namaPeran]);

        $id = DB::table('pengguna')->insertGetId([
            'nama_lengkap'           => 'Akun ' . $username,
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => Hash::make('Rahasia123!'),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => 0,
            'id_peran'               => $peran->id_peran,
            'id_bidang'              => null,
            'created_at'             => now(),
            'updated_at'             => now(),
        ], 'id_pengguna');

        return Pengguna::find($id);
    }

    private function buatAnggota(string $nama, ?int $idBidang, int $dibuatOleh, array $tambahan = []): int
    {
        return DB::table('anggota')->insertGetId(array_merge([
            'nama_lengkap'  => $nama,
            'nomor_telepon' => '081234567890',
            'id_bidang'     => $idBidang,
            'status'        => 'aktif',
            'dibuat_oleh'   => $dibuatOleh,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $tambahan), 'id_anggota');
    }

    private function nama($respons): array
    {
        return collect($respons->json('anggota.data'))->pluck('nama_lengkap')->all();
    }

    public function test_filter_peran_hanya_menampilkan_anggota_dengan_peran_itu(): void
    {
        $admin  = $this->buatPengguna('Admin', 'admin.uji');
        $bend   = $this->buatPengguna('Bendahara', 'bend.uji');
        $angg   = $this->buatPengguna('Anggota Bidang', 'angg.uji');
        $this->buatAnggota('Ani', null, $admin->id_pengguna, ['id_pengguna' => $bend->id_pengguna]);
        $this->buatAnggota('Budi', null, $admin->id_pengguna, ['id_pengguna' => $angg->id_pengguna]);

        $respons = $this->actingAs($admin)
            ->getJson(route('admin.anggota.index', ['peran' => $bend->id_peran]));

        $this->assertSame(['Ani'], $this->nama($respons));
    }

    public function test_pencarian_mencocokkan_nama_dan_username(): void
    {
        $admin = $this->buatPengguna('Admin', 'admin.uji');
        $akun  = $this->buatPengguna('Anggota Bidang', 'siti.login');
        $this->buatAnggota('Ani Wijaya', null, $admin->id_pengguna);
        $this->buatAnggota('Siti Rahma', null, $admin->id_pengguna, ['id_pengguna' => $akun->id_pengguna]);

        $this->assertSame(['Ani Wijaya'], $this->nama(
            $this->actingAs($admin)->getJson(route('admin.anggota.index', ['q' => 'wijaya']))
        ));
        $this->assertSame(['Siti Rahma'], $this->nama(
            $this->actingAs($admin)->getJson(route('admin.anggota.index', ['q' => 'siti.login']))
        ));
    }

    public function test_pencarian_persen_tidak_dianggap_wildcard(): void
    {
        $admin = $this->buatPengguna('Admin', 'admin.uji');
        $this->buatAnggota('Ani', null, $admin->id_pengguna);

        $respons = $this->actingAs($admin)->getJson(route('admin.anggota.index', ['q' => '%']));

        $respons->assertOk()->assertJsonPath('anggota.total', 0);
    }

    public function test_posisi_dan_kelengkapan_data_terkirim(): void
    {
        $admin = $this->buatPengguna('Admin', 'admin.uji');
        $unit  = DB::table('unit_kerja')->insertGetId([
            'nama_unit_kerja' => 'Biro Uji', 'created_at' => now(), 'updated_at' => now(),
        ], 'id_unit_kerja');

        $this->buatAnggota('Lengkap', null, $admin->id_pengguna, [
            'jabatan_bidang'     => 'ketua',
            'tempat_lahir'       => 'Jakarta',
            'tanggal_lahir'      => '1990-01-01',
            'alamat_lengkap'     => 'Jl. Uji 1',
            'email'              => 'a@uji.id',
            'id_unit_kerja'      => $unit,
            'tingkat_pendidikan' => 'S1 (Sarjana)',
        ]);
        $this->buatAnggota('Belum', null, $admin->id_pengguna);

        $baris = collect($this->actingAs($admin)->getJson(route('admin.anggota.index'))->json('anggota.data'))
            ->keyBy('nama_lengkap');

        $this->assertEquals(1, $baris['Lengkap']['lengkap']);
        $this->assertSame('ketua', $baris['Lengkap']['jabatan_bidang']);
        $this->assertEquals(0, $baris['Belum']['lengkap']);
        $this->assertNull($baris['Belum']['jabatan_bidang']);
    }

    public function test_view_dropdown_bidang_memakai_id_dan_filter_menyisakan_bidang_itu(): void
    {
        $admin    = $this->buatPengguna('Admin', 'admin.uji');
        $ekonomi  = Bidang::firstOrCreate(['nama_bidang' => 'Ekonomi']);
        $pendidik = Bidang::firstOrCreate(['nama_bidang' => 'Pendidikan']);
        $this->buatAnggota('Ani Ekonomi', $ekonomi->id_bidang, $admin->id_pengguna);
        $this->buatAnggota('Budi Pendidikan', $pendidik->id_bidang, $admin->id_pengguna);

        $html = $this->actingAs($admin)
            ->get(route('admin.anggota.index', ['bidang' => $ekonomi->id_bidang]))
            ->assertOk()
            ->assertSee('value="' . $ekonomi->id_bidang . '" selected', false)
            ->assertSee('Ani Ekonomi')
            ->assertDontSee('Budi Pendidikan');
    }

    public function test_view_bidang_tanpa_anggota_menampilkan_pesan_kosong(): void
    {
        $admin  = $this->buatPengguna('Admin', 'admin.uji');
        $kosong = Bidang::firstOrCreate(['nama_bidang' => 'Kesehatan']);

        $this->actingAs($admin)
            ->get(route('admin.anggota.index', ['bidang' => $kosong->id_bidang]))
            ->assertOk()
            ->assertSee('Tidak ada anggota yang sesuai.');
    }
}