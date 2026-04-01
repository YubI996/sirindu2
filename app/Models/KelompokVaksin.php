<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KelompokVaksin extends Model
{
    use HasFactory;

    protected $table = 'kelompok_vaksin';

    protected $fillable = [
        'kode',
        'nama',
        'usia_pemberian_min',
        'usia_pemberian_max',
        'batas_usia_kejar',
        'keterangan',
    ];

    public function jenisVaksin()
    {
        return $this->hasMany(JenisVaksin::class, 'id_kelompok_vaksin', 'id');
    }
}
