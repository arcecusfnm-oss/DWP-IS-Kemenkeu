<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-099 (B-051): kolom persetujuan pada program_kerja.
 * Dipakai bersama B-123 (revisi) dan B-124 (status bidang).
 *
 * Kolom "status" sudah ada (Diajukan / Disetujui / Perlu Revisi), jadi tidak
 * dibuat lagi. Data lama tetap berstatus Diajukan, tidak perlu backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_kerja', function (Blueprint $table) {
            $table->dateTime('tanggal_persetujuan')->nullable()->after('status');
            $table->unsignedBigInteger('disetujui_oleh')->nullable()->after('tanggal_persetujuan');
            $table->text('catatan_revisi')->nullable()->after('disetujui_oleh');

            // Penyetuju tidak boleh terhapus selama masih tercatat di program kerja
            $table->foreign('disetujui_oleh')
                ->references('id_pengguna')->on('pengguna')
                ->restrictOnDelete();

            // Antrean persetujuan memfilter status Diajukan
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('program_kerja', function (Blueprint $table) {
            $table->dropForeign(['disetujui_oleh']);
            $table->dropIndex(['status']);
            $table->dropColumn(['tanggal_persetujuan', 'disetujui_oleh', 'catatan_revisi']);
        });
    }
};