<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B-053 (T-153): laporan kegiatan bidang.
 * Isian disimpan di tabel ini (bukan merujuk kolom templat) agar perubahan
 * templat B-052 tidak mengubah laporan lama (AC 5 B-052).
 * Status: Draf, Terkirim, Dikembalikan, Diperiksa (divalidasi di aplikasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_kegiatan', function (Blueprint $table) {
            $table->id('id_laporan_kegiatan');
            $table->unsignedBigInteger('id_kegiatan');
            $table->unsignedBigInteger('id_bidang');
            $table->string('judul_kegiatan', 255);
            $table->date('tanggal_kegiatan');
            $table->text('penjelasan_singkat');
            $table->string('status', 20)->default('Draf');
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->timestamps();

            $table->foreign('id_kegiatan')
                ->references('id_kegiatan')->on('kegiatan');
            $table->foreign('id_bidang')
                ->references('id_bidang')->on('bidang');
            $table->foreign('dibuat_oleh')
                ->references('id_pengguna')->on('pengguna')
                ->nullOnDelete();

            $table->index(['id_bidang', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_kegiatan');
    }
};