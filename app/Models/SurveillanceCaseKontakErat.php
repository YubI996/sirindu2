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
        'tanggal_lahir',
        'no_telepon',
        'alamat',
        'tanggal_kontak_terakhir',
        'ada_gejala',
        'jumlah_imunisasi_campak_rubella',
        'catatan',
    ];

    protected $casts = [
        'tanggal_lahir'                   => 'date',
        'tanggal_kontak_terakhir'         => 'date',
        'ada_gejala'                      => 'boolean',
        'urutan'                          => 'integer',
        'jumlah_imunisasi_campak_rubella' => 'integer',
    ];

    public function surveillanceCase()
    {
        return $this->belongsTo(SurveillanceCase::class, 'id_surveillance_case');
    }
}
