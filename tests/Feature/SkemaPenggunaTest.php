<?php

namespace Tests\Feature;

use App\Models\Bidang;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkemaPenggunaTest extends TestCase
{
    use RefreshDatabase;

    private function dataAkun(): array
    {
        return[
            'nama_lengkap' => 'Akun Uji',
            'nama_pengguna' => 'akun_uji',
            'kata_sandi_hash' => 'rahasia123',
            'id_peran' => Peran::create(['nama_peran' => 'Admin'])->id_peran,
            'id_bidang' => Bidang::create(['nama_bidang' => 'Sekretariat'])->id_bidang,
        ];
    }

    public function test_table_pengguna_punya_kolom_akun(): void
    {
        foreach (['nama_lengkap', 'nama_pengguna', 'status', 'perlu_ganti_kata_sandi'] as $kolom){
            $this->assertTrue(Schema::hasColumn('pengguna', $kolom), "Kolom $kolom tidak ada");
        }
    }

    public function test_nama_pengguna_harus_unik(): void
    {
        $data = $this->dataAkun();
        Pengguna::create($data);

        $this->expectException(QueryException::class);
        Pengguna::create($data);
    }

    public function test_nilai_default_status_dan_penanda_ganti_sandi(): void
    {
        $akun = Pengguna::create($this->dataAkun())->fresh();

        $this->assertSame('aktif', $akun->status);
        $this->assertFalse($akun->perlu_ganti_kata_sandi);
    }
}
