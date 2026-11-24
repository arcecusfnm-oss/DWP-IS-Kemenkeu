<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B-018 T-003: slot jabatan tingkat DWP (Ketua DWP, Wakil Ketua DWP).
     * Satu baris = satu slot. Jumlah Wakil Ketua fleksibel: tambah baris saja.
     * id_anggota boleh kosong (jabatan belum terisi).
     */
    public function up(): void
    {
        Schema::create('jabatan_dwp', function (Blueprint $table) {
            $table->id('id_jabatan');
            $table->string('nama_jabatan', 100)->unique();
            $table->enum('jenis', ['ketua', 'wakil_ketua']);
            $table->unsignedSmallInteger('urutan')->default(0);

            $table->foreignId('id_anggota')
                ->nullable()
                ->unique()
                ->constrained('anggota', 'id_anggota')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['jenis', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatan_dwp');
    }
};