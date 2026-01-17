<?php

namespace App\Models;

use App\Traits\HasHashId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anak extends Model
{
    use HasFactory, HasHashId;

    protected $table = 'anak';
    protected $keyType = 'string';
    protected $guarded = [];
    protected $appends = ['hashid'];

    public function kec()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec', 'id');
    }
    public function kel()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kel', 'id');
    }
    public function dataAnak()
    {
        return $this->hasMany(DataAnak::class,'id_anak','id');
    }

    public function imunisasi()
    {
        return $this->hasMany(Imunisasi::class, 'id_anak', 'id');
    }
}
