<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akses fitur persuratan: Admin, atau akun dengan bidang Sekretariat
 * (AC B-035 no. 5 dan B-039 no. 5, asumsi tim).
 *
 * Bidang dicek dari kolom pengguna.id_bidang dan dari pivot pengguna_bidang
 * (peran penanggung jawab bisa memegang beberapa bidang).
 * Dipakai setelah middleware 'auth'. Bisa dipakai ulang untuk T-59, T-64, dan T-69.
 */
class AksesPersuratan
{
    private const PERAN_ADMIN = 'Admin';

    private const BIDANG_SEKRETARIAT = 'Sekretariat';

    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if (! $pengguna) {
            abort(401, 'Silakan masuk terlebih dahulu.');
        }

        if ($pengguna->peran?->nama_peran === self::PERAN_ADMIN) {
            return $next($request);
        }

        if ($this->anggotaSekretariat((int) $pengguna->getAuthIdentifier())) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki hak akses.');
    }

    private function anggotaSekretariat(int $idPengguna): bool
    {
        $idBidang = DB::table('bidang')
            ->where('nama_bidang', self::BIDANG_SEKRETARIAT)
            ->value('id_bidang');

        if ($idBidang === null) {
            return false;
        }

        $langsung = DB::table('pengguna')
            ->where('id_pengguna', $idPengguna)
            ->where('id_bidang', $idBidang)
            ->exists();

        if ($langsung) {
            return true;
        }

        return DB::table('pengguna_bidang')
            ->where('id_pengguna', $idPengguna)
            ->where('id_bidang', $idBidang)
            ->exists();
    }
}