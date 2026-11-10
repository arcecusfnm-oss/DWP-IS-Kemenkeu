<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class KataSandiKuat implements ValidationRule
{
    /**
     * Contoh karakter khusus yang ditampilkan ke pengguna dan dipakai
     * saat membuat kata sandi sementara. Untuk validasi, SEMUA karakter
     * yang bukan huruf, angka, atau spasi dianggap karakter khusus.
     */
    public const KARAKTER_KHUSUS = '!@#$%^&*';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;
        $kurang = [];

        if (!preg_match('/\p{Lu}/u', $value)) {
            $kurang[] = 'huruf besar';
        }
        if (!preg_match('/\p{Ll}/u', $value)) {
            $kurang[] = 'huruf kecil';
        }
        if (!preg_match('/[0-9]/', $value)) {
            $kurang[] = 'angka';
        }
        if (!preg_match('/[^\p{L}\p{N}\s]/u', $value)) {
            $kurang[] = 'karakter khusus (misalnya ' . implode(' ', str_split(self::KARAKTER_KHUSUS)) . ')';
        }

        if ($kurang) {
            $fail('Kata sandi harus memuat ' . implode(', ', $kurang) . '.');
        }
    }
}