<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Usulan verifikasi RT untuk satu anak. Riwayat: satu baris per usulan.
 * Ditulis hanya lewat VerifikasiRtService.
 */
class VerifikasiAnak extends Model
{
    protected $table = 'verifikasi_anak';

    public const STATUS = ['berdomisili', 'pindah', 'meninggal', 'tidak_dikenal', 'bukan_rt_ini'];
    public const REVIU  = ['diusulkan', 'disetujui', 'ditolak'];

    public const LABEL_STATUS = [
        'berdomisili'   => 'Berdomisili',
        'pindah'        => 'Pindah',
        'meninggal'     => 'Meninggal',
        'tidak_dikenal' => 'Tidak dikenal',
        'bukan_rt_ini'  => 'Bukan warga RT ini',
    ];

    protected $guarded = [];

    protected $casts = [
        'diusulkan_at' => 'datetime',
        'ditinjau_at'  => 'datetime',
    ];

    public function anak(): BelongsTo
    {
        return $this->belongsTo(Anak::class, 'id_anak');
    }

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
