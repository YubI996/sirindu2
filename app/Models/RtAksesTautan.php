<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Tautan bertoken per RT (mode B scoping akses). Token plaintext hanya ada di
 * nilai balik buat(); DB menyimpan sha256-nya sehingga bocornya tabel tidak
 * membuka akses. Satu RT hanya punya satu tautan aktif — membuat yang baru
 * mencabut yang lama.
 */
class RtAksesTautan extends Model
{
    public const HARI_DEFAULT = 30;

    protected $table = 'rt_akses_tautan';

    protected $guarded = [];

    protected $casts = [
        'kedaluwarsa_at'      => 'datetime',
        'dicabut_at'          => 'datetime',
        'terakhir_dipakai_at' => 'datetime',
    ];

    public function scopeAktif(Builder $q): Builder
    {
        return $q->whereNull('dicabut_at')->where('kedaluwarsa_at', '>', now());
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class, 'id_rt');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function masihBerlaku(): bool
    {
        return $this->dicabut_at === null && $this->kedaluwarsa_at->isFuture();
    }

    /** @return array{model: self, token: string} token plaintext — tampilkan sekali, jangan disimpan */
    public static function buat(Rt $rt, User $oleh, int $hari = self::HARI_DEFAULT): array
    {
        static::where('id_rt', $rt->id)->whereNull('dicabut_at')->update(['dicabut_at' => now()]);

        $token = Str::random(40);
        $model = static::create([
            'id_rt'          => $rt->id,
            'token_hash'     => hash('sha256', $token),
            'kedaluwarsa_at' => now()->addDays($hari),
            'dibuat_oleh'    => $oleh->id,
        ]);

        return ['model' => $model, 'token' => $token];
    }

    public static function cariToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }

        return static::aktif()->where('token_hash', hash('sha256', $token))->first();
    }

    public function cabut(): void
    {
        if ($this->dicabut_at === null) {
            $this->update(['dicabut_at' => now()]);
        }
    }

    public function catatPakai(): void
    {
        $this->increment('jumlah_pakai', 1, ['terakhir_dipakai_at' => now()]);
    }
}
