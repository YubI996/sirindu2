<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan perubahan nomor EPID akibat perapatan deret setelah penghapusan.
 * Lihat EpidCounter::rapatkanSetelahHapus().
 */
class EpidRenumberLog extends Model
{
    protected $table = 'epid_renumber_log';

    public $timestamps = false;

    protected $fillable = [
        'id_surveillance_case',
        'no_lama',
        'no_baru',
        'dipicu_hapus',
        'id_user',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function case()
    {
        return $this->belongsTo(SurveillanceCase::class, 'id_surveillance_case');
    }
}
