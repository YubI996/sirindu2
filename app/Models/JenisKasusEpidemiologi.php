<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisKasusEpidemiologi extends Model
{
    use HasFactory;

    protected $table = 'jenis_kasus_epidemiologi';

    protected $fillable = [
        'kode_penyakit',
        'nama_penyakit',
        'kategori',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get surveillance cases of this disease type
     */
    public function surveillanceCases()
    {
        return $this->hasMany(SurveillanceCase::class, 'id_jenis_kasus', 'id');
    }

    /**
     * Scope to get only active diseases
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('kategori', $category);
    }
}
