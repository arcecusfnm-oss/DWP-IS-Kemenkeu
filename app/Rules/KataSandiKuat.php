<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class KataSandiKuat implements ValidationRule
{
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
        if (!preg_match('/[' . preg_quote(self::KARAKTER_KHUSUS, '/') . ']/', $value)) {
            $kurang[] = 'karakter khusus (' . implode(' ', str_split(self::KARAKTER_KHUSUS)) . ')';
        }

        if ($kurang) {
            $fail('Kata sandi baru harus memuat ' . implode(', ', $kurang) . '.');
        }
    }
}