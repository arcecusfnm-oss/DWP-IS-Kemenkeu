<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-71 / B-038: unit test ubah data surat masuk dan surat keluar.
 *
 * Skenario: berhasil (AC 1), kolom kosong & nomor ganda (AC 2), ID tidak ada (AC 3),
 * peran tidak berhak & jenis tetap (AC 4), XSS (AC 5), serta log audit (AC 6).
 *
 * Mandiri: peran, bidang, akun, dan surat dibuat di dalam test, tidak bergantung
 * pada seeder atau test lain. DatabaseTransactions membatalkan semua data setelah
 * tiap test (pola yang sama dengan PencarianSuratTest).
 */
class UbahSuratTest extends TestCase
{
    use DatabaseTransactions;

    private const TABEL = ['masuk' => 'surat_masuk', 'keluar' => 'surat_keluar'];

    private const KUNCI = ['masuk' => 'id_surat_masuk', 'keluar' => 'id_surat_keluar'];

    private ?Pengguna $admin = null;
    private int $urut = 0;
    private static ?string $hash = null;

    // ---------------------------------------------------------------- setup

    private function peran(string $nama): Peran
    {
        return Peran::firstOrCreate(['nama_peran' => $nama]);
    }

    private function bidang(string $nama): Bidang
    {
        return Bidang::firstOrCreate(['nama_bidang' => $nama]);
    }

    private function akun(string $namaPeran, ?Bidang $bidang = null): Pengguna
    {
        self::$hash ??= Hash::make('Rahasia#123');

        $p = new Pengguna();
        $p->nama_lengkap = 'Uji ' . $namaPeran;
        $p->nama_pengguna = 'uji_' . uniqid();
        $p->kata_sandi_hash = self::$hash;
        $p->status = 'aktif';
        $p->perlu_ganti_kata_sandi = false;
        $p->id_peran = $this->peran($namaPeran)->id_peran;
        $p->id_bidang = $bidang?->id_bidang;
        $p->save();

        return $p;
    }

    private function admin(): Pengguna
    {
        return $this->admin ??= $this->akun('Admin');
    }

    /** Surat awal di basis data; mengembalikan id-nya. */
    private function buatSurat(string $jenis, array $override = []): int
    {
        $this->urut++;

        if ($jenis === 'masuk') {
            $data = [
                'nomor_agenda'     => $this->urut . '/XI/2031/' . uniqid(),
                'nomor_surat'      => 'M-' . $this->urut . '-' . uniqid(),
                'asal_surat'       => 'Asal Awal',
                'tanggal_surat'    => '2031-01-10',
                'tanggal_diterima' => '2031-01-12',
                'lampiran'         => '1 berkas',
                'perihal'          => 'Perihal Awal',
                'dibuat_oleh'      => $this->admin()->id_pengguna,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        } else {
            $data = [
                'nomor_surat'       => 'K-' . $this->urut . '-' . uniqid(),
                'tanggal'           => '2031-01-10',
                'tujuan'            => 'Tujuan Awal',
                'perihal'           => 'Perihal Awal',
                'status_terkirim'   => 'belum',
                'id_bidang_terkait' => $this->bidang('Bidang Awal')->id_bidang,
                'dibuat_oleh'       => $this->admin()->id_pengguna,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }

        return DB::table(self::TABEL[$jenis])
            ->insertGetId(array_merge($data, $override), self::KUNCI[$jenis]);
    }

    /** Isi form ubah yang valid (nilai baru); $override menimpa kolom tertentu. */
    private function payload(string $jenis, array $override = []): array
    {
        if ($jenis === 'masuk') {
            $data = [
                'nomor_surat'      => 'M-BARU-' . uniqid(),
                'asal_surat'       => 'Asal Baru',
                'tanggal_surat'    => '2031-02-01',
                'tanggal_diterima' => '2031-02-03',
                'lampiran'         => '2 lembar',
                'perihal'          => 'Perihal Baru',
            ];
        } else {
            $data = [
                'nomor_surat'       => 'K-BARU-' . uniqid(),
                'tanggal'           => '2031-02-01',
                'tujuan'            => 'Tujuan Baru',
                'perihal'           => 'Perihal Baru',
                'status_terkirim'   => 'sudah',
                'id_bidang_terkait' => $this->bidang('Pendidikan')->id_bidang,
            ];
        }

        return array_merge($data, $override);
    }

    private function ambil(string $jenis, int $id): ?object
    {
        return DB::table(self::TABEL[$jenis])->where(self::KUNCI[$jenis], $id)->first();
    }

    private function url(string $jenis, int $id): string
    {
        return "/surat/{$jenis}/{$id}";
    }

    private function assertTidakBerubah(string $jenis, int $id, object $sebelum): void
    {
        $this->assertEquals($sebelum, $this->ambil($jenis, $id), 'Data surat seharusnya tidak berubah.');
    }

    // ------------------------------------------------- AC 1: berhasil

    #[DataProvider('daftarJenis')]
    public function test_admin_berhasil_mengubah_surat_dan_data_tersimpan(string $jenis): void
    {
        $id = $this->buatSurat($jenis);
        $baru = $this->payload($jenis);

        $this->actingAs($this->admin())
            ->putJson($this->url($jenis, $id), $baru)
            ->assertOk()
            ->assertJsonPath('pesan', 'Data surat berhasil diubah.')
            ->assertJsonPath('jenis', $jenis)
            ->assertJsonPath('id_surat', $id);

        $this->assertDatabaseHas(self::TABEL[$jenis], [self::KUNCI[$jenis] => $id] + $baru);
    }

    #[DataProvider('daftarJenis')]
    public function test_akun_bidang_sekretariat_berhasil_mengubah_surat(string $jenis): void
    {
        $id = $this->buatSurat($jenis);
        $sekretariat = $this->akun('Anggota Bidang', $this->bidang('Sekretariat'));

        $this->actingAs($sekretariat)
            ->putJson($this->url($jenis, $id), $this->payload($jenis, ['perihal' => 'Diubah Sekretariat']))
            ->assertOk();

        $this->assertDatabaseHas(self::TABEL[$jenis], [
            self::KUNCI[$jenis] => $id,
            'perihal'           => 'Diubah Sekretariat',
        ]);
    }

    public function test_ubah_lewat_form_biasa_diarahkan_ke_daftar_surat_dengan_pesan_sukses(): void
    {
        $id = $this->buatSurat('masuk');

        $this->actingAs($this->admin())
            ->put($this->url('masuk', $id), $this->payload('masuk'))
            ->assertRedirect(route('surat.index', ['jenis' => 'masuk']))
            ->assertSessionHas('sukses', 'Data surat berhasil diubah.');
    }

    #[DataProvider('daftarJenis')]
    public function test_nomor_surat_milik_sendiri_boleh_dipertahankan(string $jenis): void
    {
        $id = $this->buatSurat($jenis);
        $nomorLama = $this->ambil($jenis, $id)->nomor_surat;

        $this->actingAs($this->admin())
            ->putJson($this->url($jenis, $id), $this->payload($jenis, ['nomor_surat' => $nomorLama]))
            ->assertOk();

        $this->assertDatabaseHas(self::TABEL[$jenis], [
            self::KUNCI[$jenis] => $id,
            'nomor_surat'       => $nomorLama,
            'perihal'           => 'Perihal Baru',
        ]);
    }

    // ------------------------------------------------- AC 2: nomor ganda & kolom kosong

    #[DataProvider('daftarJenis')]
    public function test_nomor_surat_yang_dipakai_surat_lain_ditolak_dan_data_tetap(string $jenis): void
    {
        $id = $this->buatSurat($jenis);
        $lain = $this->buatSurat($jenis);
        $nomorLain = $this->ambil($jenis, $lain)->nomor_surat;
        $sebelum = $this->ambil($jenis, $id);

        $this->actingAs($this->admin())
            ->putJson($this->url($jenis, $id), $this->payload($jenis, ['nomor_surat' => $nomorLain]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nomor_surat'])
            ->assertJsonPath('errors.nomor_surat.0', 'Nomor surat sudah tercatat');

        $this->assertTidakBerubah($jenis, $id, $sebelum);
    }

    public function test_nomor_yang_sama_dengan_surat_jenis_lain_tidak_dianggap_ganda(): void
    {
        // Unik berlaku per jenis: nomor surat keluar boleh sama dengan nomor surat masuk.
        $masuk = $this->buatSurat('masuk');
        $keluar = $this->buatSurat('keluar');
        $nomorKeluar = $this->ambil('keluar', $keluar)->nomor_surat;

        $this->actingAs($this->admin())
            ->putJson($this->url('masuk', $masuk), $this->payload('masuk', ['nomor_surat' => $nomorKeluar]))
            ->assertOk();

        $this->assertDatabaseHas('surat_masuk', ['id_surat_masuk' => $masuk, 'nomor_surat' => $nomorKeluar]);
    }

    #[DataProvider('kolomWajib')]
    public function test_kolom_wajib_yang_dikosongkan_ditolak_dan_data_tetap(string $jenis, string $kolom, string $pesan): void
    {
        $id = $this->buatSurat($jenis);
        $sebelum = $this->ambil($jenis, $id);

        $this->actingAs($this->admin())
            ->putJson($this->url($jenis, $id), $this->payload($jenis, [$kolom => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$kolom])
            ->assertJsonPath("errors.{$kolom}.0", $pesan);

        $this->assertTidakBerubah($jenis, $id, $sebelum);
    }

    // ------------------------------------------------- AC 3: ID tidak ditemukan

    #[DataProvider('daftarJenis')]
    public function test_ubah_surat_dengan_id_tidak_ada_menghasilkan_404(string $jenis): void
    {
        $this->actingAs($this->admin())
            ->putJson($this->url($jenis, 999999999), $this->payload($jenis))
            ->assertNotFound();
    }

    // ------------------------------------------------- AC 4: peran tidak berhak & jenis tetap

    #[DataProvider('perannyaTidakBerhak')]
    public function test_peran_selain_admin_dan_sekretariat_ditolak_403_dan_data_tetap(string $namaPeran): void
    {
        $this->bidang('Sekretariat'); // bidang Sekretariat ada, tetapi akun ini bukan anggotanya
        $akun = $this->akun($namaPeran, $this->bidang('Bidang Uji Lain'));

        foreach (['masuk', 'keluar'] as $jenis) {
            $id = $this->buatSurat($jenis);
            $sebelum = $this->ambil($jenis, $id);

            $this->actingAs($akun)
                ->putJson($this->url($jenis, $id), $this->payload($jenis))
                ->assertForbidden();

            $this->assertTidakBerubah($jenis, $id, $sebelum);
        }
    }

    public function test_tanpa_sesi_ditolak_dan_data_tetap(): void
    {
        foreach (['masuk', 'keluar'] as $jenis) {
            $id = $this->buatSurat($jenis);
            $sebelum = $this->ambil($jenis, $id);

            $this->putJson($this->url($jenis, $id), $this->payload($jenis))->assertUnauthorized();
            $this->put($this->url($jenis, $id), $this->payload($jenis))->assertRedirect(route('login'));

            $this->assertTidakBerubah($jenis, $id, $sebelum);
        }
    }

    public function test_jenis_surat_tidak_dapat_diubah_lewat_request(): void
    {
        $id = $this->buatSurat('masuk');
        $sebelum = $this->ambil('masuk', $id);

        $this->actingAs($this->admin())
            ->putJson($this->url('masuk', $id), $this->payload('masuk', ['jenis' => 'keluar']))
            ->assertStatus(422)
            ->assertJsonPath('errors.jenis.0', 'Jenis surat tidak dapat diubah');

        $this->assertTidakBerubah('masuk', $id, $sebelum);
    }

    // ------------------------------------------------- AC 5: sanitasi & XSS

    #[DataProvider('daftarJenis')]
    public function test_input_pembaruan_dengan_tag_skrip_tampil_aman_di_daftar(string $jenis): void
    {
        $id = $this->buatSurat($jenis);
        $perihalBerbahaya = '<script>alert("xss-ubah-' . $jenis . '")</script> Perihal "Resmi" & Evaluasi';

        $this->actingAs($this->admin())
            ->putJson($this->url($jenis, $id), $this->payload($jenis, ['perihal' => $perihalBerbahaya]))
            ->assertOk();

        $this->assertDatabaseHas(self::TABEL[$jenis], [
            self::KUNCI[$jenis] => $id,
            'perihal'           => $perihalBerbahaya,
        ]);

        $this->actingAs($this->admin())
            ->get(route('surat.index', ['jenis' => $jenis]))
            ->assertOk()
            ->assertDontSee('<script>alert("xss-ubah-' . $jenis . '")</script>', false)
            ->assertSee('<script>alert("xss-ubah-' . $jenis . '")</script>');
    }

    // ------------------------------------------------- AC 6: log audit

    #[DataProvider('daftarJenis')]
    public function test_perubahan_surat_tercatat_di_log_tanpa_data_sensitif(string $jenis): void
    {
        Log::spy();

        $sekretariat = $this->akun('Anggota Bidang', $this->bidang('Sekretariat'));
        $id = $this->buatSurat($jenis);

        $tambahan = $jenis === 'masuk'
            ? [
                'perihal'    => 'RAHASIA_PERIHAL_UBAH',
                'lampiran'   => '3 berkas asli',
                'disposisi'  => 'RAHASIA_DISPOSISI_UBAH',
                'keterangan' => 'RAHASIA_KETERANGAN_UBAH',
            ]
            : [
                'tujuan'     => 'RAHASIA_TUJUAN_UBAH',
                'perihal'    => 'RAHASIA_PERIHAL_UBAH',
                'keterangan' => 'RAHASIA_KETERANGAN_UBAH',
            ];

        $payload = $this->payload($jenis, $tambahan);

        $this->actingAs($sekretariat)
            ->putJson($this->url($jenis, $id), $payload)
            ->assertOk();

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($sekretariat, $jenis, $id, $payload) {
                $json = json_encode($konteks);
                $kolomBerubah = $konteks['kolom_berubah'] ?? [];

                $lampiranTunggal = $jenis !== 'masuk'
                    || count(array_keys($kolomBerubah, 'lampiran', true)) === 1;

                return $pesan === 'Surat diubah'
                    && $konteks['pengubah_id'] === $sekretariat->id_pengguna
                    && $konteks['pengubah_username'] === $sekretariat->nama_pengguna
                    && $konteks['jenis'] === $jenis
                    && $konteks['id_surat'] === $id
                    && $konteks['nomor_surat'] === $payload['nomor_surat']
                    && ! empty($konteks['dicatat_pada'])
                    && $lampiranTunggal
                    && ! str_contains($json, 'RAHASIA_PERIHAL_UBAH')
                    && ! str_contains($json, 'RAHASIA_TUJUAN_UBAH')
                    && ! str_contains($json, 'RAHASIA_DISPOSISI_UBAH')
                    && ! str_contains($json, 'RAHASIA_KETERANGAN_UBAH');
            })
            ->once();
    }

    // ------------------------------------------------- data provider

    public static function daftarJenis(): array
    {
        return [
            'surat masuk'  => ['masuk'],
            'surat keluar' => ['keluar'],
        ];
    }

    public static function kolomWajib(): array
    {
        return [
            'masuk: nomor_surat'        => ['masuk', 'nomor_surat', 'Nomor surat wajib diisi'],
            'masuk: asal_surat'         => ['masuk', 'asal_surat', 'Asal surat wajib diisi'],
            'masuk: tanggal_surat'      => ['masuk', 'tanggal_surat', 'Tanggal surat wajib diisi'],
            'masuk: tanggal_diterima'   => ['masuk', 'tanggal_diterima', 'Tanggal diterima wajib diisi'],
            'masuk: lampiran'           => ['masuk', 'lampiran', 'Lampiran wajib diisi'],
            'masuk: perihal'            => ['masuk', 'perihal', 'Perihal wajib diisi'],
            'keluar: nomor_surat'       => ['keluar', 'nomor_surat', 'Nomor surat wajib diisi'],
            'keluar: tanggal'           => ['keluar', 'tanggal', 'Tanggal surat wajib diisi'],
            'keluar: tujuan'            => ['keluar', 'tujuan', 'Tujuan wajib diisi'],
            'keluar: perihal'           => ['keluar', 'perihal', 'Perihal wajib diisi'],
            'keluar: status_terkirim'   => ['keluar', 'status_terkirim', 'Status terkirim wajib dipilih'],
            'keluar: id_bidang_terkait' => ['keluar', 'id_bidang_terkait', 'Bidang terkait wajib dipilih'],
        ];
    }

    public static function perannyaTidakBerhak(): array
    {
        return [
            'Anggota Bidang'   => ['Anggota Bidang'],
            'Pimpinan Bidang'  => ['Pimpinan Bidang'],
            'Penanggung Jawab' => ['Penanggung Jawab'],
            'Bendahara'        => ['Bendahara'],
            'Penasihat'        => ['Penasihat'],
        ];
    }
}