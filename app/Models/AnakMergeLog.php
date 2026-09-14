<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris per penggabungan; `snapshot` memuat semua yang dibutuhkan untuk membatalkan. */
class AnakMergeLog extends Model
{
    protected $table = 'anak_merge_log';
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'dibatalkan_at' => 'datetime'];

    public function scopeAktif(Builder $q): Builder
    {
        return $q->whereNull('dibatalkan_at');
    }

    public function dipertahankan(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_dipertahankan');
    }

    public function tautan(): BelongsTo
    {
        return $this->belongsTo(AnakTautan::class, 'id_tautan');
    }

    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }

    public function pembatal(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }
}
