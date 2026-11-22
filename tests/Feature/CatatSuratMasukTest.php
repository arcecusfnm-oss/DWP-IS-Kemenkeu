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
 * T-61 (B-035): pengujian endpoint catat surat masuk.
 */
class CatatSuratMasukTest extends TestCase
{
    use DatabaseTransactions;

    private ?Pengguna $admin = null;
    private static ?string $hash = null;

    // ---------- Helper ----------

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
        $p->nama_pengguna = 'uji_sm_' . uniqid();
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

    private function sekretariat(): Pengguna
    {
        return $this->akun('Anggota Bidang', $this->bidang('Sekretariat'));
    }

    private function payloadValid(array $override = []): array
    {
        $tanggalDiterima = $override['tanggal_diterima'] ?? '2032-01-12';

        return array_merge([
            'nomor_surat'       => 'SM-' . uniqid(),
            'asal_surat'        => 'Sekretariat Jenderal Kemenkeu',
            'tanggal_surat'     => '2032-01-10',
            'tanggal_diterima'  => $tanggalDiterima,
            'lampiran'          => '1 berkas',
            'perihal'           => 'Undangan Rapat Koordinasi DWP',
            'disposisi'         => 'Diteruskan ke Ketua Bidang Pendidikan',
            'tanggal_disposisi' => $tanggalDiterima,
            'keterangan'        => 'Harap ditindaklanjuti sebelum Jumat',
        ], $override);
    }

    // ---------- AC 1: Skenario utama (Admin & Sekretariat, lengkap 10 kolom template) ----------

    public function test_sekretariat_berhasil_mencatat_surat_masuk_lengkap_dan_tampil_di_daftar(): void
    {
        $sekretariat = $this->sekretariat();
        $nomorSurat = 'UND-01/SJ.1/' . uniqid();

        $payload = $this->payloadValid([
            'nomor_surat'       => $nomorSurat,
            'asal_surat'        => 'Biro Umum Kemenkeu',
            'tanggal_surat'     => '2032-01-05',
            'tanggal_diterima'  => '2032-01-06',
            'lampiran'          => '2 lembar',
            'perihal'           => 'Permohonan Peserta Seminar Nasional',
            'disposisi'         => 'Koordinasikan dengan Bidang Sosial Budaya',
            'tanggal_disposisi' => '2032-01-07',
            'keterangan'        => 'Arsip penting',
        ]);

        $res = $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $payload);

        $res->assertRedirect(route('surat.index', ['jenis' => 'masuk']));
        $res->assertSessionHas('sukses', 'Surat masuk berhasil dicatat.');
        $res->assertSessionHasNoErrors();

        $tersimpan = DB::table('surat_masuk')
            ->where('nomor_surat', $nomorSurat)
            ->first();

        $this->assertNotNull($tersimpan);
        $this->assertSame('1/I/2032', $tersimpan->nomor_agenda);
        $this->assertSame('Biro Umum Kemenkeu', $tersimpan->asal_surat);
        $this->assertSame('2032-01-05', $tersimpan->tanggal_surat);
        $this->assertSame('2032-01-06', $tersimpan->tanggal_diterima);
        $this->assertSame('2 lembar', $tersimpan->lampiran);
        $this->assertSame('Permohonan Peserta Seminar Nasional', $tersimpan->perihal);
        $this->assertSame('Koordinasikan dengan Bidang Sosial Budaya', $tersimpan->disposisi);
        $this->assertSame('2032-01-07', $tersimpan->tanggal_disposisi);
        $this->assertSame('Arsip penting', $tersimpan->keterangan);
        $this->assertSame($sekretariat->id_pengguna, (int) $tersimpan->dibuat_oleh);

        $this->actingAs($sekretariat)
            ->get(route('surat.index', ['jenis' => 'masuk']))
            ->assertOk()
            ->assertSee($nomorSurat)
            ->assertSee('Biro Umum Kemenkeu')
            ->assertSee('Permohonan Peserta Seminar Nasional');
    }

    public function test_admin_berhasil_mencatat_surat_masuk_tanpa_kolom_opsional(): void
    {
        $admin = $this->admin();
        $nomorSurat = 'SM-ADMIN-' . uniqid();

        $this->actingAs($admin)
            ->post(route('surat.simpan-masuk'), [
                'nomor_surat'      => $nomorSurat,
                'asal_surat'       => 'Direktorat Jenderal Pajak',
                'tanggal_surat'    => '2032-03-10',
                'tanggal_diterima' => '2032-03-10',
                'lampiran'         => '-',
                'perihal'          => 'Pemberitahuan Kegiatan Bakti Sosial',
            ])
            ->assertRedirect(route('surat.index', ['jenis' => 'masuk']))
            ->assertSessionHas('sukses', 'Surat masuk berhasil dicatat.');

        $this->assertDatabaseHas('surat_masuk', [
            'nomor_surat'       => $nomorSurat,
            'nomor_agenda'      => '1/III/2032',
            'lampiran'          => '-',
            'disposisi'         => null,
            'tanggal_disposisi' => null,
            'keterangan'        => null,
        ]);
    }

    // ---------- AC 2: Nomor agenda berurutan & format No. urut/Bulan Romawi/Tahun ----------

    public function test_nomor_agenda_berurutan_lintas_bulan_dan_reset_tahun(): void
    {
        $sekretariat = $this->sekretariat();

        $no1 = 'SM-JAN-1-' . uniqid();
        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'      => $no1,
                'tanggal_surat'    => '2033-01-05',
                'tanggal_diterima' => '2033-01-06',
            ]))
            ->assertSessionHasNoErrors();

        $no2 = 'SM-JAN-2-' . uniqid();
        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'      => $no2,
                'tanggal_surat'    => '2033-01-15',
                'tanggal_diterima' => '2033-01-16',
            ]))
            ->assertSessionHasNoErrors();

        $no3 = 'SM-FEB-1-' . uniqid();
        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'      => $no3,
                'tanggal_surat'    => '2033-02-01',
                'tanggal_diterima' => '2033-02-02',
            ]))
            ->assertSessionHasNoErrors();

        $no4 = 'SM-JAN-TAHUN-BARU-' . uniqid();
        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'      => $no4,
                'tanggal_surat'    => '2034-01-05',
                'tanggal_diterima' => '2034-01-06',
            ]))
            ->assertSessionHasNoErrors();

        $agenda1 = DB::table('surat_masuk')->where('nomor_surat', $no1)->value('nomor_agenda');
        $agenda2 = DB::table('surat_masuk')->where('nomor_surat', $no2)->value('nomor_agenda');
        $agenda3 = DB::table('surat_masuk')->where('nomor_surat', $no3)->value('nomor_agenda');
        $agenda4 = DB::table('surat_masuk')->where('nomor_surat', $no4)->value('nomor_agenda');

        $this->assertSame('1/I/2033', $agenda1);
        $this->assertSame('2/I/2033', $agenda2);
        $this->assertSame('3/II/2033', $agenda3);
        $this->assertSame('1/I/2034', $agenda4);
        $this->assertCount(4, array_unique([$agenda1, $agenda2, $agenda3, $agenda4]));
    }

    // ---------- AC 3: Disposisi administratif & validasi urutan tanggal ----------

    public function test_disposisi_dan_tanggal_disposisi_tersimpan_langsung_sebagai_data_administratif(): void
    {
        $sekretariat = $this->sekretariat();
        $nomorSurat = 'SM-DISPO-' . uniqid();

        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'       => $nomorSurat,
                'tanggal_surat'     => '2032-04-01',
                'tanggal_diterima'  => '2032-04-02',
                'disposisi'         => 'Tindak lanjuti oleh Bidang Ekonomi',
                'tanggal_disposisi' => '2032-04-03',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('surat_masuk', [
            'nomor_surat'       => $nomorSurat,
            'disposisi'         => 'Tindak lanjuti oleh Bidang Ekonomi',
            'tanggal_disposisi' => '2032-04-03',
        ]);
    }

    public function test_tanggal_diterima_sebelum_tanggal_surat_ditolak(): void
    {
        $sekretariat = $this->sekretariat();
        $nomorSurat = 'SM-TGL-' . uniqid();

        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'      => $nomorSurat,
                'tanggal_surat'    => '2032-05-15',
                'tanggal_diterima' => '2032-05-10',
            ]))
            ->assertSessionHasErrors([
                'tanggal_diterima' => 'Tanggal diterima tidak boleh sebelum tanggal surat',
            ]);

        $this->assertDatabaseMissing('surat_masuk', ['nomor_surat' => $nomorSurat]);
    }

    public function test_tanggal_disposisi_sebelum_tanggal_diterima_ditolak(): void
    {
        $sekretariat = $this->sekretariat();
        $nomorSurat = 'SM-DISPO-TGL-' . uniqid();

        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'       => $nomorSurat,
                'tanggal_surat'     => '2032-05-10',
                'tanggal_diterima'  => '2032-05-15',
                'tanggal_disposisi' => '2032-05-14',
            ]))
            ->assertSessionHasErrors([
                'tanggal_disposisi' => 'Tanggal disposisi tidak boleh lebih awal dari tanggal diterima',
            ]);

        $this->assertDatabaseMissing('surat_masuk', ['nomor_surat' => $nomorSurat]);
    }

    // ---------- AC 4: Kolom wajib kosong & nomor surat ganda ditolak ----------

    #[DataProvider('kolomWajib')]
    public function test_kolom_wajib_kosong_ditolak_dengan_pesan_pada_kolom_terkait(string $kolom, string $pesan): void
    {
        $sekretariat = $this->sekretariat();
        $jumlahAwal = DB::table('surat_masuk')->count();

        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([$kolom => '']))
            ->assertSessionHasErrors([$kolom => $pesan]);

        $this->assertSame($jumlahAwal, DB::table('surat_masuk')->count());
    }

    public function test_nomor_surat_ganda_ditolak(): void
    {
        $sekretariat = $this->sekretariat();
        $nomorGanda = 'SM-GANDA-' . uniqid();

        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid(['nomor_surat' => $nomorGanda]))
            ->assertSessionHasNoErrors();

        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat' => $nomorGanda,
                'perihal'     => 'Perihal Berbeda',
            ]))
            ->assertSessionHasErrors(['nomor_surat' => 'Nomor surat sudah tercatat']);

        $this->assertSame(
            1,
            DB::table('surat_masuk')->where('nomor_surat', $nomorGanda)->count()
        );
    }

    // ---------- AC 5: Keamanan RBAC & sesi ----------

    #[DataProvider('peranTidakBerhak')]
    public function test_peran_selain_admin_dan_sekretariat_ditolak(string $namaPeran): void
    {
        $this->bidang('Sekretariat');
        $akun = $this->akun($namaPeran, $this->bidang('Bidang Ekonomi'));
        $nomorSurat = 'SM-FORBID-' . uniqid();

        $this->actingAs($akun)
            ->get(route('surat.catat-masuk'))
            ->assertForbidden();

        $this->actingAs($akun)
            ->post(route('surat.simpan-masuk'), $this->payloadValid(['nomor_surat' => $nomorSurat]))
            ->assertForbidden();

        $this->assertDatabaseMissing('surat_masuk', ['nomor_surat' => $nomorSurat]);
    }

    public function test_tanpa_sesi_diarahkan_ke_halaman_masuk(): void
    {
        $nomorSurat = 'SM-GUEST-' . uniqid();

        $this->get(route('surat.catat-masuk'))
            ->assertRedirect(route('login'));

        $this->post(route('surat.simpan-masuk'), $this->payloadValid(['nomor_surat' => $nomorSurat]))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('surat_masuk', ['nomor_surat' => $nomorSurat]);
    }

    // ---------- AC 6: Sanitasi & pencegahan XSS ----------

    public function test_input_karakter_khusus_dan_tag_skrip_disimpan_dan_tampil_aman_dari_xss(): void
    {
        $sekretariat = $this->sekretariat();
        $nomorSurat = 'SM-XSS-' . uniqid();
        $perihalBerbahaya = '<script>alert("xss-surat")</script> Undangan "Khusus" & Evaluasi 100%';

        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'      => $nomorSurat,
                'tanggal_surat'    => '2032-06-01',
                'tanggal_diterima' => '2032-06-01',
                'perihal'          => $perihalBerbahaya,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('surat_masuk', [
            'nomor_surat' => $nomorSurat,
            'perihal'     => $perihalBerbahaya,
        ]);

        $this->actingAs($sekretariat)
            ->get(route('surat.index', ['jenis' => 'masuk']))
            ->assertOk()
            ->assertDontSee('<script>alert("xss-surat")</script>', false)
            ->assertSee('<script>alert("xss-surat")</script>');
    }

    // ---------- AC 7: Log audit tanpa data sensitif ----------

    public function test_pencatatan_surat_masuk_tercatat_di_log_tanpa_data_sensitif(): void
    {
        Log::spy();

        $sekretariat = $this->sekretariat();
        $nomorSurat = 'SM-LOG-' . uniqid();

        $this->actingAs($sekretariat)
            ->post(route('surat.simpan-masuk'), $this->payloadValid([
                'nomor_surat'       => $nomorSurat,
                'asal_surat'        => 'Inspektorat Jenderal Kemenkeu',
                'tanggal_surat'     => '2032-07-10',
                'tanggal_diterima'  => '2032-07-11',
                'perihal'           => 'RAHASIA_PERIHAL_AUDIT_INTERNAL',
                'disposisi'         => 'RAHASIA_DISPOSISI_PIMPINAN',
                'tanggal_disposisi' => '2032-07-12',
                'keterangan'        => 'RAHASIA_KETERANGAN_TAMBAHAN',
            ]))
            ->assertSessionHasNoErrors();

        Log::shouldHaveReceived('info')
            ->withArgs(function ($pesan, $konteks = []) use ($sekretariat, $nomorSurat) {
                $json = json_encode($konteks);

                return $pesan === 'Surat masuk dicatat'
                    && $konteks['pengguna_id'] === $sekretariat->id_pengguna
                    && $konteks['pengguna_username'] === $sekretariat->nama_pengguna
                    && $konteks['nomor_agenda'] === '1/VII/2032'
                    && $konteks['nomor_surat'] === $nomorSurat
                    && ! empty($konteks['id_surat_masuk'])
                    && ! empty($konteks['dicatat_pada'])
                    && ! str_contains($json, 'RAHASIA_PERIHAL_AUDIT_INTERNAL')
                    && ! str_contains($json, 'RAHASIA_DISPOSISI_PIMPINAN')
                    && ! str_contains($json, 'RAHASIA_KETERANGAN_TAMBAHAN');
            })
            ->once();
    }

    // ---------- Data Provider ----------

    public static function kolomWajib(): array
    {
        return [
            'nomor_surat'      => ['nomor_surat', 'Nomor surat wajib diisi'],
            'asal_surat'       => ['asal_surat', 'Asal surat wajib diisi'],
            'tanggal_surat'    => ['tanggal_surat', 'Tanggal surat wajib diisi'],
            'tanggal_diterima' => ['tanggal_diterima', 'Tanggal diterima wajib diisi'],
            'lampiran'         => ['lampiran', 'Lampiran wajib diisi'],
            'perihal'          => ['perihal', 'Perihal wajib diisi'],
        ];
    }

    public static function peranTidakBerhak(): array
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