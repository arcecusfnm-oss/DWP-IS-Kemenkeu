<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class BidangSesuaiPeran implements ValidationRule
{
    public function __construct(private ?string $peran, private ?int $kecualiId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($value, self::bidangBoleh($this->peran), true)) {
            $fail('Bidang tidak sesuai dengan peran yang dipilih.');
            return;
        }

        if (self::slotTerisi($this->peran, $value, $this->kecualiId)) {
            $fail("Peran {$this->peran} untuk bidang {$value} sudah terisi.");
        }
    }

    public static function tanpaBidang(?string $peran): bool
    {
        return in_array($peran, config('dwp.peran_tanpa_bidang', []), true);
    }


    /** Bidang yang boleh dipakai suatu peran (dinamis dari tabel bidang). */
    public static function bidangBoleh(?string $peran): array
    {
        if (! in_array($peran, config('dwp.peran'), true)) {
            return [];
        }

        $tetap = config('dwp.peran_bidang_tetap')[$peran] ?? null;
        if ($tetap) {
            return $tetap;
        }

        return DB::table('bidang')->orderBy('id_bidang')->pluck('nama_bidang')->all();
    }

    public static function slotTerisi(?string $peran, string $bidang, ?int $kecualiId = null): bool
    {
        if (! in_array($peran, config('dwp.peran_satu_per_slot'), true)) {
            return false; // Admin, Bendahara, Anggota: tanpa batas
        }

        $satuSlot = [$bidang];
        foreach (config('dwp.slot_gabungan')[$peran] ?? [] as $kelompok) {
            if (in_array($bidang, $kelompok, true)) {
                $satuSlot = $kelompok;
                break;
            }
        }

        return DB::table('pengguna')
            ->join('peran', 'pengguna.id_peran', '=', 'peran.id_peran')
            ->join('bidang', 'pengguna.id_bidang', '=', 'bidang.id_bidang')
            ->whereNull('pengguna.dihapus_pada')
            ->where('peran.nama_peran', $peran)
            ->whereIn('bidang.nama_bidang', $satuSlot)
            ->when($kecualiId, fn ($q) => $q->where('pengguna.id_pengguna', '!=', $kecualiId))
            ->exists();
    }
}