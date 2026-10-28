<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KoneksiDatabaseTest extends TestCase
{
    public function test_test_memakai_database_uji(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('dwp_is_kemenkeu_test', DB::connection()->getDatabaseName());
    }
}