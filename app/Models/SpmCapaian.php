<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu kategori × satu tahun. tw1..tw4 boleh NULL
 * (triwulan belum dilaporkan) — cast 'float' mempertahankan NULL.
 */
class SpmCapaian extends Model
{
    protected $table = 'spm_capaian';

    protected $fillable = ['id_kategori', 'tahun', 'sasaran', 'tw1', 'tw2', 'tw3', 'tw4', 'catatan'];

    protected $casts = [
        'tahun'   => 'integer',
        'sasaran' => 'float',
        'tw1'     => 'float',
        'tw2'     => 'float',
        'tw3'     => 'float',
        'tw4'     => 'float',
    ];

    public function kategori()
    {
        return $this->belongsTo(SpmKategori::class, 'id_kategori');
    }
}
