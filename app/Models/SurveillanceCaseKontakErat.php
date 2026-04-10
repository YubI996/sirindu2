<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveillanceCaseKontakErat extends Model
{
    protected $table = 'surveillance_case_kontak_erat';

    protected $fillable = [
        'id_surveillance_case',
        'urutan',
        'nama',
        'hubungan',
        'no_telepon',
        'alamat',
        'tanggal_kontak_terakhir',
        'ada_gejala',
        'catatan',
    ];

    protected $casts = [
        'tanggal_kontak_terakhir' => 'date',
        'ada_gejala'              => 'boolean',
        'urutan'                  => 'integer',
    ];

    public function surveillanceCase()
    {
        return $this->belongsTo(SurveillanceCase::class, 'id_surveillance_case');
    }
}
