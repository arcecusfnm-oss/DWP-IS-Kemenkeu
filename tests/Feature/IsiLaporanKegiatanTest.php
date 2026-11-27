<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IsiLaporanKegiatanTest extends TestCase
{
    use RefreshDatabase;

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
            ?? DB::table('peran')->insertGetId([
                'nama_peran' => $nama,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function buatPengguna(string $peran, int $idBidang, string $username): Pengguna
    {
        return Pengguna::create([
            'nama_lengkap'            => 'Nama ' . $username,
            'nama_pengguna'           => $username,
            'kata_sandi_hash'         => 'Password123!',
            'status'                  => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'                => $this->buatPeran($peran),
            'id_bidang'               => $idBidang,
        ]);
    }

    private function buatProgram(int $idBidang, int $dibuatOleh): int
    {
        return DB::table('program_kerja')->insertGetId([
            'id_bidang'        => $idBidang,
            'nama_program'     => 'Program Pengujian',
            'tujuan'            => 'Tujuan program test',
            'sasaran_peserta'  => 'Anggota DWP',
            'metode'            => 'Pelaksanaan langsung',
            'tanggal_mulai'    => '2026-01-01',
            'tanggal_selesai'  => '2026-12-31',
            'tempat'             => 'Tempat pengujian',
            'anggaran'           => 1000000,
            'keterangan'         => null,
            'status'             => 'Disetujui',
            'dibuat_oleh'       => $dibuatOleh,
            'created_at'         => now(),
            'updated_at'         => now(),
        ], 'id_program_kerja');
    }

    private function buatKegiatan(int $idProgram, int $dibuatOleh, array $ubah = []): int
    {
        return DB::table('kegiatan')->insertGetId(array_merge([
            'id_program_kerja' => $idProgram,
            'nama_kegiatan'     => 'Kegiatan Pengujian',
            'tanggal_mulai'    => '2026-11-01',
            'tanggal_selesai'  => '2026-11-30',
            'deskripsi'          => 'Deskripsi kegiatan test',
            'dibuat_oleh'      => $dibuatOleh,
            'dihapus_pada'     => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $ubah), 'id_kegiatan');
    }

    private function dataValid(array $ubah = []): array
    {
        return array_merge([
            'id_kegiatan'        => null,
            'judul_kegiatan'     => 'Laporan Kegiatan Pengujian',
            'tanggal_kegiatan'   => now()->toDateString(),
            'penjelasan_singkat' => 'Kegiatan berjalan lancar dan mencapai tujuan.',
        ], $ubah);
    }

    private function simpan(Pengguna $pengguna, array $data)
    {
        return $this->actingAs($pengguna)
            ->postJson(route('api.laporan-kegiatan.store'), $data);
    }

    // 1. Anggota Bidang berhasil menyimpan laporan
    public function test_anggota_bidang_berhasil_menyimpan_laporan_sebagai_draf(): void
    {
        $bidang  = $this->buatBidang('Bidang Test 158');
        $anggota = $this->buatPengguna('Anggota Bidang', $bidang, 'anggota_t158');
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $kegiatan = $this->buatKegiatan($program, $anggota->id_pengguna);

        $res = $this->simpan($anggota, $this->dataValid(['id_kegiatan' => $kegiatan]));

        $res->assertCreated()
            ->assertJsonPath('status', 'Draf')
            ->assertJsonPath('pesan', 'Laporan kegiatan berhasil disimpan sebagai Draf.');

        $idLaporan = $res->json('id_laporan_kegiatan');

        $this->assertDatabaseHas('laporan_kegiatan', [
            'id_laporan_kegiatan' => $idLaporan,
            'id_kegiatan'         => $kegiatan,
            'id_bidang'           => $bidang,
            'judul_kegiatan'      => 'Laporan Kegiatan Pengujian',
            'tanggal_kegiatan'    => now()->toDateString(),
            'penjelasan_singkat'  => 'Kegiatan berjalan lancar dan mencapai tujuan.',
            'status'               => 'Draf',
            'dibuat_oleh'         => $anggota->id_pengguna,
        ]);
    }

    // 2. Pimpinan Bidang juga boleh menyimpan laporan
    public function test_pimpinan_bidang_berhasil_menyimpan_laporan(): void
    {
        $bidang   = $this->buatBidang('Bidang Pimpinan T158');
        $pimpinan = $this->buatPengguna('Pimpinan Bidang', $bidang, 'pimpinan_t158');
        $program  = $this->buatProgram($bidang, $pimpinan->id_pengguna);
        $kegiatan = $this->buatKegiatan($program, $pimpinan->id_pengguna);

        $this->simpan($pimpinan, $this->dataValid(['id_kegiatan' => $kegiatan]))
            ->assertCreated()
            ->assertJsonPath('status', 'Draf');

        $this->assertDatabaseCount('laporan_kegiatan', 1);
    }

    // 3. Field wajib tidak boleh kosong
    public function test_field_wajib_kosong_ditolak(): void
    {
        $bidang  = $this->buatBidang('Bidang Validasi T158');
        $anggota = $this->buatPengguna('Anggota Bidang', $bidang, 'validasi_t158');
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $kegiatan = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->simpan($anggota, [
            'id_kegiatan' => $kegiatan,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'judul_kegiatan',
                'tanggal_kegiatan',
                'penjelasan_singkat',
            ]);

        $this->assertDatabaseCount('laporan_kegiatan', 0);
    }

    // 4. Tanggal masa depan ditolak
    public function test_tanggal_laporan_di_masa_depan_ditolak(): void
    {
        $bidang  = $this->buatBidang('Bidang Tanggal T158');
        $anggota = $this->buatPengguna('Anggota Bidang', $bidang, 'tanggal_t158');
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $kegiatan = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->simpan($anggota, $this->dataValid([
            'id_kegiatan'      => $kegiatan,
            'tanggal_kegiatan' => now()->addDay()->toDateString(),
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['tanggal_kegiatan']);

        $this->assertDatabaseCount('laporan_kegiatan', 0);
    }

    // 5. Kegiatan dari bidang lain ditolak
    public function test_kegiatan_bidang_lain_ditolak(): void
    {
        $bidangA = $this->buatBidang('Bidang A T158');
        $bidangB = $this->buatBidang('Bidang B T158');

        $anggota = $this->buatPengguna('Anggota Bidang', $bidangA, 'anggota_bidang_a_t158');
        $pemilikB = $this->buatPengguna('Anggota Bidang', $bidangB, 'anggota_bidang_b_t158');

        $programB = $this->buatProgram($bidangB, $pemilikB->id_pengguna);
        $kegiatanB = $this->buatKegiatan($programB, $pemilikB->id_pengguna);

        $this->simpan($anggota, $this->dataValid(['id_kegiatan' => $kegiatanB]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_kegiatan']);

        $this->assertDatabaseCount('laporan_kegiatan', 0);
    }

    // 6. Kegiatan yang telah dihapus ditolak
    public function test_kegiatan_yang_sudah_dihapus_ditolak(): void
    {
        $bidang  = $this->buatBidang('Bidang Hapus T158');
        $anggota = $this->buatPengguna('Anggota Bidang', $bidang, 'hapus_t158');
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $kegiatan = $this->buatKegiatan($program, $anggota->id_pengguna, [
            'dihapus_pada' => now(),
        ]);

        $this->simpan($anggota, $this->dataValid(['id_kegiatan' => $kegiatan]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_kegiatan']);

        $this->assertDatabaseCount('laporan_kegiatan', 0);
    }

    // 7. Payload tidak dapat memanipulasi bidang atau pembuat laporan
    public function test_payload_tidak_dapat_memanipulasi_bidang_dan_pembuat_laporan(): void
    {
        $bidang  = $this->buatBidang('Bidang Aman T158');
        $bidangLain = $this->buatBidang('Bidang Manipulasi T158');
        $anggota = $this->buatPengguna('Anggota Bidang', $bidang, 'aman_t158');
        $program = $this->buatProgram($bidang, $anggota->id_pengguna);
        $kegiatan = $this->buatKegiatan($program, $anggota->id_pengguna);

        $this->simpan($anggota, $this->dataValid([
            'id_kegiatan' => $kegiatan,
            'id_bidang'   => $bidangLain,
            'dibuat_oleh'  => 999999,
        ]))->assertCreated();

        $this->assertDatabaseHas('laporan_kegiatan', [
            'id_kegiatan' => $kegiatan,
            'id_bidang'   => $bidang,
            'dibuat_oleh'  => $anggota->id_pengguna,
            'status'       => 'Draf',
        ]);

        $this->assertDatabaseCount('laporan_kegiatan', 1);
    }

    // 8. Pengguna tanpa autentikasi ditolak
    public function test_pengguna_tanpa_autentikasi_ditolak(): void
    {
        $this->postJson(route('api.laporan-kegiatan.store'), $this->dataValid())
            ->assertUnauthorized();

        $this->assertDatabaseCount('laporan_kegiatan', 0);
    }
}
