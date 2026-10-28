<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Pengguna extends Authenticatable
{
    use Notifiable, SoftDeletes;

    public const DELETED_AT = 'dihapus_pada';

    protected $table = 'pengguna';
    protected $primaryKey = 'id_pengguna';

    protected $fillable = [
        'nama_lengkap',
        'nama_pengguna',
        'kata_sandi_hash',
        'status',
        'perlu_ganti_kata_sandi',
        'id_peran',
        'id_bidang',
    ];

    protected $hidden = ['kata_sandi_hash'];

    protected function casts(): array
    {
        return [
            'kata_sandi_hash' => 'hashed',
            'perlu_ganti_kata_sandi' => 'boolean',
        ];
    }

    // Laravel mencari kolom "password" secara default
    public function getAuthPassword()
    {
        return $this->kata_sandi_hash;
    }

    public function getRememberTokenName()
    {
        return '';
    }

    public function peran()
    {
        return $this->belongsTo(Peran::class, 'id_peran', 'id_peran');
    }

    public function bidang()
    {
        return $this->belongsTo(Bidang::class, 'id_bidang', 'id_bidang');
    }
}