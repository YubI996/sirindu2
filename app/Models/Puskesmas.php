<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Puskesmas extends Model
{
    use HasFactory;
    protected $table = 'puskesmas';
    protected $fillable = ['id_kecamatan', 'name'];

    public function sekolahMenengahPertama()
    {
        return $this->hasMany(SekolahMenengahPertama::class, 'id_puskesmas');
    }

    public function sekolahDasar()
    {
        return $this->hasMany(SekolahDasar::class, 'id_puskesmas');
    }

    public function sekolahMenengahAtas()
    {
        return $this->hasMany(SekolahMenengahAtas::class, 'id_puskesmas');
    }
}
