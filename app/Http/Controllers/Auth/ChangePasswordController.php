<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\KataSandiKuat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        // Tolak request yang mencoba mengirim ID akun lain.
        if (
            $request->exists('id_pengguna') ||
            $request->exists('user_id')
        ) {
            throw ValidationException::withMessages([
                'kata_sandi_baru' =>
                    'Permintaan tidak valid. Akun yang diubah harus akun sendiri.',
            ]);
        }

        // Validasi field kata sandi.
        $validated = $request->validate([
            'kata_sandi_lama' => ['required', 'string'],
            'kata_sandi_baru' => ['required', 'string', 'min:8', new KataSandiKuat()],
        ], [
            'kata_sandi_lama.required' => 'Kata sandi lama salah',
            'kata_sandi_baru.required' => 'Kata sandi baru wajib diisi.',
            'kata_sandi_baru.min' => 'Kata sandi baru minimal 8 karakter.',
        ]);

        // Ambil pengguna yang sedang login.
        $pengguna = $request->user();

        // Periksa kata sandi lama.
        if (
            !Hash::check(
                $validated['kata_sandi_lama'],
                $pengguna->kata_sandi_hash
            )
        ) {
            throw ValidationException::withMessages([
                'kata_sandi_lama' => 'Kata sandi lama salah',
            ]);
        }

        // Catat dulu apakah ini alur paksa (setelah reset), sebelum penanda dihapus.
        $wajibGanti = (bool) $pengguna->perlu_ganti_kata_sandi;

        // Simpan kata sandi baru.
        // Model Pengguna otomatis melakukan hashing melalui cast hashed.
        $pengguna->kata_sandi_hash = $validated['kata_sandi_baru'];
        $pengguna->perlu_ganti_kata_sandi = false;
        $pengguna->save();

        // Alur paksa (setelah reset): lanjut ke halaman tujuan sesuai peran.
        if ($wajibGanti) {
            $tujuan = $pengguna->peran?->nama_peran === 'Admin'
                ? route('admin.dashboard')
                : route('beranda');

            return redirect($tujuan)->with('success', 'Kata sandi berhasil diubah.');
        }

        // Alur biasa (B-003, form di /admin/profil): perilaku tetap sama.
        return back()->with('success', 'Kata sandi berhasil diubah.');
    }
}