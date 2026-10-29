<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogLogin extends Model
{
    protected $table = 'log_login';

    public $timestamps = false;

    protected $fillable = [
        'pengguna_id',
        'nama_pengguna',
        'berhasil',
        'alasan',
        'alamat_ip',
        'user_agent',
    ];

    protected $casts = [
        'berhasil' => 'boolean',
        'dicatat_pada' => 'datetime',
    ];
}