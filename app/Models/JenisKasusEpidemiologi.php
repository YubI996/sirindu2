<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisKasusEpidemiologi extends Model
{
    use HasFactory;

    protected $table = 'jenis_kasus_epidemiologi';
    protected $guarded = [];

    public function cases()
    {
        return $this->hasMany(SurveillanceCase::class, 'id_jenis_kasus', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $kategori)
    {
        return $query->where('kategori', $kategori);
    }
}
