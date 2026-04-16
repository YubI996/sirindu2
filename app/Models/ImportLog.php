<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'file_path',
        'type',
        'status',
        'success_count',
        'failure_count',
        'failures',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'failures'     => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

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
