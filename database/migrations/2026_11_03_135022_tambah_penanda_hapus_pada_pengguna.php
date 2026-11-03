<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            if (! Schema::hasColumn('pengguna', 'dihapus_pada')) {
                $table->timestamp('dihapus_pada')->nullable();
            }
            $table->foreignId('dihapus_oleh')
                ->nullable()
                ->constrained('pengguna', 'id_pengguna');
            $table->index('dihapus_pada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->dropForeign(['dihapus_oleh']);
            $table->dropIndex(['dihapus_pada']);
            $table->dropColumn('dihapus_oleh');
        });
    }
};