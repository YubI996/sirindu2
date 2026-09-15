<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Keputusan RT atas satu pasangan (a<b) + reviu. Ditulis hanya lewat TautanIdentitasService. */
class AnakTautan extends Model
{
    protected $table = 'anak_tautan';

    public const KEPUTUSAN = ['sama', 'beda'];
    public const STATUS    = ['diusulkan', 'disetujui', 'ditolak', 'digabung'];

    protected $guarded = [];
    protected $casts = ['diusulkan_at' => 'datetime', 'ditinjau_at' => 'datetime'];

    /** @return array{0:int,1:int} [min, max] */
    public static function urut(int $x, int $y): array
    {
        return $x < $y ? [$x, $y] : [$y, $x];
    }

    public function anakA(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak_a');
    }

    public function anakB(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak_b');
    }

    /** RT yang memutus (Sept 2026; dulu diturunkan dari users.id_rt pengusul). */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class, 'id_rt');
    }

    public function pengusul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diusulkan_oleh');
    }

    public function peninjau(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh');
    }
}
