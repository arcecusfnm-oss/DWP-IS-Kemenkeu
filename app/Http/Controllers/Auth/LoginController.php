<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Repositories\LogLoginRepository;
use App\Repositories\PenggunaRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(
        private readonly PenggunaRepository $penggunaRepository,
        private readonly LogLoginRepository $logLoginRepository // T-13
    ) {}

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:100'],
        ]);

        $pengguna = $this->penggunaRepository
            ->findByNamaPengguna($credentials['username']);

        if ($pengguna && $pengguna->status !== 'aktif') {
            $this->catatLog(
                $request,
                $pengguna,
                $credentials['username'],
                false,
                LogLoginRepository::ALASAN_AKUN_NONAKTIF
            );

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
            // T-13
            $this->catatLog(
                $request,
                $pengguna,
                $credentials['username'],
                false,
                LogLoginRepository::ALASAN_KREDENSIAL_SALAH
            );

            throw ValidationException::withMessages([
                'login' =>
                'Nama pengguna atau kata sandi salah.',
            ]);
        }

        Auth::login($pengguna);
        $request->session()->regenerate();

        $this->catatLog($request, $pengguna, $credentials['username'], true, null);

        if ($pengguna->peran->nama_peran === 'Admin') {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('beranda');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withCookie(cookie()->forget(config('session.cookie')));
    }

    /**
     * Catat log login. Kegagalan mencatat tidak boleh
     * menggagalkan proses login itu sendiri.
     */
    private function catatLog(
        Request $request,
        ?Pengguna $pengguna,
        string $namaPengguna,
        bool $berhasil,
        ?string $alasan
    ): void {
        try {
            $this->logLoginRepository->catat(
                $pengguna?->getKey(),
                $namaPengguna,
                $berhasil,
                $alasan,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
