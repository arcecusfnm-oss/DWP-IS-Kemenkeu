<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel anggota (B-016, T-24), mengikuti ERD T-22 lengkap.
     * Kolom wajib di ERD: nama_lengkap, nomor_telepon, status, dibuat_oleh.
     * Kolom jabatan (ketua / wakil ketua) TIDAK di sini: dibuat di T-31.
     */
    public function up(): void
    {
        Schema::create('anggota', function (Blueprint $table) {
            $table->id('id_anggota');

            // Akun terkait: relasi 0..1 : 0..1, satu akun hanya dipakai satu anggota.
            $table->foreignId('id_pengguna')
                ->nullable()
                ->unique()
                ->constrained('pengguna', 'id_pengguna');

            // Identitas
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            // String agar angka 0 di depan tidak hilang; format 8-15 digit divalidasi di backend.
            $table->string('nomor_telepon', 20);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat_lengkap')->nullable();
            $table->string('email')->nullable();
            $table->string('nama_suami')->nullable();

            // Unit kerja suami (ERD: UNIT_KERJA 1 : N ANGGOTA, boleh kosong)
            $table->foreignId('id_unit_kerja')
                ->nullable()
                ->constrained('unit_kerja', 'id_unit_kerja');

            // Pendidikan, pekerjaan, dan keahlian (teks bebas, tidak dipecah per nilai)
            $table->string('tingkat_pendidikan')->nullable();
            $table->string('bidang_pendidikan')->nullable();
            $table->text('keahlian')->nullable();
            $table->string('instansi_kerja')->nullable();
            $table->string('bidang_usaha')->nullable();

            // Foto disimpan sebagai berkas di server; kolom ini hanya menyimpan lokasinya.
            $table->string('foto_path')->nullable();

            // Nullable sesuai ERD (Penasihat/Ketua DWP tidak terikat bidang).
            // "Bidang wajib" pada B-016 ditegakkan lewat validasi form/backend.
            $table->foreignId('id_bidang')
                ->nullable()
                ->constrained('bidang', 'id_bidang');

            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');

            // Soft delete, pola sama dengan tabel pengguna.
            $table->timestamp('dihapus_pada')->nullable();

            // Admin pembuat data anggota (ERD: dibuat_oleh wajib).
            $table->foreignId('dibuat_oleh')->constrained('pengguna', 'id_pengguna');

            $table->timestamps();

            $table->index('dihapus_pada');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota');
    }
};