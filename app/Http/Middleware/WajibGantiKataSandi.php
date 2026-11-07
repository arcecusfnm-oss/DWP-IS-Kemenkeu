<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WajibGantiKataSandi
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if ($pengguna
            && $pengguna->perlu_ganti_kata_sandi
            && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}