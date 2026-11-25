<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B-040 (T-067): relasi surat masuk ke program kerja.
 * Kolom opsional: surat tanpa tautan tetap valid. Program kerja memakai hapus lunak (B-047),
 * jadi baris program tidak hilang dan tautan tetap terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_masuk', function (Blueprint $table) {
            $table->unsignedBigInteger('id_program_kerja')->nullable()->after('keterangan');

            $table->foreign('id_program_kerja')
                ->references('id_program_kerja')->on('program_kerja')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('surat_masuk', function (Blueprint $table) {
            $table->dropForeign(['id_program_kerja']);
            $table->dropColumn('id_program_kerja');
        });
    }
};