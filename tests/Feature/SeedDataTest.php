<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Override;
use Tests\TestCase;

class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

    }

    public function test_ada_lima_peran_dan_lima_bidang(): void
    {
        $this->assertTrue(Pengguna::where('status', 'aktif')->exists());
        $this->assertTrue(Pengguna::where('status', 'nonaktif')->exists());

    }

    public function test_admin_aktif_ada(): void
    {
        $admin = Pengguna::where('nama_pengguna', 'admin')->first();

        $this->assertSame('Admin', $admin->peran->nama_peran);
        $this->assertSame('aktif', $admin->status);
    }

    public function test_kata_sandi_tersimpan_dalam_bentuk_hash(): void
    {
        $admin = Pengguna::where('nama_pengguna', 'admin')->first();

        $this->assertNotSame(env('SEED_ADMIN_PASSWORD'), $admin->kata_sandi_hash);
        $this->assertTrue(Hash::check(env('SEED_ADMIN_PASSWORD'), $admin->kata_sandi_hash));
    }
}
