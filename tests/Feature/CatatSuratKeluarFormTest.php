<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatatSuratKeluarFormTest extends TestCase
{
    use DatabaseTransactions;

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
        $p->nama_lengkap = 'Uji Form ' . $namaPeran;
        $p->nama_pengguna = 'uji_form_' . uniqid();
        $p->kata_sandi_hash = self::$hash;
        $p->status = 'aktif';
        $p->perlu_ganti_kata_sandi = false;
        $p->id_peran = $this->peran($namaPeran)->id_peran;
        $p->id_bidang = $bidang?->id_bidang;
        $p->save();

        return $p;
    }

    public function test_admin_bisa_membuka_form_catat_surat_keluar(): void
    {
        $admin = $this->akun('Admin');
        $sekretariat = $this->bidang('Sekretariat');
        $ekonomi = $this->bidang('Bidang Ekonomi');

        $this->actingAs($admin)
            ->get(route('surat.keluar.create'))
            ->assertOk()
            ->assertViewIs('surat.catat-keluar')
            ->assertSee('Catat Surat Keluar')
            ->assertSee('nomor_surat')
            ->assertSee('tanggal_surat')
            ->assertSee('tujuan')
            ->assertSee('perihal')
            ->assertSee('id_bidang_terkait')
            ->assertSee('Bidang Terkait')
            ->assertSee($sekretariat->nama_bidang)
            ->assertSee($ekonomi->nama_bidang)
            ->assertSee('status_terkirim')
            ->assertSee('Status Terkirim')
            ->assertSee('Sudah')
            ->assertSee('Belum')
            ->assertSee('Batal')
            ->assertSee('keterangan')
            ->assertSee('Keterangan');
    }

    public function test_sekretariat_bisa_membuka_form_catat_surat_keluar(): void
    {
        $user = $this->akun('Staf', $this->bidang('Sekretariat'));

        $this->actingAs($user)
            ->get(route('surat.keluar.create'))
            ->assertOk()
            ->assertViewIs('surat.catat-keluar');
    }

    public function test_sekretariat_di_pivot_bisa_membuka_form_catat_surat_keluar(): void
    {
        $sekretariat = $this->bidang('Sekretariat');
        $user = $this->akun('Staf');

        DB::table('pengguna_bidang')->insert([
            'id_pengguna' => $user->id_pengguna,
            'id_bidang' => $sekretariat->id_bidang,
        ]);

        $this->actingAs($user)
            ->get(route('surat.keluar.create'))
            ->assertOk()
            ->assertViewIs('surat.catat-keluar');
    }

    public function test_role_selain_admin_dan_sekretariat_ditolak_membuka_form(): void
    {
        $user = $this->akun('Ketua');

        $this->actingAs($user)
            ->get(route('surat.keluar.create'))
            ->assertForbidden();
    }

    public function test_pengguna_belum_login_tidak_bisa_membuka_form(): void
    {
        $this->get(route('surat.keluar.create'))
            ->assertRedirect();
    }
}
