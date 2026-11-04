<?php

namespace Tests\Feature\Auth;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T-28: Unit test ubah kata sandi (B-003).
 * Menguji POST /ubah-kata-sandi (route: password.update) dari T-26.
 *
 * Kasus: lama salah, kurang dari 8 karakter, berhasil, akun lain.
 */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI_LAMA = 'ArcecusLama123';
    private const SANDI_BARU = 'ArcecusBaru456';
    private const SANDI_AKUN_LAIN = 'ArcecusLain789';
    private const SANDI_SALAH = 'ArcecusSalah1';
    private const SANDI_7_KARAKTER = 'Arcecus';   // 7 karakter
    private const SANDI_8_KARAKTER = 'Arcecus1';  // tepat 8 karakter

    /**
     * Buat akun uji aktif. Cast `hashed` pada model Pengguna
     * otomatis meng-hash nilai kata_sandi_hash.
     * SESUAIKAN atribut lain dengan factory/migration tim (T-07, T-32).
     */
    private function buatPengguna(string $sandi = self::SANDI_LAMA): Pengguna
    {
        return Pengguna::factory()->create([
            'kata_sandi_hash' => $sandi,
        ]);
    }

    private function kirim(Pengguna $pengguna, array $data)
    {
        return $this->actingAs($pengguna)
            ->from('/ubah-kata-sandi')
            ->post(route('password.update'), $data);
    }

    // ---------- Kata sandi lama salah (AC 1) ----------

    public function test_kata_sandi_lama_salah_ditolak(): void
    {
        $pengguna = $this->buatPengguna();
        $hashAwal = $pengguna->kata_sandi_hash;

        $response = $this->kirim($pengguna, [
            'kata_sandi_lama' => self::SANDI_SALAH,
            'kata_sandi_baru' => self::SANDI_BARU,
        ]);

        $response->assertSessionHasErrors([
            'kata_sandi_lama' => 'Kata sandi lama salah',
        ]);
        $this->assertSame($hashAwal, $pengguna->fresh()->kata_sandi_hash);
    }

    public function test_kata_sandi_lama_kosong_ditolak(): void
    {
        $pengguna = $this->buatPengguna();
        $hashAwal = $pengguna->kata_sandi_hash;

        $response = $this->kirim($pengguna, [
            'kata_sandi_lama' => '',
            'kata_sandi_baru' => self::SANDI_BARU,
        ]);

        $response->assertSessionHasErrors([
            'kata_sandi_lama' => 'Kata sandi lama salah',
        ]);
        $this->assertSame($hashAwal, $pengguna->fresh()->kata_sandi_hash);
    }

    // ---------- Kata sandi baru kurang dari 8 karakter (AC 2) ----------

    public function test_kata_sandi_baru_7_karakter_ditolak(): void
    {
        $pengguna = $this->buatPengguna();
        $hashAwal = $pengguna->kata_sandi_hash;

        $response = $this->kirim($pengguna, [
            'kata_sandi_lama' => self::SANDI_LAMA,
            'kata_sandi_baru' => self::SANDI_7_KARAKTER,
        ]);

        $response->assertSessionHasErrors([
            'kata_sandi_baru' => 'Kata sandi baru minimal 8 karakter.',
        ]);
        $this->assertSame($hashAwal, $pengguna->fresh()->kata_sandi_hash);
    }

    public function test_kata_sandi_baru_8_karakter_diterima(): void
    {
        $pengguna = $this->buatPengguna();

        $response = $this->kirim($pengguna, [
            'kata_sandi_lama' => self::SANDI_LAMA,
            'kata_sandi_baru' => self::SANDI_8_KARAKTER,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(
            Hash::check(self::SANDI_8_KARAKTER, $pengguna->fresh()->kata_sandi_hash)
        );
    }

    // ---------- Berhasil (AC 3 dan AC 4) ----------

    public function test_ubah_kata_sandi_berhasil(): void
    {
        $pengguna = $this->buatPengguna();

        $response = $this->kirim($pengguna, [
            'kata_sandi_lama' => self::SANDI_LAMA,
            'kata_sandi_baru' => self::SANDI_BARU,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $segar = $pengguna->fresh();

        // Kata sandi baru berlaku, yang lama tidak lagi.
        $this->assertTrue(Hash::check(self::SANDI_BARU, $segar->kata_sandi_hash));
        $this->assertFalse(Hash::check(self::SANDI_LAMA, $segar->kata_sandi_hash));
    }

    public function test_kata_sandi_baru_tersimpan_sebagai_hash_bukan_teks_asli(): void
    {
        $pengguna = $this->buatPengguna();

        $this->kirim($pengguna, [
            'kata_sandi_lama' => self::SANDI_LAMA,
            'kata_sandi_baru' => self::SANDI_BARU,
        ]);

        $nilaiDiDb = $pengguna->fresh()->kata_sandi_hash;

        $this->assertNotSame(self::SANDI_BARU, $nilaiDiDb);
        $this->assertTrue(Hash::check(self::SANDI_BARU, $nilaiDiDb));
    }

    public function test_pengguna_tetap_login_setelah_ubah_kata_sandi(): void
    {
        $pengguna = $this->buatPengguna();

        $this->kirim($pengguna, [
            'kata_sandi_lama' => self::SANDI_LAMA,
            'kata_sandi_baru' => self::SANDI_BARU,
        ]);

        $this->assertAuthenticatedAs($pengguna->fresh());
    }

    // ---------- Akun lain (AC 5) ----------

    public function test_request_dengan_id_akun_lain_ditolak(): void
    {
        $pengguna = $this->buatPengguna();
        $akunLain = $this->buatPengguna(self::SANDI_AKUN_LAIN);

        $hashPenggunaAwal = $pengguna->kata_sandi_hash;
        $hashAkunLainAwal = $akunLain->kata_sandi_hash;

        $response = $this->kirim($pengguna, [
            'id_pengguna' => $akunLain->getKey(),
            'kata_sandi_lama' => self::SANDI_LAMA,
            'kata_sandi_baru' => self::SANDI_BARU,
        ]);

        $response->assertSessionHasErrors('kata_sandi_baru');

        // Tidak ada akun yang berubah.
        $this->assertSame($hashAkunLainAwal, $akunLain->fresh()->kata_sandi_hash);
        $this->assertSame($hashPenggunaAwal, $pengguna->fresh()->kata_sandi_hash);
    }

    public function test_request_dengan_user_id_akun_lain_ditolak(): void
    {
        $pengguna = $this->buatPengguna();
        $akunLain = $this->buatPengguna(self::SANDI_AKUN_LAIN);
        $hashAkunLainAwal = $akunLain->kata_sandi_hash;

        $response = $this->kirim($pengguna, [
            'user_id' => $akunLain->getKey(),
            'kata_sandi_lama' => self::SANDI_LAMA,
            'kata_sandi_baru' => self::SANDI_BARU,
        ]);

        $response->assertSessionHasErrors('kata_sandi_baru');
        $this->assertSame($hashAkunLainAwal, $akunLain->fresh()->kata_sandi_hash);
    }

    // ---------- Tanpa sesi ----------

    public function test_permintaan_tanpa_login_ditolak(): void
    {
        $pengguna = $this->buatPengguna();
        $hashAwal = $pengguna->kata_sandi_hash;

        $response = $this->post(route('password.update'), [
            'kata_sandi_lama' => self::SANDI_LAMA,
            'kata_sandi_baru' => self::SANDI_BARU,
        ]);

        $response->assertRedirect(); // middleware auth mengalihkan ke halaman masuk
        $this->assertGuest();
        $this->assertSame($hashAwal, $pengguna->fresh()->kata_sandi_hash);
    }
}