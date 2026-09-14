<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pasangan kandidat hasil pindai (a<b). Derived data — aman dihapus & dipindai ulang. */
class AnakKandidat extends Model
{
    protected $table = 'anak_kandidat';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['dipindai_at' => 'datetime'];

    public function anakA(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak_a');
    }

    public function anakB(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak_b');
    }
}
