<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel surat keputusan (B-035, T-58), mengikuti ERD revisi (SURAT_KEPUTUSAN).
     * Kolom wajib di ERD: nomor_sk, tanggal, perihal, status_penetapan, dibuat_oleh.
     */
    public function up(): void
    {
        Schema::create('surat_keputusan', function (Blueprint $table) {
            $table->id('id_surat_keputusan');

            // Unik, sama seperti nomor surat keluar. ERD belum menandai UK di kolom ini.
            $table->string('nomor_sk', 100)->unique();

            $table->date('tanggal');
            $table->string('perihal');

            // ERD: Sudah / Belum / Batal.
            $table->enum('status_penetapan', ['sudah', 'belum', 'batal'])->default('belum');

            // Admin atau anggota Sekretariat yang mencatat (ERD: dibuat_oleh wajib).
            $table->foreignId('dibuat_oleh')->constrained('pengguna', 'id_pengguna');

            $table->timestamps();

            // Untuk filter rentang tanggal pada pencarian.
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_keputusan');
    }
};
