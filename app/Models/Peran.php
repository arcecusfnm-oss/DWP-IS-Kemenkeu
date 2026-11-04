<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peran extends Model
{
    use HasFactory;
    
    protected $table = 'peran';

    protected $primaryKey = 'id_peran';

    protected $fillable = [
        'nama_peran',
    ];

    public function pengguna(): HasMany
    {
        return $this->hasMany(Pengguna::class, 'id_peran', 'id_peran');
    }
}
