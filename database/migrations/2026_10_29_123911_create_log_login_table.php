<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_login', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pengguna_id')->nullable();
            $table->string('nama_pengguna', 100)->nullable();
            $table->boolean('berhasil');
            $table->string('alasan', 50)->nullable();
            $table->string('alamat_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('dicatat_pada')->useCurrent();

            $table->index('nama_pengguna');
            $table->index('dicatat_pada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_login');
    }
};