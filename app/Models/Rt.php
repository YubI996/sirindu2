<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rt extends Model
{
    use HasFactory;
    protected $table = 'rt';
    protected $fillable = ['id_kelurahan', 'id_posyandu', 'name'];

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kelurahan');
    }

    /** Tautan akses bertoken yang masih berlaku (paling banyak satu per RT). */
    public function tautanAktif()
    {
        return $this->hasOne(RtAksesTautan::class, 'id_rt')->ofMany(['id' => 'max'], fn ($q) => $q->aktif());
    }
}
