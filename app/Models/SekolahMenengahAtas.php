<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SekolahMenengahAtas extends Model
{
    protected $table = 'sekolah_menengah_atas';

    protected $fillable = ['nama', 'id_puskesmas'];

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class, 'id_puskesmas');
    }
}
