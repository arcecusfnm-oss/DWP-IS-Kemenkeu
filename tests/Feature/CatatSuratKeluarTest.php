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
        ], $override);
    }

    private function kirimSurat(array $data, ?Pengguna $pengguna = null)
    {
        return $this->actingAs($pengguna ?? $this->admin())
            ->post(route('surat.keluar.store'), $data);
    }

    // ---------------------------------------------------- penyimpanan

    public function test_admin_berhasil_mencatat_surat_keluar(): void
    {
        Log::spy();

        $data = $this->payload();

        $response = $this->kirimSurat($data);

        $response->assertRedirect(route('surat.index', ['jenis' => 'keluar']))
            ->assertSessionHas('sukses', 'Surat keluar berhasil dicatat.');

        $this->assertDatabaseHas('surat_keluar', [
            'nomor_surat' => $data['nomor_surat'],
            'tanggal' => $data['tanggal_surat'],
            'tujuan' => $data['tujuan'],
            'perihal' => $data['perihal'],
            'status_terkirim' => 'belum',
            'dibuat_oleh' => $this->admin()->id_pengguna,
        ]);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Surat keluar dicatat', \Mockery::on(function ($context) use ($data) {
                return $context['pengguna_id'] === $this->admin()->id_pengguna
                    && $context['nomor_surat'] === $data['nomor_surat'];
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

    // ------------------------------------------------------ validasi

    public function test_field_wajib_ditolak_jika_kosong(): void
    {
        foreach ([
            'nomor_surat',
            'tanggal_surat',
            'tujuan',
            'perihal',
        ] as $field) {
            $data = $this->payload([$field => '']);

            $this->kirimSurat($data)
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('surat_keluar', 0);
    }

    public function test_format_tanggal_tidak_valid_ditolak(): void
    {
        $this->kirimSurat($this->payload([
            'tanggal_surat' => '20-11-2031',
        ]))
            ->assertSessionHasErrors('tanggal_surat');

        $this->assertDatabaseCount('surat_keluar', 0);
    }

    public function test_nomor_surat_duplikat_di_surat_keluar_ditolak(): void
    {
        $data = $this->payload();

        $this->kirimSurat($data)
            ->assertSessionHas('sukses');

        $this->kirimSurat($data)
            ->assertSessionHasErrors('nomor_surat');

        $this->assertDatabaseCount('surat_keluar', 1);
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

    // ---------------------------------------------------- akses

    public function test_role_selain_admin_dan_sekretariat_ditolak(): void
    {
        $user = $this->akun('Ketua');

        $this->kirimSurat($this->payload(), $user)
            ->assertForbidden();

        $this->assertDatabaseCount('surat_keluar', 0);
    }

    public function test_pengguna_belum_login_ditolak(): void
    {
        $this->postJson(route('surat.keluar.store'), $this->payload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('surat_keluar', 0);
    }
}
