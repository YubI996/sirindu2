<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'file_path',
        'type',
        'format_tanggal',
        'status',
        'success_count',
        'failure_count',
        'failures',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** Urutan tampil pesan; baris tanpa tanda (ringkasan, "Import gagal: …") tetap paling atas. */
    private const URUTAN_TINGKAT = ['ERROR' => 1, 'PERINGATAN' => 2, 'INFO' => 3];

    /**
     * Pesan dibaca terurut ERROR → PERINGATAN → INFO, urutan baris di dalam tiap tingkat tetap.
     * Diurutkan saat dibaca, bukan saat ditulis, supaya log lama ikut terurut.
     */
    protected function failures(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : self::urutkanPesan(json_decode($value, true) ?? []),
            set: fn (?array $value) => $value === null ? null : json_encode($value),
        );
    }

    public static function urutkanPesan(array $pesan): array
    {
        $tingkat = fn (string $p) => preg_match('/^\[([A-Z]+)\]/', $p, $m) ? (self::URUTAN_TINGKAT[$m[1]] ?? 0) : 0;

        // usort stabil sejak PHP 8, jadi urutan baris di dalam satu tingkat tidak berubah.
        usort($pesan, fn ($a, $b) => $tingkat((string) $a) <=> $tingkat((string) $b));

        return $pesan;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isProcessing(): bool { return $this->status === 'processing'; }
    public function isDone(): bool       { return $this->status === 'done'; }
    public function isFailed(): bool     { return $this->status === 'failed'; }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'    => 'Menunggu',
            'processing' => 'Diproses',
            'done'       => 'Selesai',
            'failed'     => 'Gagal',
            default      => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'    => 'warning',
            'processing' => 'info',
            'done'       => 'success',
            'failed'     => 'danger',
            default      => 'secondary',
        };
    }
}
