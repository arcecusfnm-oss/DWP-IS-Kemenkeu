<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('id_pengguna');
            $table->string('nama_lengkap');
            $table->string('nama_pengguna')->unique();
            $table->string('kata_sandi_hash');
            $table->string('status')->default('aktif');
            $table->boolean('perlu_ganti_kata_sandi')->default(false);
            $table->timestamp('dihapus_pada')->nullable();
            $table->foreignId('id_peran')->constrained('peran', 'id_peran');
            $table->foreignId('id_bidang')->constrained('bidang', 'id_bidang');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};
