<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LokasiPenularanMaster extends Model
{
    use HasFactory;

    protected $table = 'lokasi_penularan_master';

    protected $fillable = [
        'nama',
        'kategori',
        'is_custom',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
    ];
}
