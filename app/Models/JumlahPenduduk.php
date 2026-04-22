<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JumlahPenduduk extends Model
{
    protected $table = 'jumlah_penduduk';

    protected $fillable = ['tahun', 'kategori', 'id_kelurahan', 'jumlah_penduduk'];

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kelurahan');
    }
}
