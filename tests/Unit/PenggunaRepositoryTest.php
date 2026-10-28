<?php

namespace Tests\Unit;

use App\Models\Pengguna;
use App\Repositories\PenggunaRepository;
use Mockery;
use Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

class PenggunaRepositoryTest extends TestCase
{
    public function test_find_by_nama_pengguna_uses_the_correct_column(): void
    {
        $pengguna = new Pengguna();

        $model = Mockery::mock(Pengguna::class);
        $query = Mockery::mock();

        $model->shouldReceive('newQuery')
            ->once()
            ->andReturn($query);

        $query->shouldReceive('where')
            ->once()
            ->with('nama_pengguna', 'admin')
            ->andReturnSelf();

        $query->shouldReceive('first')
            ->once()
            ->andReturn($pengguna);

        $repository = new PenggunaRepository($model);

        $result = $repository->findByNamaPengguna('admin');

        $this->assertSame($pengguna, $result);
    }

    public function test_find_by_id_uses_the_model_primary_key(): void
    {
        $pengguna = new Pengguna();

        $model = Mockery::mock(Pengguna::class);
        $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);

        $model->shouldReceive('newQuery')
            ->once()
            ->andReturn($query);

        $query->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($pengguna);

        $repository = new PenggunaRepository($model);

        $result = $repository->findById(10);

        $this->assertSame($pengguna, $result);
    }


    public function test_get_aktif_filters_users_by_active_status(): void
    {
        $penggunaAktif = new Collection([
            new Pengguna(),
        ]);

        $model = Mockery::mock(Pengguna::class);
        $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);

        $model->shouldReceive('newQuery')
            ->once()
            ->andReturn($query);

        $query->shouldReceive('where')
            ->once()
            ->with('status', 'aktif')
            ->andReturnSelf();

        $query->shouldReceive('get')
            ->once()
            ->andReturn($penggunaAktif);

        $repository = new PenggunaRepository($model);

        $result = $repository->getAktif();

        $this->assertSame($penggunaAktif, $result);
    }
}
