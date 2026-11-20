<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Support\LogKeamanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ErrorLogKeamananTest extends TestCase
{
    use RefreshDatabase;

    private const RAHASIA = 'RAHASIA_UJI_ERROR_500';
    private const PESAN_UMUM = 'Terjadi kesalahan pada sistem. Silakan coba kembali.';

    /** @var array<int, array{level: string, message: string, context: array}> */
    private array $logTertangkap = [];

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(MessageLogged::class, function (MessageLogged $e): void {
            $this->logTertangkap[] = [
                'level'   => $e->level,
                'message' => $e->message,
                'context' => $e->context,
            ];
        });
    }

    private function logSebagaiTeks(): string
    {
        return (string) json_encode($this->logTertangkap, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    private function buatPengguna(): Pengguna
    {
        $peran = Peran::firstOrCreate(['nama_peran' => 'Admin']);
        $bidang = Bidang::firstOrCreate(['nama_bidang' => 'Umum']);

        return Pengguna::create([
            'nama_lengkap' => 'Fahira Putri Ananta',
            'nama_pengguna' => 'fahira',
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $peran->id_peran,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    // Test (a): error yang dipicu hanya menampilkan pesan umum + ID kejadian.
    public function test_error_500_hanya_menampilkan_pesan_umum_dan_id_kejadian(): void
    {
        // Respons JSON
        $json = $this->getJson('/_test-error-500');

        $json->assertStatus(500)
            ->assertJsonPath('message', self::PESAN_UMUM)
            ->assertJsonStructure(['message', 'incident_id']);

        $idJson = $json->json('incident_id');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $idJson
        );
        $this->assertStringNotContainsString(self::RAHASIA, $json->getContent());

        // Log punya incident_id yang sama
        $entriError = array_values(array_filter(
            $this->logTertangkap,
            fn (array $l) => ($l['context']['incident_id'] ?? null) === $idJson
        ));
        $this->assertCount(1, $entriError, 'Log harus memuat incident_id yang sama dengan respons.');

        // Respons HTML
        $html = $this->get('/_test-error-500');

        $html->assertStatus(500)
            ->assertDontSee(self::RAHASIA)
            ->assertDontSee('RuntimeException');

        // Log tidak memuat pesan exception mentah
        $this->assertStringNotContainsString(self::RAHASIA, $this->logSebagaiTeks());
    }

    // Test (b): log setelah login uji tidak memuat token/password.
    public function test_log_login_tidak_memuat_token_atau_password(): void
    {
        $pengguna = $this->buatPengguna();
        $hash = (string) $pengguna->fresh()->kata_sandi_hash;

        // Login gagal
        $this->post('/login', [
            'username' => 'fahira',
            'password' => 'salahsekali999',
        ]);

        // Login berhasil
        $this->post('/login', [
            'username' => 'fahira',
            'password' => 'password123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($pengguna);

        $logKeamanan = array_values(array_filter(
            $this->logTertangkap,
            fn (array $l) => $l['message'] === 'keamanan'
                && ($l['context']['aksi'] ?? null) === 'login'
        ));

        $this->assertCount(2, $logKeamanan);
        $this->assertSame('gagal', $logKeamanan[0]['context']['hasil']);
        $this->assertSame('berhasil', $logKeamanan[1]['context']['hasil']);

        $idAcak = LogKeamanan::idAnonim($pengguna->getKey());
        $this->assertMatchesRegularExpression('/^usr_[0-9a-f]{16}$/', $idAcak);

        foreach ($logKeamanan as $entri) {
            $this->assertSame($idAcak, $entri['context']['pengguna']);
            $this->assertNotEmpty($entri['context']['ip']);
            $this->assertNotEmpty($entri['context']['waktu']);
        }

        // Tidak ada password, hash, token sesi/CSRF, atau username di log.
        $teksLog = $this->logSebagaiTeks();
        $this->assertStringNotContainsString('password123', $teksLog);
        $this->assertStringNotContainsString('salahsekali999', $teksLog);
        $this->assertStringNotContainsString($hash, $teksLog);
        $this->assertStringNotContainsString('fahira', $teksLog);
        $this->assertStringNotContainsString(csrf_token(), $teksLog);
        $this->assertStringNotContainsString(session()->getId(), $teksLog);

        // Tabel log_login juga tidak menyimpan username.
        $this->assertDatabaseHas('log_login', [
            'pengguna_id' => $pengguna->getKey(),
            'nama_pengguna' => null,
        ]);
    }
}