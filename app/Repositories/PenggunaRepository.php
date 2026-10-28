<?php

namespace App\Repositories;

use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class PenggunaRepository
{
    public function __construct(
        private readonly Pengguna $model
    ) {
    }

    /**
     * Cari pengguna berdasarkan nama pengguna untuk proses login.
     */
    public function findByNamaPengguna(string $namaPengguna): ?Pengguna
    {
        return $this->model
            ->newQuery()
            ->where('nama_pengguna', $namaPengguna)
            ->first();
    }

    /**
     * Ambil pengguna berdasarkan ID.
     */
    public function findById(int $id): ?Pengguna
    {
        return $this->model->newQuery()->find($id);
    }

    /**
     * Ambil daftar pengguna aktif.
     */
    public function getAktif(): Collection
    {
        return $this->model
            ->newQuery()
            ->where('status', 'aktif')
            ->get();
    }

    /**
     * Buat query dasar untuk kebutuhan pencarian lanjutan.
     */
    public function query(): Builder
    {
        return $this->model->newQuery();
    }
}
