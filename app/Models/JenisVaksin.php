<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisVaksin extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_vaksin';

    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'usia_pemberian_min',
        'usia_pemberian_max',
        'interval_hari',
        'catchup_max_hari',
        'bisa_dikejar',
        'keterangan',
        'id_kelompok_vaksin',
        'aktif',
    ];

    protected $casts = [
        'aktif'        => 'boolean',
        'bisa_dikejar' => 'boolean',
    ];

    public function kelompokVaksin()
    {
        return $this->belongsTo(KelompokVaksin::class, 'id_kelompok_vaksin', 'id');
    }

    public function imunisasi()
    {
        return $this->hasMany(Imunisasi::class, 'id_jenis_vaksin', 'id');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
