<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Repositories\PenggunaRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(
        private readonly PenggunaRepository $penggunaRepository
    ) {
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:100'],
        ]);

        $pengguna = $this->penggunaRepository
            ->findByNamaPengguna($credentials['username']);

        if ($pengguna && $pengguna->status !== 'aktif') {
            throw ValidationException::withMessages([
                'login' =>
                    'Akun tidak aktif. Hubungi Admin Sekretariat.',
            ]);
        }

        if (
            !$pengguna ||
            !Hash::check(
                $credentials['password'],
                $pengguna->kata_sandi_hash
            )
        ) {
            throw ValidationException::withMessages([
                'login' =>
                    'Nama pengguna atau kata sandi salah.',
            ]);
        }

        $request->session()->forget('login_throttle_until');

        Auth::login($pengguna);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
