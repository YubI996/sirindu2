<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioritasGizi extends Model
{
    protected $table = 'prioritas_gizi';
    protected $guarded = [];

    protected $casts = [
        'gizi_buruk' => 'boolean',
        'gizi_kurang' => 'boolean',
        'stunting' => 'boolean',
        'bb_tidak_naik' => 'boolean',
        'prioritas' => 'integer',
        'usia_bln' => 'integer',
        'refreshed_at' => 'datetime',
    ];

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak', 'id');
    }
}
