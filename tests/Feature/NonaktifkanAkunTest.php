<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;

class NonaktifkanAkunTest extends TestCase
{
    use RefreshDatabase;

    private function buatAkun(string $peran, string $username): Pengguna
    {
        $role = Peran::firstOrCreate([
            'nama_peran' => $peran,
        ]);

        $bidang = Bidang::firstOrCreate([
            'nama_bidang' => 'Umum',
        ]);

        return Pengguna::create([
            'nama_lengkap' => 'Pengguna Pengujian',
            'nama_pengguna' => $username,
            'kata_sandi_hash' => Hash::make('password123'),
            'status' => 'aktif',
            'perlu_ganti_kata_sandi' => false,
            'id_peran' => $role->id_peran,
            'id_bidang' => $bidang->id_bidang,
        ]);
    }

    public function test_admin_dapat_menonaktifkan_akun(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'anggota_test');

        $response = $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.nonaktifkan', $target->id_pengguna));

        $response->assertRedirect(route('admin.akun.index'));
        $response->assertSessionHas('sukses');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'nonaktif',
            'dihapus_pada' => null,
        ]);
    }

    public function test_semua_sesi_akun_target_dicabut(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'anggota_test');

        foreach (range(1, 2) as $i) {
            DB::table('sessions')->insert([
                'id' => Str::random(40),
                'user_id' => $target->id_pengguna,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Browser',
                'payload' => 'test-payload',
                'last_activity' => time(),
            ]);
        }

        $this->assertDatabaseCount('sessions', 2);

        $this->actingAs($admin)
            ->post(route('admin.akun.nonaktifkan', $target->id_pengguna));

        $this->assertDatabaseMissing('sessions', [
            'user_id' => $target->id_pengguna,
        ]);
    }

    public function test_admin_tidak_dapat_menonaktifkan_akunnya_sendiri(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');

        $response = $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.nonaktifkan', $admin->id_pengguna));

        $response->assertRedirect(route('admin.akun.index'));
        $response->assertSessionHas('galat');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $admin->id_pengguna,
            'status' => 'aktif',
        ]);
    }

    public function test_non_admin_tidak_dapat_menonaktifkan_akun(): void
    {
        $anggota = $this->buatAkun('Anggota', 'anggota_test');
        $target = $this->buatAkun('Anggota', 'target_test');

        $response = $this->actingAs($anggota)
            ->post(route('admin.akun.nonaktifkan', $target->id_pengguna));

        $response->assertForbidden();

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'aktif',
        ]);
    }

    public function test_akun_yang_sudah_nonaktif_tidak_diubah_kembali(): void
    {
        $admin = $this->buatAkun('Admin', 'admin_test');
        $target = $this->buatAkun('Anggota', 'anggota_test');

        DB::table('pengguna')
            ->where('id_pengguna', $target->id_pengguna)
            ->update(['status' => 'nonaktif']);

        $response = $this->actingAs($admin)
            ->from(route('admin.akun.index'))
            ->post(route('admin.akun.nonaktifkan', $target->id_pengguna));

        $response->assertRedirect(route('admin.akun.index'));
        $response->assertSessionHas('galat');

        $this->assertDatabaseHas('pengguna', [
            'id_pengguna' => $target->id_pengguna,
            'status' => 'nonaktif',
        ]);
    }
}
