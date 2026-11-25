
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\WajibGantiKataSandi::class,
        ]);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $e): bool =>
                $request->is('api/*') || $request->expectsJson()
        );

        // Jangan perlakukan validasi, autentikasi, dan HTTP 4xx
        // sebagai kesalahan server.
        $bukanErrorServer = function (\Throwable $e): bool {
            return $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || ($e instanceof HttpExceptionInterface
                    && $e->getStatusCode() < 500);
        };

        // Catat kesalahan server.
        $exceptions->report(function (\Throwable $e) use ($bukanErrorServer): ?bool {
            if ($bukanErrorServer($e)) {
                return null;
            }

            $incidentId = (string) Str::uuid();

            request()->attributes->set('incident_id', $incidentId);

            Log::error('Terjadi kesalahan pada aplikasi.', [
                'incident_id' => $incidentId,
                'exception' => get_class($e),
                'code' => $e->getCode(),
            ]);

            // Mencegah pencatatan otomatis kedua kali.
            return false;
        });

                // Catat akses ditolak (403) secara terpusat.
        $exceptions->render(function (
            HttpExceptionInterface $e,
            Request $request
        ) {
            if ($e->getStatusCode() === 403) {
                $idPengguna = $request->user()?->getKey();

                \App\Support\LogKeamanan::catat(
                    'akses_ditolak',
                    'ditolak',
                    $request,
                    $idPengguna !== null ? (int) $idPengguna : null,
                    ['rute' => $request->path(), 'metode' => $request->method()]
                );
            }

            return null; // biar render bawaan tetap jalan
        });

        // Tampilkan pesan umum hanya untuk kesalahan server.
        $exceptions->render(function (
            \Throwable $e,
            Request $request
        ) use ($bukanErrorServer) {
            if ($bukanErrorServer($e)) {
                return null;
            }

            $incidentId = $request->attributes->get('incident_id');

            if (!is_string($incidentId)) {
                $incidentId = (string) Str::uuid();

                Log::error('Terjadi kesalahan pada aplikasi.', [
                    'incident_id' => $incidentId,
                    'exception' => get_class($e),
                    'code' => $e->getCode(),
                ]);
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Terjadi kesalahan pada sistem. Silakan coba kembali.',
                    'incident_id' => $incidentId,
                ], $status);
            }

            return response()->view('errors.500', [
                'incidentId' => $incidentId,
            ], $status);
        });
    })->create();
