<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntervensiGizi extends Model
{
    protected $table = 'intervensi_gizi';
    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date',
    ];

    /** Pilihan jenis intervensi (lintas dinas). */
    public const JENIS = [
        'PMT',
        'Pemeriksaan Kesehatan',
        'Suplementasi',
        'Rujukan',
        'Bansos',
        'Dukungan Pangan',
        'Pendampingan Keluarga',
    ];

    /** Status pelaksanaan. */
    public const STATUS = [
        'Direncanakan',
        'Berjalan',
        'Selesai',
    ];

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak', 'id');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
