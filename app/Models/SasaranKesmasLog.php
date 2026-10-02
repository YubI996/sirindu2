<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak perubahan anak.sasaran_balita_kesmas (spec 2026-10-02 §4). Hanya ditambah, tidak pernah
 * diubah — karena itu tanpa updated_at.
 */
class SasaranKesmasLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sasaran_kesmas_log';
    protected $guarded = [];
}
