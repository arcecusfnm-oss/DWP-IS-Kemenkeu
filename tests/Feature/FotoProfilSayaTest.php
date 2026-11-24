<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Filesystem\FilesystemAdapter;

/**
 * Foto Profil Saya: upload dan tampil milik sendiri. Self-contained.
 */
class FotoProfilSayaTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $admin;
private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('local');
        $this->admin = $this->buatAkun('Admin', 'admin_uji');
    }

    private function buatAkun(string $peran, string $username): Pengguna
    {
        $p = Peran::firstOrCreate(['nama_peran' => $peran]);

        return Pengguna::create([
            'nama_lengkap'           => ucfirst(str_replace('_', ' ', $username)),
            'nama_pengguna'          => $username,
            'kata_sandi_hash'        => 'Rahasia#12345',
            'status'                 => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran'               => $p->id_peran,
            'id_bidang'              => null,
        ]);
    }

    private function buatAnggota(Pengguna $akun, array $ekstra = []): int
    {
        return DB::table('anggota')->insertGetId(array_merge([
            'id_pengguna'   => $akun->id_pengguna,
            'nama_lengkap'  => 'Ibu ' . $akun->nama_pengguna,
            'nomor_telepon' => '081234567890',
            'status'        => 'aktif',
            'dibuat_oleh'   => $this->admin->id_pengguna,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $ekstra));
    }

    private function unggah(Pengguna $akun, $berkas)
    {
        return $this->actingAs($akun)->post('/profil-saya/foto', ['foto' => $berkas]);
    }

    private function fotoPath(int $idAnggota): ?string
    {
        return DB::table('anggota')->where('id_anggota', $idAnggota)->value('foto_path');
    }

    public function test_anggota_dapat_mengunggah_foto_sendiri(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'unggah');
        $id = $this->buatAnggota($akun);

        $this->unggah($akun, UploadedFile::fake()->image('foto.jpg'))
            ->assertRedirect(route('profil-saya'))
            ->assertSessionHas('success');

        $path = $this->fotoPath($id);
        $this->assertNotNull($path);
        $this->disk->assertExists($path);
    }

    public function test_foto_lama_dihapus_saat_diganti(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'ganti');
        $id = $this->buatAnggota($akun);

        $this->unggah($akun, UploadedFile::fake()->image('satu.jpg'));
        $lama = $this->fotoPath($id);

        $this->unggah($akun, UploadedFile::fake()->image('dua.png'));
        $baru = $this->fotoPath($id);

        $this->assertNotSame($lama, $baru);
        $this->disk->assertMissing($lama);
        $this->disk->assertExists($baru);
    }

    public function test_tipe_dan_ukuran_tidak_valid_ditolak(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'tolak');
        $id = $this->buatAnggota($akun);

        $this->unggah($akun, UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'))
            ->assertSessionHasErrors('foto');

        $this->unggah($akun, UploadedFile::fake()->image('besar.jpg')->size(3000))
            ->assertSessionHasErrors('foto');

        $this->actingAs($akun)->post('/profil-saya/foto', [])
            ->assertSessionHasErrors('foto');

        $this->assertNull($this->fotoPath($id));
    }

    public function test_akun_belum_tertaut_tidak_bisa_unggah(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'belum_tertaut');

        $this->unggah($akun, UploadedFile::fake()->image('foto.jpg'))
            ->assertSessionHas('error');

        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_unggah_hanya_mengubah_anggota_sendiri(): void
    {
        $saya = $this->buatAkun('Anggota Bidang', 'saya');
        $lain = $this->buatAkun('Anggota Bidang', 'orang_lain');
        $idSaya = $this->buatAnggota($saya);
        $idLain = $this->buatAnggota($lain);

        $this->actingAs($saya)->post('/profil-saya/foto', [
            'foto'       => UploadedFile::fake()->image('foto.jpg'),
            'id_anggota' => $idLain,
            'id'         => $idLain,
        ]);

        $this->assertNotNull($this->fotoPath($idSaya));
        $this->assertNull($this->fotoPath($idLain));
    }

    public function test_foto_sendiri_dapat_dibuka_dan_id_lain_diabaikan(): void
    {
        $saya = $this->buatAkun('Anggota Bidang', 'saya_foto');
        $lain = $this->buatAkun('Anggota Bidang', 'lain_foto');
        $this->buatAnggota($saya);
        $idLain = $this->buatAnggota($lain);

        $this->unggah($saya, UploadedFile::fake()->image('saya.jpg'));
        $this->unggah($lain, UploadedFile::fake()->image('lain.jpg'));

        $pathSaya = DB::table('anggota')->where('id_pengguna', $saya->id_pengguna)->value('foto_path');
        $isiSaya = $this->disk->get($pathSaya);

        $r = $this->actingAs($saya)->get('/profil-saya/foto?id=' . $idLain . '&id_anggota=' . $idLain)
            ->assertOk();

        $this->assertSame($isiSaya, $r->streamedContent());
    }

    public function test_foto_belum_ada_atau_belum_tertaut_mengembalikan_404(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'tanpa_foto');
        $this->buatAnggota($akun);
        $this->actingAs($akun)->get('/profil-saya/foto')->assertNotFound();

        $belum = $this->buatAkun('Anggota Bidang', 'tanpa_anggota');
        $this->actingAs($belum)->get('/profil-saya/foto')->assertNotFound();
    }

    public function test_file_hilang_dari_disk_mengembalikan_404(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'file_hilang');
        $id = $this->buatAnggota($akun, ['foto_path' => 'foto-anggota/tidak-ada.jpg']);

        $this->actingAs($akun)->get('/profil-saya/foto')->assertNotFound();
    }

    public function test_tanpa_sesi_diarahkan_ke_login(): void
    {
        $this->get('/profil-saya/foto')->assertRedirect(route('login'));
        $this->post('/profil-saya/foto', [
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertRedirect(route('login'));
    }

    public function test_profil_saya_menampilkan_url_foto_atau_strip(): void
    {
        $akun = $this->buatAkun('Anggota Bidang', 'url_foto');
        $this->buatAnggota($akun);

        $this->assertSame('-', $this->actingAs($akun)->get('/profil-saya')->viewData('anggota')->foto);

        $this->unggah($akun, UploadedFile::fake()->image('foto.jpg'));

        $foto = $this->actingAs($akun)->get('/profil-saya')->viewData('anggota')->foto;
        $this->assertStringContainsString('/profil-saya/foto', $foto);
        $this->assertStringNotContainsString('foto-anggota/', $foto); // path disk tidak bocor
    }
}