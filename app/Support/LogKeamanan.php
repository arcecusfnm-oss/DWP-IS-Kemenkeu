<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogKeamanan
{
        private const KUNCI_TERLARANG = [
        'password', 'kata_sandi', 'sandi', 'token',
        'cookie', 'session', 'sesi', 'authorization', 'secret',
        'email', 'username', 'nama_pengguna',
    ];

    /** ID acak stabil untuk pengguna; null jika pengguna tidak dikenal. */
    public static function idAnonim(?int $idPengguna): ?string
    {
        if ($idPengguna === null) {
            return null;
        }

        return 'usr_' . substr(
            hash_hmac('sha256', (string) $idPengguna, (string) config('app.key')),
            0,
            16
        );
    }

    /** Catat event keamanan: waktu, pengguna (ID acak), IP, aksi, hasil. */
    public static function catat(
        string $aksi,
        string $hasil,
        ?Request $request = null,
        ?int $idPengguna = null,
        array $tambahan = []
    ): void {
        try {
            $request ??= request();

            $konteks = [
                'waktu'    => now()->toIso8601String(),
                'pengguna' => self::idAnonim($idPengguna),
                'ip'       => $request->ip(),
                'aksi'     => $aksi,
                'hasil'    => $hasil,
            ] + self::bersihkan($tambahan);

            $hasil === 'berhasil'
                ? Log::info('keamanan', $konteks)
                : Log::warning('keamanan', $konteks);
        } catch (\Throwable) {
            // Gagal mencatat tidak boleh mengganggu request.
        }
    }

    private static function bersihkan(array $data): array
    {
        foreach ($data as $kunci => $nilai) {
            foreach (self::KUNCI_TERLARANG as $kata) {
                if (stripos((string) $kunci, $kata) !== false) {
                    unset($data[$kunci]);
                    continue 2;
                }
            }
            if (is_array($nilai)) {
                $data[$kunci] = self::bersihkan($nilai);
            }
        }

        return $data;
    }
}