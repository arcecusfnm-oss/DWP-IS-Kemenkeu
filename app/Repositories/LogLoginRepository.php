<?php

namespace App\Repositories;

use App\Models\LogLogin;
use Illuminate\Support\Str;

class LogLoginRepository
{
    public const ALASAN_KREDENSIAL_SALAH = 'kredensial_salah';
    public const ALASAN_AKUN_NONAKTIF = 'akun_nonaktif';
    public const ALASAN_TERKUNCI = 'terkunci';

    public function __construct(
        private readonly LogLogin $model
    ) {
    }

    /**
     * Catat satu percobaan login.
     * Sengaja tidak menerima kata sandi, hash, token, atau sesi.
     */
    public function catat(
        ?int $penggunaId,
        ?string $namaPengguna,
        bool $berhasil,
        ?string $alasan,
        ?string $alamatIp,
        ?string $userAgent
    ): void {
        $this->model->newQuery()->create([
            'pengguna_id' => $penggunaId,
            'nama_pengguna' => $namaPengguna !== null
                ? Str::limit($namaPengguna, 100, '')
                : null,
            'berhasil' => $berhasil,
            'alasan' => $alasan,
            'alamat_ip' => $alamatIp,
            'user_agent' => $userAgent !== null
                ? Str::limit($userAgent, 255, '')
                : null,
        ]);
    }
}