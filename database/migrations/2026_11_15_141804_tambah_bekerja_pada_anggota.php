<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom "Bekerja (Ya/Tidak)" sesuai Template Database Pengurus DWP Kemenkeu.
     */
    public function up(): void
    {
        Schema::table('anggota', function (Blueprint $table) {
            $table->string('bekerja', 5)->nullable()->after('bidang_usaha');
        });
    }

    public function down(): void
    {
        Schema::table('anggota', function (Blueprint $table) {
            $table->dropColumn('bekerja');
        });
    }
};