<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggota', function (Blueprint $table) {
            // null = tidak punya jabatan; isi: 'ketua' atau 'wakil_ketua'
            $table->string('jabatan_bidang', 20)->nullable()->after('id_bidang');
        });
    }

    public function down(): void
    {
        Schema::table('anggota', function (Blueprint $table) {
            $table->dropColumn('jabatan_bidang');
        });
    }
};