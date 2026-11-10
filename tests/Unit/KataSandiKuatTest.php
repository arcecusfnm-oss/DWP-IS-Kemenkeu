<?php

namespace Tests\Unit;

use App\Rules\KataSandiKuat;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KataSandiKuatTest extends TestCase
{
    private function lolos(string $kata): bool
    {
        return Validator::make(
            ['kata_sandi_baru' => $kata],
            ['kata_sandi_baru' => ['required', 'min:8', new KataSandiKuat()]]
        )->passes();
    }

    public static function kataSandiDitolak(): array
    {
        return [
            'kurang dari 8 karakter' => ['Abc1!ef'],
            'tanpa huruf besar'      => ['abcdef1!'],
            'tanpa huruf kecil'      => ['ABCDEF1!'],
            'tanpa angka'            => ['Abcdefg!'],
            'tanpa karakter khusus'  => ['Abcdefg1'],
        ];
    }

    #[DataProvider('kataSandiDitolak')]
    public function test_kata_sandi_yang_melanggar_aturan_ditolak(string $kata): void
    {
        $this->assertFalse($this->lolos($kata));
    }

    public function test_kata_sandi_yang_memenuhi_semua_aturan_diterima(): void
    {
        $this->assertTrue($this->lolos('Abcdef1!'));
    }

    public function test_pesan_error_menyebut_aturan_yang_belum_terpenuhi(): void
    {
        $v = Validator::make(
            ['kata_sandi_baru' => 'abcdefgh'],
            ['kata_sandi_baru' => [new KataSandiKuat()]]
        );

        $this->assertTrue($v->fails());
        $pesan = $v->errors()->first('kata_sandi_baru');
        $this->assertStringContainsString('huruf besar', $pesan);
        $this->assertStringContainsString('angka', $pesan);
        $this->assertStringContainsString('karakter khusus', $pesan);
    }
}