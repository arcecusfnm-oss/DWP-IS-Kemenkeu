<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna_bidang', function (Blueprint $t) {
            $t->unsignedBigInteger('id_pengguna');
            $t->unsignedBigInteger('id_bidang');
            $t->primary(['id_pengguna', 'id_bidang']);
            $t->foreign('id_pengguna')->references('id_pengguna')->on('pengguna')->cascadeOnDelete();
            $t->foreign('id_bidang')->references('id_bidang')->on('bidang')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengguna_bidang');
    }
};