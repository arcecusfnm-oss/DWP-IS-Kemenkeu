<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B-052 (T-130): templat laporan kegiatan tunggal untuk semua bidang.
 * Kolom templat: Judul Kegiatan, Tanggal Kegiatan, Penjelasan Singkat, Dokumentasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templat_laporan', function (Blueprint $table) {
            $table->id('id_templat_laporan');
            $table->string('nama_templat', 100);
            $table->boolean('aktif')->default(true);
            $table->unsignedBigInteger('diubah_oleh')->nullable();
            $table->timestamps();

            $table->foreign('diubah_oleh')
                ->references('id_pengguna')->on('pengguna')
                ->nullOnDelete();
        });

        Schema::create('kolom_templat_laporan', function (Blueprint $table) {
            $table->id('id_kolom_templat');
            $table->unsignedBigInteger('id_templat_laporan');
            $table->string('kunci', 30);
            $table->string('label', 100);
            $table->boolean('wajib')->default(true);
            $table->unsignedTinyInteger('urutan');
            $table->timestamps();

            $table->foreign('id_templat_laporan')
                ->references('id_templat_laporan')->on('templat_laporan')
                ->cascadeOnDelete();
            $table->unique(['id_templat_laporan', 'kunci']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolom_templat_laporan');
        Schema::dropIfExists('templat_laporan');
    }
};