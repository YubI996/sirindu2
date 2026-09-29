<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kategori SPM yang didaftarkan Dinkes sendiri (bebas, aplikasi tidak tahu maknanya).
 * Definisinya berumur panjang; angkanya per tahun ada di SpmCapaian.
 */
class SpmKategori extends Model
{
    use SoftDeletes;

    protected $table = 'spm_kategori';

    protected $fillable = ['nama', 'satuan', 'keterangan', 'urutan', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan'    => 'integer',
    ];

    public function capaian()
    {
        return $this->hasMany(SpmCapaian::class, 'id_kategori');
    }

    /** Baris angka untuk satu tahun, atau null bila tahun itu belum diisi. */
    public function capaianTahun(int $tahun): ?SpmCapaian
    {
        return $this->capaian()->where('tahun', $tahun)->first();
    }
}
