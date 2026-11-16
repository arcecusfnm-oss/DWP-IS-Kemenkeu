<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel surat keluar (B-035/B-036, T-58), mengikuti ERD revisi (SURAT_KELUAR).
     * Kolom wajib di ERD: nomor_surat, tanggal, tujuan, perihal, status_terkirim, dibuat_oleh.
     */
    public function up(): void
    {
        Schema::create('surat_keluar', function (Blueprint $table) {
            $table->id('id_surat_keluar');

            // Unik sesuai T-64 ("nomor surat unik"). ERD belum menandai UK di kolom ini.
            $table->string('nomor_surat', 100)->unique();

            // Bidang terkait boleh kosong (ERD: 0..1 : N).
            $table->foreignId('id_bidang_terkait')
                ->nullable()
                ->constrained('bidang', 'id_bidang');

            $table->date('tanggal');
            $table->string('tujuan');
            $table->string('perihal');

            // ERD: Sudah / Belum / Batal.
            $table->enum('status_terkirim', ['sudah', 'belum', 'batal'])->default('belum');

            $table->text('keterangan')->nullable();

            // Admin atau anggota Sekretariat yang mencatat (ERD: dibuat_oleh wajib).
            $table->foreignId('dibuat_oleh')->constrained('pengguna', 'id_pengguna');

            $table->timestamps();

            // Untuk filter rentang tanggal pada pencarian (T-74).
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_keluar');
    }
};
