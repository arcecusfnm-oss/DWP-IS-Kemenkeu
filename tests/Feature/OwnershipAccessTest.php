<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnershipAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_cannot_change_another_users_password(): void
    {
        $peran = Peran::create([
            'nama_peran' => 'Anggota Bidang',
        ]);

        $bidang = Bidang::create([
            'nama_bidang' => 'Pendidikan',
        ]);
        $pengguna = new Pengguna();
        $pengguna->nama_lengkap = 'Anggota Pengujian';
        $pengguna->nama_pengguna = 'anggota_test';
        $pengguna->kata_sandi_hash = Hash::make('password-lama');
        $pengguna->status = 'aktif';
        $pengguna->perlu_ganti_kata_sandi = false;
        $pengguna->id_peran = $peran->id_peran;
        $pengguna->id_bidang = $bidang->id_bidang;
        $pengguna->save();

        $hashSebelum = $pengguna->kata_sandi_hash;

        $response = $this->actingAs($pengguna)
            ->withSession(['_token' => 'token-pengujian'])
            ->post('/ubah-kata-sandi', [
                '_token' => 'token-pengujian',
                'id_pengguna' => 38,
                'kata_sandi_lama' => 'password-lama',
                'kata_sandi_baru' => 'password-baru-aman',
            ]);

        $response->assertSessionHasErrors('kata_sandi_baru');

        $this->assertSame(
            $hashSebelum,
            $pengguna->fresh()->kata_sandi_hash
        );
    }
}
