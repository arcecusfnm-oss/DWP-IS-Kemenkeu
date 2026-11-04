<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bidang extends Model
{
    use HasFactory;

    protected $table = 'bidang';

    protected $primaryKey = 'id_bidang';

    protected $fillable = [
        'nama_bidang',
    ];

    public function pengguna(): HasMany
    {
        return $this->hasMany(Pengguna::class, 'id_bidang', 'id_bidang');
    }
}
