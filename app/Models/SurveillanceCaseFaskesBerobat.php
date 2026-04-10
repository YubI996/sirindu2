<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveillanceCaseFaskesBerobat extends Model
{
    protected $table = 'surveillance_case_faskes_berobat';

    protected $fillable = [
        'id_surveillance_case',
        'urutan',
        'jenis_faskes',
        'nama_faskes',
        'tanggal_berobat',
        'jenis_perawatan',
        'tanggal_keluar',
    ];

    protected $casts = [
        'tanggal_berobat' => 'date',
        'tanggal_keluar'  => 'date',
        'urutan'          => 'integer',
    ];

    public function surveillanceCase()
    {
        return $this->belongsTo(SurveillanceCase::class, 'id_surveillance_case');
    }
}
