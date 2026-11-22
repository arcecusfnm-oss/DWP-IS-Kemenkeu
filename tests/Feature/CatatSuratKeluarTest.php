<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CatatSuratKeluarTest extends TestCase
{
    use DatabaseTransactions;

    private ?Pengguna $admin = null;
    private int $urut = 0;
    private static ?string $hash = null;

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

    private function payload(array $override = []): array
    {
        return array_merge([
            'nomor_surat' => 'ND-' . uniqid(),
            'tanggal_surat' => '2031-11-20',
            'tujuan' => 'Kantor Pengujian',
            'perihal' => 'Undangan rapat pengujian',
            'id_bidang_terkait' => $this->bidang('Sekretariat')->id_bidang,
            'status_terkirim' => 'belum',
            'keterangan' => null,
        ], $override);
    }

    private function kirimSurat(array $data, ?Pengguna $pengguna = null)
    {
        return $this->actingAs($pengguna ?? $this->admin())
            ->post(route('surat.keluar.store'), $data);
    }

    private function jumlahSuratKeluar(): int
    {
        return DB::table('surat_keluar')->count();
    }

    // ---------------------------------------------------- penyimpanan

    public function test_admin_berhasil_mencatat_surat_keluar(): void
    {
        Log::spy();

        $data = $this->payload([
            'tujuan' => 'RAHASIA_TUJUAN_SURAT',
            'perihal' => 'RAHASIA_PERIHAL_SURAT',
        ]);

        $response = $this->kirimSurat($data);

        $response->assertRedirect(route('surat.index', ['jenis' => 'keluar']))
            ->assertSessionHas('sukses', 'Surat keluar berhasil dicatat.');

        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => $data['nomor_surat'],
            'tanggal' => $data['tanggal_surat'],
            'tujuan' => $data['tujuan'],
            'perihal' => $data['perihal'],
            'id_bidang_terkait' => $data['id_bidang_terkait'],
            'status_terkirim' => $data['status_terkirim'],
            'keterangan' => $data['keterangan'],
            'dibuat_oleh' => $this->admin()->id_pengguna,
        ]);

        // Log memuat pencatat dan nomor surat, tetapi tidak memuat isi
        // tujuan dan perihal (log tanpa data sensitif).
        Log::shouldHaveReceived('info')
            ->once()
            ->with('Surat keluar dicatat', \Mockery::on(function ($context) use ($data) {
                $json = json_encode($context);

                return $context['pengguna_id'] === $this->admin()->id_pengguna
                    && $context['nomor_surat'] === $data['nomor_surat']
                    && ! str_contains($json, 'RAHASIA_TUJUAN_SURAT')
                    && ! str_contains($json, 'RAHASIA_PERIHAL_SURAT');
            }));
    }

    public function test_sekretariat_dengan_bidang_langsung_bisa_mencatat_surat(): void
    {
        $sekretariat = $this->bidang('Sekretariat');
        $user = $this->akun('Staf', $sekretariat);
        $data = $this->payload();

        $this->kirimSurat($data, $user)
            ->assertRedirect(route('surat.index', ['jenis' => 'keluar']))
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => $data['nomor_surat'],
            'dibuat_oleh' => $user->id_pengguna,
        ]);
    }

    public function test_sekretariat_melalui_pivot_bisa_mencatat_surat(): void
    {
        $sekretariat = $this->bidang('Sekretariat');
        $user = $this->akun('Staf');

        DB::table('pengguna_bidang')->insert([
            'id_pengguna' => $user->id_pengguna,
            'id_bidang' => $sekretariat->id_bidang,
        ]);

        $data = $this->payload();

        $this->kirimSurat($data, $user)
            ->assertRedirect(route('surat.index', ['jenis' => 'keluar']))
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => $data['nomor_surat'],
            'dibuat_oleh' => $user->id_pengguna,
        ]);
    }

    public function test_surat_keluar_tersimpan_tampil_di_daftar_jenis_keluar(): void
    {
        $nomor = 'ND-DAFTAR-' . uniqid();
        $tujuan = 'Biro Tujuan ' . uniqid();

        $this->kirimSurat($this->payload([
            'nomor_surat' => $nomor,
            'tujuan' => $tujuan,
        ]))->assertSessionHas('sukses');

        $this->actingAs($this->admin())
            ->get(route('surat.index', ['jenis' => 'keluar']))
            ->assertOk()
            ->assertSee($nomor)
            ->assertSee($tujuan);
    }

    // ------------------------------------------------------ validasi

    public function test_field_wajib_ditolak_jika_kosong(): void
    {
        $awal = $this->jumlahSuratKeluar();

        foreach ([
            'nomor_surat' => 'Nomor surat wajib diisi',
            'tanggal_surat' => 'Tanggal surat wajib diisi',
            'tujuan' => 'Tujuan wajib diisi',
            'perihal' => 'Perihal wajib diisi',
            'id_bidang_terkait' => 'Bidang terkait wajib dipilih',
            'status_terkirim' => 'Status terkirim wajib dipilih',
        ] as $field => $pesan) {
            $data = $this->payload([$field => '']);

            $this->kirimSurat($data)
                ->assertSessionHasErrors([$field => $pesan]);
        }

        $this->assertSame($awal, $this->jumlahSuratKeluar());
    }

    public function test_field_baru_dengan_nilai_tidak_valid_ditolak(): void
    {
        $awal = $this->jumlahSuratKeluar();

        foreach ([
            'id_bidang_terkait' => 999999,
            'status_terkirim' => 'invalid',
            'keterangan' => str_repeat('a', 2001),
        ] as $field => $nilai) {
            $this->kirimSurat($this->payload([
                $field => $nilai,
            ]))->assertSessionHasErrors($field);
        }

        $this->assertSame($awal, $this->jumlahSuratKeluar());
    }

    public function test_format_tanggal_tidak_valid_ditolak(): void
    {
        $awal = $this->jumlahSuratKeluar();

        $this->kirimSurat($this->payload([
            'tanggal_surat' => '20-11-2031',
        ]))
            ->assertSessionHasErrors('tanggal_surat');

        $this->assertSame($awal, $this->jumlahSuratKeluar());
    }

    public function test_nomor_surat_duplikat_di_surat_keluar_ditolak(): void
    {
        $awal = $this->jumlahSuratKeluar();
        $data = $this->payload();

        $this->kirimSurat($data)
            ->assertSessionHas('sukses');

        $this->kirimSurat($data)
            ->assertSessionHasErrors(['nomor_surat' => 'Nomor surat sudah tercatat']);

        $this->assertSame($awal + 1, $this->jumlahSuratKeluar());
    }

    public function test_nomor_surat_yang_sama_dengan_surat_masuk_tetap_boleh(): void
    {
        $nomor = 'NOMOR-BERSAMA-' . uniqid();

        DB::table('surat_masuk')->insert([
            'nomor_agenda' => 'AGENDA-' . uniqid(),
            'nomor_surat' => $nomor,
            'asal_surat' => 'Instansi Pengujian',
            'tanggal_surat' => '2031-11-20',
            'tanggal_diterima' => '2031-11-20',
            'lampiran' => '1 berkas',
            'perihal' => 'Surat masuk pengujian',
            'dibuat_oleh' => $this->admin()->id_pengguna,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $this->payload([
            'nomor_surat' => $nomor,
        ]);

        $this->kirimSurat($data)
            ->assertRedirect(route('surat.index', ['jenis' => 'keluar']))
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => $nomor,
        ]);
    }

    // ------------------------------------------------------- keamanan

    public function test_tag_skrip_pada_tujuan_tersimpan_dan_tampil_sebagai_teks(): void
    {
        $nomor = 'ND-XSS-' . uniqid();
        $tujuan = '<script>alert("xss-keluar")</script> "Uji" & \'Coba\'';

        $this->kirimSurat($this->payload([
            'nomor_surat' => $nomor,
            'tujuan' => $tujuan,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => $nomor,
            'tujuan' => $tujuan,
        ]);

        // Output harus di-escape: tag mentah tidak boleh muncul di HTML.
        $this->actingAs($this->admin())
            ->get(route('surat.index', ['jenis' => 'keluar']))
            ->assertOk()
            ->assertDontSee('<script>alert("xss-keluar")</script>', false)
            ->assertSee('<script>alert("xss-keluar")</script>');
    }

    // ---------------------------------------------------- akses

    public function test_role_selain_admin_dan_sekretariat_ditolak(): void
    {
        $this->bidang('Sekretariat');
        $ekonomi = $this->bidang('Bidang Ekonomi');
        $awal = $this->jumlahSuratKeluar();

        foreach ([
            'Anggota Bidang',
            'Pimpinan Bidang',
            'Penanggung Jawab',
            'Bendahara',
            'Penasihat',
        ] as $namaPeran) {
            $user = $this->akun($namaPeran, $ekonomi);

            $this->actingAs($user)
                ->get(route('surat.keluar.create'))
                ->assertForbidden();

            $this->kirimSurat($this->payload(), $user)
                ->assertForbidden();
        }

        $this->assertSame($awal, $this->jumlahSuratKeluar());
    }

    public function test_pengguna_belum_login_ditolak(): void
    {
        $awal = $this->jumlahSuratKeluar();

        $this->postJson(route('surat.keluar.store'), $this->payload())
            ->assertUnauthorized();

        $this->assertSame($awal, $this->jumlahSuratKeluar());
    }
}