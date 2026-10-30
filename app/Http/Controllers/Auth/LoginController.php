<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pengguna;
use App\Repositories\LogLoginRepository;
use App\Repositories\PenggunaRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Cache;
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

        $username = mb_strtolower(trim($credentials['username']));
        $ip = $request->ip();

        // Key berdasarkan kombinasi username dan IP.
        $key = 'login|' . $username . '|' . $ip;
        $lockKey = $key . '|locked';

        // Periksa apakah akun sedang dalam masa lockout.
        $lockedUntil = Cache::get($lockKey);

        if ($lockedUntil !== null) {
            $remaining = max(0, (int) $lockedUntil - now()->timestamp);

            if ($remaining > 0) {
                $request->session()->put(
                    'login_throttle_until',
                    (int) $lockedUntil
                );

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'throttle' =>
                        'Terlalu banyak percobaan masuk. Silakan coba lagi dalam 5 menit.',
                    ])
                    ->withInput($request->only('username'));
            }

            Cache::forget($lockKey);
            Cache::forget($key);
        }

        $pengguna = $this->penggunaRepository
            ->findByNamaPengguna($credentials['username']);

        // Periksa password terlebih dahulu.
        if (
            !$pengguna ||
            !Hash::check(
                $credentials['password'],
                $pengguna->kata_sandi_hash
            )
        ) {
            $this->catatLog(
                $request,
                $pengguna,
                $credentials['username'],
                false,
                LogLoginRepository::ALASAN_KREDENSIAL_SALAH
            );

            // Tambahkan hitungan kegagalan.
            Cache::add($key, 0, now()->addMinutes(5));

            $attempts = Cache::increment($key);

            // Kegagalan kelima langsung mengaktifkan lockout.
            if ($attempts >= 5) {
                $lockedUntil = now()->addMinutes(5)->timestamp;

                Cache::put(
                    $lockKey,
                    $lockedUntil,
                    now()->addMinutes(5)
                );

                $request->session()->put(
                    'login_throttle_until',
                    $lockedUntil
                );

                return redirect()
                    ->route('login')
                    ->withErrors([
                        'throttle' =>
                        'Terlalu banyak percobaan masuk. Silakan coba lagi dalam 5 menit.',
                    ])
                    ->withInput($request->only('username'));
            }

            throw ValidationException::withMessages([
                'login' => 'Nama pengguna atau kata sandi salah.',
            ]);
        }

        // Password benar, baru periksa status akun.
        if ($pengguna->status !== 'aktif') {
            $this->catatLog(
                $request,
                $pengguna,
                $credentials['username'],
                false,
                LogLoginRepository::ALASAN_AKUN_NONAKTIF
            );

            throw ValidationException::withMessages([
                'login' => 'Akun tidak aktif. Hubungi Admin Sekretariat.',
            ]);
        }

        // Login berhasil: hapus hitungan kegagalan.
        Cache::forget($key);
        Cache::forget($lockKey);
        $request->session()->forget('login_throttle_until');

        Auth::login($pengguna);
        $request->session()->regenerate();

        $this->catatLog(
            $request,
            $pengguna,
            $credentials['username'],
            true,
            null
        );

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

        Cookie::queue(
            Cookie::forget(config('session.cookie'))
        );

        return redirect()->route('login');
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
