<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $pengguna = $request->user();

        if (! $pengguna) {
            abort(401, 'Silakan masuk terlebih dahulu.');
        }

        $namaPeran = $pengguna->peran?->nama_peran;

        if (! $namaPeran || ! in_array($namaPeran, $roles, true)) {
            abort(403, 'Anda tidak memiliki hak akses.');
        }

        return $next($request);
    }
}
