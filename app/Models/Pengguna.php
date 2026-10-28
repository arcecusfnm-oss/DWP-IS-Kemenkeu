<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengguna extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'pengguna';

    protected $primaryKey = 'id_pengguna';

    public const DELETED_AT = 'dihapus_pada';

    protected $fillable = [
        'nama_lengkap',
        'nama_pengguna',
        'kata_sandi_hash',
        'status',
        'perlu_ganti_kata_sandi',
        'id_peran',
        'id_bidang',
    ];

    protected $hidden = [
        'kata_sandi_hash',
    ];

    protected function casts(): array
    {
        return [
            'kata_sandi_hash' => 'hashed',
            'perlu_ganti_kata_sandi' => 'boolean',
            'dihapus_pada' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'kata_sandi_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->kata_sandi_hash;
    }

    public function peran(): BelongsTo
    {
        return $this->belongsTo(Peran::class, 'id_peran', 'id_peran');
    }

    public function bidang(): BelongsTo
    {
        return $this->belongsTo(Bidang::class, 'id_bidang', 'id_bidang');
    }
}
