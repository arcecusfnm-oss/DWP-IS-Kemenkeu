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
        $boleh = config('dwp.peran_bidang')[$this->peran] ?? [];

        if (! in_array($value, $boleh, true)) {
            $fail('Bidang tidak sesuai dengan peran yang dipilih.');
            return;
        }

        if (self::slotTerisi($this->peran, $value, $this->kecualiId)) {
            $fail("Peran {$this->peran} untuk bidang {$value} sudah terisi.");
        }
    }

    public static function slotTerisi(?string $peran, string $bidang, ?int $kecualiId = null): bool
    {
        $slot = config('dwp.slot')[$peran] ?? null;
        if (! $slot || ! isset($slot[$bidang])) {
            return false; // Admin, Bendahara, Anggota: tanpa batas
        }

        $satuSlot = array_keys(array_filter($slot, fn ($s) => $s === $slot[$bidang]));

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