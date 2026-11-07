<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T-59: Unit test reset kata sandi (B-009).
 *
 * AC 1  : kata sandi sementara acak, tampil satu kali
 * AC 2  : pengguna wajib ganti kata sandi sebelum membuka halaman lain
 * AC 3  : kata sandi sementara tidak berlaku setelah diganti; baru minimal 8 karakter
 * AC 4  : kata sandi sementara tersimpan sebagai hash dan tidak muncul di log
 * AC 5  : hanya Admin yang dapat mengatur ulang kata sandi
 */
class ResetKataSandiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function buatPengguna(string $namaPeran, string $username): Pengguna
    {
        $idPeran = Peran::where('nama_peran', $namaPeran)->value('id_peran')
            ?? Peran::create(['nama_peran' => $namaPeran])->id_peran;
        $idBidang = Bidang::value('id_bidang')
            ?? Bidang::create(['nama_bidang' => 'Umum'])->id_bidang;

        return Pengguna::create([
            'nama_lengkap'           => 'Uji ' . $username,
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => Hash::make('password123'),
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $idPeran,
            'id_bidang'              => $idBidang,
        ]);
    }

    /** Admin mereset akun target, lalu mengembalikan kata sandi sementara dari flash session. */
    private function resetDanAmbilSementara(Pengguna $admin, Pengguna $target): string
    {
        $this->actingAs($admin)
            ->post(route('admin.akun.reset-password', $target->id_pengguna))
            ->assertRedirect(route('admin.akun.index'))
            ->assertSessionHas('kata_sandi_sementara');

        return session('kata_sandi_sementara');
    }

    private function hashDiDatabase(Pengguna $akun): string
    {
        return DB::table('pengguna')
            ->where('id_pengguna', $akun->id_pengguna)
            ->value('kata_sandi_hash');
    }

    // ---------------------------------------------------------------
    // AC 1: acak
    // ---------------------------------------------------------------

    public function test_reset_membuat_kata_sandi_sementara_acak_dan_berbeda_tiap_reset()
    {
        $admin  = $this->buatPengguna('Admin', 'admin_reset');
        $target = $this->buatPengguna('Anggota Bidang', 'target_reset');

        $pertama = $this->resetDanAmbilSementara($admin, $target);
        $kedua   = $this->resetDanAmbilSementara($admin, $target);

        $this->assertNotSame($pertama, $kedua);
        $this->assertGreaterThanOrEqual(8, strlen($pertama));
        $this->assertGreaterThanOrEqual(8, strlen($kedua));
        $this->assertNotSame('password123', $pertama);
    }

    // ---------------------------------------------------------------
    // AC 2: wajib ganti
    // ---------------------------------------------------------------

    public function test_reset_menandai_akun_wajib_ganti_kata_sandi()
    {
        $admin  = $this->buatPengguna('Admin', 'admin_reset');
        $target = $this->buatPengguna('Anggota Bidang', 'target_reset');

        $this->assertFalse((bool) $target->fresh()->perlu_ganti_kata_sandi);

        $this->resetDanAmbilSementara($admin, $target);

        $this->assertTrue((bool) $target->fresh()->perlu_ganti_kata_sandi);
    }

    /**
     * CATATAN: di main saat ini belum ada middleware yang membaca
     * perlu_ganti_kata_sandi, jadi test ini diperkirakan GAGAL sampai
     * penjaga "wajib ganti" dibuat (T-58 / koordinasi dengan Fahira dan Novi).
     * Setelah layar wajib ganti ada, ganti assertRedirect() di bawah dengan
     * route layar tersebut.
     */
    public function test_akun_wajib_ganti_tidak_dapat_membuka_halaman_lain_sebelum_ganti()
    {
        $admin  = $this->buatPengguna('Admin', 'admin_reset');
        $target = $this->buatPengguna('Admin', 'target_wajib');

        $sementara = $this->resetDanAmbilSementara($admin, $target);

        $this->post(route('logout'));
        $this->post(route('login.process'), [
            'username' => 'target_wajib',
            'password' => $sementara,
        ]);
        $this->assertAuthenticated();

        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(); // TODO: assertRedirect(route('<layar ganti kata sandi>'))
    }

    // ---------------------------------------------------------------
    // AC 3: tidak berlaku setelah diganti; minimal 8 karakter
    // ---------------------------------------------------------------

    public function test_kata_sandi_sementara_tidak_berlaku_setelah_diganti()
    {
        $admin  = $this->buatPengguna('Admin', 'admin_reset');
        $target = $this->buatPengguna('Anggota Bidang', 'target_reset');

        $sementara = $this->resetDanAmbilSementara($admin, $target);

        // Sebelum diganti, kata sandi sementara masih sah.
        $this->assertTrue(Hash::check($sementara, $this->hashDiDatabase($target)));

        $this->actingAs($target->fresh())
            ->post(route('password.update'), [
                'kata_sandi_lama' => $sementara,
                'kata_sandi_baru' => 'KataSandiBaru123',
            ])
            ->assertSessionHasNoErrors();

        $hashSesudah = $this->hashDiDatabase($target);
        $this->assertFalse(Hash::check($sementara, $hashSesudah));
        $this->assertTrue(Hash::check('KataSandiBaru123', $hashSesudah));

        // Coba masuk lewat form login: sementara ditolak, baru diterima.
        $this->post(route('logout'));

        $this->post(route('login.process'), [
            'username' => 'target_reset',
            'password' => $sementara,
        ])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post(route('login.process'), [
            'username' => 'target_reset',
            'password' => 'KataSandiBaru123',
        ])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    /**
     * CATATAN: ChangePasswordController@update belum mengembalikan
     * perlu_ganti_kata_sandi ke false, jadi test ini diperkirakan GAGAL
     * sampai perbaikan itu dibuat (koordinasi dengan Fahira, T-26/T-57).
     */
    public function test_penanda_wajib_ganti_dihapus_setelah_kata_sandi_diganti()
    {
        $admin  = $this->buatPengguna('Admin', 'admin_reset');
        $target = $this->buatPengguna('Anggota Bidang', 'target_reset');

        $sementara = $this->resetDanAmbilSementara($admin, $target);

        $this->actingAs($target->fresh())
            ->post(route('password.update'), [
                'kata_sandi_lama' => $sementara,
                'kata_sandi_baru' => 'KataSandiBaru123',
            ]);

        $this->assertFalse((bool) $target->fresh()->perlu_ganti_kata_sandi);
    }

    public function test_kata_sandi_baru_kurang_dari_8_karakter_ditolak()
    {
        $admin  = $this->buatPengguna('Admin', 'admin_reset');
        $target = $this->buatPengguna('Anggota Bidang', 'target_reset');

        $sementara = $this->resetDanAmbilSementara($admin, $target);

        $this->actingAs($target->fresh())
            ->post(route('password.update'), [
                'kata_sandi_lama' => $sementara,
                'kata_sandi_baru' => 'abcdefg', // 7 karakter
            ])
            ->assertSessionHasErrors('kata_sandi_baru');

        // Kata sandi tidak berubah: sementara masih sah.
        $this->assertTrue(Hash::check($sementara, $this->hashDiDatabase($target)));
    }

    // ---------------------------------------------------------------
    // AC 4: hash dan tidak masuk log
    // ---------------------------------------------------------------

    public function test_kata_sandi_sementara_tersimpan_sebagai_hash_dan_tidak_masuk_log()
    {
        $entriLog = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$entriLog) {
            $entriLog[] = $e->message . ' ' . json_encode($e->context);
        });

        $admin  = $this->buatPengguna('Admin', 'admin_reset');
        $target = $this->buatPengguna('Anggota Bidang', 'target_reset');

        $sementara = $this->resetDanAmbilSementara($admin, $target);

        // Di basis data: bukan teks asli, tetapi hash yang cocok.
        $hash = $this->hashDiDatabase($target);
        $this->assertNotSame($sementara, $hash);
        $this->assertTrue(Hash::check($sementara, $hash));

        // Di log: ada catatan reset, tetapi tanpa kata sandi sementara.
        $semuaLog = implode("\n", $entriLog);
        $this->assertStringContainsString('Admin mereset kata sandi akun', $semuaLog);
        $this->assertStringNotContainsString($sementara, $semuaLog);
    }

    // ---------------------------------------------------------------
    // AC 5: hanya Admin
    // ---------------------------------------------------------------

    public function test_reset_ditolak_tanpa_sesi_dan_untuk_peran_selain_admin()
    {
        $target  = $this->buatPengguna('Anggota Bidang', 'target_reset');
        $anggota = $this->buatPengguna('Anggota Bidang', 'anggota_biasa');
        $hashAwal = $this->hashDiDatabase($target);

        // Tanpa sesi: diarahkan ke halaman masuk.
        $this->post(route('admin.akun.reset-password', $target->id_pengguna))
            ->assertRedirect(route('login'));

        // Peran selain Admin: ditolak 403.
        $this->actingAs($anggota)
            ->post(route('admin.akun.reset-password', $target->id_pengguna))
            ->assertStatus(403);

        // Kata sandi dan penanda tidak berubah.
        $this->assertSame($hashAwal, $this->hashDiDatabase($target));
        $this->assertFalse((bool) $target->fresh()->perlu_ganti_kata_sandi);
    }
}