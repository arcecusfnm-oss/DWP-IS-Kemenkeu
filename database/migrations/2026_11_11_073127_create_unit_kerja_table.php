<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel unit_kerja (ERD T-22). Dibuat sebelum tabel anggota
     * karena anggota.id_unit_kerja merujuk ke tabel ini.
     */
    public function up(): void
    {
        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->id('id_unit_kerja');
            $table->string('nama_unit_kerja')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerja');
    }
};