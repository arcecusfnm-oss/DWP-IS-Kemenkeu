<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel surat masuk (B-035, T-58), mengikuti ERD revisi (SURAT_MASUK).
     * Kolom wajib di ERD: nomor_agenda, nomor_surat, asal_surat, tanggal_surat,
     * tanggal_diterima, perihal, dibuat_oleh.
     */
    public function up(): void
    {
        Schema::create('surat_masuk', function (Blueprint $table) {
            $table->id('id_surat_masuk');

            // Format No. Urut/Bulan (romawi)/Tahun, contoh 1/I/2026. Unik (UK di ERD).
            $table->string('nomor_agenda', 50)->unique();

            // Nomor surat dari pengirim. Unik sesuai AC 3 B-035 ("Nomor surat sudah tercatat").
            $table->string('nomor_surat', 100)->unique();
            $table->string('asal_surat');
            $table->date('tanggal_surat');
            $table->date('tanggal_diterima');

            // Berkas lampiran disimpan di server; kolom ini hanya menyimpan lokasinya.
            $table->string('lampiran')->nullable();

            $table->string('perihal');

            // Disposisi pimpinan (opsional).
            $table->text('disposisi')->nullable();
            $table->date('tanggal_disposisi')->nullable();
            $table->text('keterangan')->nullable();

            // Admin atau anggota Sekretariat yang mencatat (ERD: dibuat_oleh wajib).
            $table->foreignId('dibuat_oleh')->constrained('pengguna', 'id_pengguna');

            $table->timestamps();

            // Untuk filter rentang tanggal pada pencarian (T-74).
            $table->index('tanggal_surat');
            $table->index('tanggal_diterima');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surat_masuk');
    }
};
