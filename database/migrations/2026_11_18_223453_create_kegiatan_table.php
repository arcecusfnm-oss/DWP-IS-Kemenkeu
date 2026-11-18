
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
            $table->date('tanggal');
            $table->text('deskripsi');

            $table->timestamps();

            // Relasi kegiatan ke program kerja
            $table->foreign('id_program_kerja')
                ->references('id_program_kerja')
                ->on('program_kerja')
                ->restrictOnDelete();

            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};
