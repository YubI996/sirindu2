<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveillanceCaseImunisasi extends Model
{
    protected $table = 'surveillance_case_imunisasi';

    protected $fillable = [
        'id_surveillance_case',
        'imunisasi_ke',
        'nama_antigen',
        'diberikan',
        'sumber_informasi',
        'tanggal_imunisasi',
    ];

    protected $casts = [
        'tanggal_imunisasi' => 'date',
        'imunisasi_ke'      => 'integer',
    ];

    public function surveillanceCase()
    {
        return $this->belongsTo(SurveillanceCase::class, 'id_surveillance_case');
    }
}
