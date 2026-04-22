<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SekolahDasar extends Model
{
    protected $table = 'sekolah_dasar';

    protected $fillable = ['nama', 'id_puskesmas'];

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class, 'id_puskesmas');
    }
}
