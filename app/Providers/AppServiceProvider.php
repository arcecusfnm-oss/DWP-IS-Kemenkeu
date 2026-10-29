<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }


    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $username = Str::lower(
                trim((string) $request->input('username'))
            );

            $makeResponse = function (string $key) {
                return function (
                    Request $request,
                    array $headers
                ) use ($key) {
                    $seconds = max(
                        1,
                        RateLimiter::availableIn($key)
                    );

                    $request->session()->put(
                        'login_throttle_until',
                        now()->addSeconds($seconds)->timestamp
                    );

                    return redirect()
                        ->route('login')
                        ->withErrors([
                            'throttle' =>
                            'Terlalu banyak percobaan masuk. Silakan coba lagi dalam 5 menit.',
                        ])
                        ->withInput(
                            $request->only('username')
                        );
                };
            };

            $usernameKey = 'login|'
                . $username
                . '|'
                . $request->ip();

            $ipKey = 'login-ip|' . $request->ip();

            return [
                Limit::perMinutes(5, 5)
                    ->by($usernameKey)
                    ->response($makeResponse($usernameKey)),

                Limit::perMinutes(30, 30)
                    ->by($ipKey)
                    ->response($makeResponse($ipKey)),
            ];
        });
    }
}
