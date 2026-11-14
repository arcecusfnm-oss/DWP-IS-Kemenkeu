<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-80: tabel program_kerja (sesuai ERD B-016 / T-22).
 *
 * Relasi:
 *  - bidang (1) -> program_kerja (N)    lewat id_bidang
 *  - pengguna (1) -> program_kerja (N)  lewat dibuat_oleh
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_kerja', function (Blueprint $table) {
            $table->id('id_program_kerja');

            $table->unsignedBigInteger('id_bidang');
            $table->string('nama_program', 255);
            $table->text('tujuan');
            $table->string('sasaran_peserta', 255)->nullable();
            $table->string('metode', 255)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('tempat', 255)->nullable();
            $table->decimal('anggaran', 15, 2)->nullable();
            $table->text('keterangan')->nullable();

            $table->unsignedBigInteger('dibuat_oleh');

            $table->timestamps();

            // Bidang atau akun pembuat tidak boleh terhapus selama masih punya program kerja
            $table->foreign('id_bidang')
                ->references('id_bidang')->on('bidang')
                ->restrictOnDelete();

            $table->foreign('dibuat_oleh')
                ->references('id_pengguna')->on('pengguna')
                ->restrictOnDelete();

            $table->index('tanggal_mulai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_kerja');
    }
};