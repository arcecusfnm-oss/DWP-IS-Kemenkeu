<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bidang extends Model
{
    protected $table = 'bidang';
    protected $primaryKey = 'id_bidang';

    protected $fillable = ['nama_bidang'];

    public function pengguna()
    {
        return $this->hasMany(Pengguna::class, 'id_bidang', 'id_bidang');

    }
}
