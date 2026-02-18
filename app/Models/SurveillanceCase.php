<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveillanceCase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveillance_cases';
    protected $guarded = [];

    protected $casts = [
        'tanggal_lahir'              => 'date',
        'tanggal_onset'              => 'date',
        'tanggal_konsultasi'         => 'date',
        'tanggal_lapor'              => 'date',
        'tanggal_pengambilan_sampel' => 'date',
        'tanggal_hasil_lab'          => 'date',
        'tanggal_masuk_rs'           => 'date',
        'tanggal_keluar_rs'          => 'date',
        'tanggal_kondisi_akhir'      => 'date',
        'gejala_demam'               => 'boolean',
        'gejala_ruam'                => 'boolean',
        'gejala_batuk'               => 'boolean',
        'gejala_pilek'               => 'boolean',
        'gejala_konjungtivitis'      => 'boolean',
        'gejala_sesak_napas'         => 'boolean',
        'gejala_nyeri_tenggorokan'   => 'boolean',
        'gejala_membran_tenggorokan' => 'boolean',
        'gejala_kejang'              => 'boolean',
        'gejala_lumpuh_layuh'        => 'boolean',
        'gejala_kaku_rahang'         => 'boolean',
        'gejala_spasme'              => 'boolean',
        'gejala_tali_pusat'          => 'boolean',
        'gejala_diare'               => 'boolean',
        'gejala_muntah'              => 'boolean',
        'gejala_pendarahan'          => 'boolean',
        'gejala_nyeri_sendi'         => 'boolean',
    ];

    // Relationships
    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec', 'id');
    }

    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kel', 'id');
    }

    public function rt()
    {
        return $this->belongsTo(Rt::class, 'id_rt', 'id');
    }

    public function jenisKasus()
    {
        return $this->belongsTo(JenisKasusEpidemiologi::class, 'id_jenis_kasus', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    // Scopes
    public function scopeByDisease($query, $id)
    {
        return $query->where('id_jenis_kasus', $id);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status_kasus', $status);
    }

    public function scopeByOutcome($query, $outcome)
    {
        return $query->where('kondisi_akhir', $outcome);
    }

    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('tanggal_onset', [$start, $end]);
    }

    public function scopeByKecamatan($query, $id)
    {
        return $query->where('id_kec', $id);
    }

    public function scopeByKelurahan($query, $id)
    {
        return $query->where('id_kel', $id);
    }

    // Helpers
    public function getSymptoms()
    {
        $symptoms = [
            'gejala_demam'               => 'Demam',
            'gejala_ruam'                => 'Ruam',
            'gejala_batuk'               => 'Batuk',
            'gejala_pilek'               => 'Pilek',
            'gejala_konjungtivitis'      => 'Konjungtivitis',
            'gejala_sesak_napas'         => 'Sesak Napas',
            'gejala_nyeri_tenggorokan'   => 'Nyeri Tenggorokan',
            'gejala_membran_tenggorokan' => 'Membran Tenggorokan',
            'gejala_kejang'              => 'Kejang',
            'gejala_lumpuh_layuh'        => 'Lumpuh Layuh',
            'gejala_kaku_rahang'         => 'Kaku Rahang',
            'gejala_spasme'              => 'Spasme',
            'gejala_tali_pusat'          => 'Infeksi Tali Pusat',
            'gejala_diare'               => 'Diare',
            'gejala_muntah'              => 'Muntah',
            'gejala_pendarahan'          => 'Pendarahan',
            'gejala_nyeri_sendi'         => 'Nyeri Sendi',
        ];

        return collect($symptoms)->filter(fn($label, $field) => $this->$field)->values();
    }

    public function getSymptomCount()
    {
        return collect([
            'gejala_demam', 'gejala_ruam', 'gejala_batuk', 'gejala_pilek',
            'gejala_konjungtivitis', 'gejala_sesak_napas', 'gejala_nyeri_tenggorokan',
            'gejala_membran_tenggorokan', 'gejala_kejang', 'gejala_lumpuh_layuh',
            'gejala_kaku_rahang', 'gejala_spasme', 'gejala_tali_pusat',
            'gejala_diare', 'gejala_muntah', 'gejala_pendarahan', 'gejala_nyeri_sendi',
        ])->filter(fn($field) => $this->$field)->count();
    }
}
