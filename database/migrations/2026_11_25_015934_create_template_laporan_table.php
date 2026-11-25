<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B-052 (T-130): template laporan kegiatan tunggal untuk semua bidang,
 * mengikuti DESAIN-SPRINT3 bagian 3.5 (tabel singleton dengan kolom label_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_laporan', function (Blueprint $table) {
            $table->id('id_template_laporan');
            $table->string('label_judul_kegiatan', 255);
            $table->string('label_tanggal_kegiatan', 255);
            $table->string('label_penjelasan_singkat', 255);
            $table->string('label_dokumentasi', 255);
            $table->unsignedBigInteger('diubah_oleh')->nullable();
            $table->timestamps();

            $table->foreign('diubah_oleh')
                ->references('id_pengguna')->on('pengguna')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_laporan');
    }
};