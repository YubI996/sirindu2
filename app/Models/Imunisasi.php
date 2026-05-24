<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Imunisasi extends Model
{
    use HasFactory;

    protected $table = 'imunisasi';

    protected $fillable = [
        'id_anak',
        'id_jenis_vaksin',
        'dosis',
        'tanggal_pemberian',
        'tanggal_selanjutnya',
        'batch_number',
        'lokasi_pemberian',
        'id_petugas',
        'status',
        'is_kejar',
        'reaksi_kipi',
        'catatan',
    ];

    protected $casts = [
        'tanggal_pemberian'   => 'date',
        'tanggal_selanjutnya' => 'date',
        'is_kejar'            => 'boolean',
    ];

    public function anak()
    {
        return $this->belongsTo(Anak::class, 'id_anak', 'id');
    }

    public function jenisVaksin()
    {
        return $this->belongsTo(JenisVaksin::class, 'id_jenis_vaksin', 'id');
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'id_petugas', 'id');
    }

    public function scopeSudah($query)
    {
        return $query->where('status', 'sudah');
    }

    public function scopeBelum($query)
    {
        return $query->where('status', 'belum');
    }

    public function scopeTerlambat($query)
    {
        return $query->where('status', 'terlambat');
    }

    public function getStatusBadgeAttribute()
    {
        return match ($this->status) {
            'sudah'          => '<span class="badge bg-success">Sudah</span>',
            'belum'          => '<span class="badge bg-warning text-dark">Belum</span>',
            'terlambat'      => '<span class="badge bg-danger">Terlambat</span>',
            'kadaluarsa'     => '<span class="badge bg-secondary">Kedaluwarsa</span>',
            'tidak_relevan'  => '<span class="badge bg-light text-secondary border">Tidak Relevan</span>',
            default          => '<span class="badge bg-secondary">-</span>',
        };
    }
}
