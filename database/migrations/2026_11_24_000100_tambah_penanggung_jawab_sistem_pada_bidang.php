<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-008 (B-024): penanggung jawab sistem pada tabel bidang.
 *
 * Satu kolom = satu penanggung jawab per bidang. Penggantian cukup menimpa
 * nilai lama (T-009). Kolom boleh kosong karena bidang yang sudah ada belum
 * punya penanggung jawab. Pembatasan "harus anggota bidang tersebut" ada di
 * endpoint, bukan di skema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidang', function (Blueprint $table) {
            $table->unsignedBigInteger('id_penanggung_jawab_sistem')
                ->nullable()
                ->after('nama_bidang');

            // Kalau akun terhapus permanen, bidang kembali tanpa penanggung jawab
            $table->foreign('id_penanggung_jawab_sistem')
                ->references('id_pengguna')->on('pengguna')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bidang', function (Blueprint $table) {
            $table->dropForeign(['id_penanggung_jawab_sistem']);
            $table->dropColumn('id_penanggung_jawab_sistem');
        });
    }
};