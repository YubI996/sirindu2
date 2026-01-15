<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisVaksin extends Model
{
    use HasFactory;

    protected $table = 'jenis_vaksin';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'usia_pemberian_min',
        'usia_pemberian_max',
        'interval_hari',
        'keterangan',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function imunisasi()
    {
        return $this->hasMany(Imunisasi::class, 'id_jenis_vaksin', 'id');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
