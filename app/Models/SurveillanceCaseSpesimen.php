<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveillanceCaseSpesimen extends Model
{
    protected $table = 'surveillance_case_spesimen';

    protected $fillable = [
        'id_surveillance_case',
        'urutan',
        'jenis_spesimen',
        'tanggal_ambil_spesimen',
        'tanggal_kirim_sampel',
        'tanggal_terima_lab',
        'status_pemeriksaan',
        'id_jenis_kasus_terkonfirmasi',
        'nama_variant_genotype',
    ];

    protected $casts = [
        'tanggal_ambil_spesimen' => 'date',
        'tanggal_kirim_sampel'   => 'date',
        'tanggal_terima_lab'     => 'date',
        'urutan'                 => 'integer',
    ];

    public function surveillanceCase()
    {
        return $this->belongsTo(SurveillanceCase::class, 'id_surveillance_case');
    }

    public function jenisPenyakitTerkonfirmasi()
    {
        return $this->belongsTo(JenisKasusEpidemiologi::class, 'id_jenis_kasus_terkonfirmasi');
    }

    public function jenisKasusTerkonfirmasi()
    {
        return $this->belongsTo(JenisKasusEpidemiologi::class, 'id_jenis_kasus_terkonfirmasi');
    }
}
