<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id('id_kegiatan');

            $table->unsignedBigInteger('id_program_kerja');
            $table->string('nama_kegiatan', 255);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('deskripsi');
            $table->unsignedBigInteger('dibuat_oleh');
            $table->timestamp('dihapus_pada')->nullable();
            $table->index('dihapus_pada');

            $table->timestamps();

            // Relasi kegiatan ke program kerja
            $table->foreign('id_program_kerja')
                ->references('id_program_kerja')
                ->on('program_kerja')
                ->restrictOnDelete();

            // Pembuat kegiatan
            $table->foreign('dibuat_oleh')
                ->references('id_pengguna')
                ->on('pengguna')
                ->restrictOnDelete();

            $table->index('tanggal_mulai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};