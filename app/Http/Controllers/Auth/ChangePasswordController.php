<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        // Tolak request yang mencoba mengirim ID akun lain.
        if (
            $request->exists('id_pengguna') ||
            $request->exists('user_id')
        ) {
            throw ValidationException::withMessages([
                'kata_sandi_baru' =>
                    'Permintaan tidak valid. Akun yang diubah harus akun sendiri.',
            ]);
        }

        // Validasi field kata sandi.
        $validated = $request->validate([
            'kata_sandi_lama' => ['required', 'string'],
            'kata_sandi_baru' => ['required', 'string', 'min:8'],
        ], [
            'kata_sandi_lama.required' => 'Kata sandi lama salah',
            'kata_sandi_baru.required' => 'Kata sandi baru wajib diisi.',
            'kata_sandi_baru.min' => 'Kata sandi baru minimal 8 karakter.',
        ]);

        // Ambil pengguna yang sedang login.
        $pengguna = $request->user();

        // Periksa kata sandi lama.
        if (
            !Hash::check(
                $validated['kata_sandi_lama'],
                $pengguna->kata_sandi_hash
            )
        ) {
            throw ValidationException::withMessages([
                'kata_sandi_lama' => 'Kata sandi lama salah',
            ]);
        }

        // Simpan kata sandi baru.
        // Model Pengguna otomatis melakukan hashing melalui cast hashed.
        $pengguna->kata_sandi_hash = $validated['kata_sandi_baru'];
        $pengguna->save();

        return back()->with(
            'success',
            'Kata sandi berhasil diubah!'
        );
    }
}
