<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class SurveillanceCase extends Model
{
    use HasFactory;

    protected $table = 'surveillance_cases';

    protected $fillable = [
        // Category A: Patient Identity
        'no_registrasi',
        'nik',
        'nama_lengkap',
        'tanggal_lahir',
        'kategori_umur',
        'jenis_kelamin',
        'alamat_lengkap',
        'id_kec',
        'id_kel',
        'id_rt',
        'no_telepon',

        // Category B: Reporter Identity
        'nama_pelapor',
        'jabatan_pelapor',
        'instansi_pelapor',
        'telepon_pelapor',

        // Category C: Case Data
        'id_jenis_kasus',
        'kode_icd10',
        'tanggal_onset',
        'tanggal_konsultasi',
        'tanggal_lapor',
        'sumber_penularan',
        'lokasi_penularan',

        // Category D: Clinical Symptoms
        'gejala_demam',
        'gejala_batuk',
        'gejala_pilek',
        'gejala_sakit_kepala',
        'gejala_mual',
        'gejala_muntah',
        'gejala_diare',
        'gejala_ruam',
        'gejala_sesak_napas',
        'gejala_nyeri_otot',
        'gejala_nyeri_sendi',
        'gejala_lemas',
        'gejala_kehilangan_nafsu_makan',
        'gejala_mata_merah',
        'gejala_pembengkakan_kelenjar',
        'gejala_kejang',
        'gejala_penurunan_kesadaran',

        // Category E: History
        'riwayat_perjalanan',
        'riwayat_kontak_kasus',
        'riwayat_imunisasi',
        'tanggal_imunisasi_terakhir',

        // Category F: Laboratory
        'status_lab',
        'tanggal_pengambilan_spesimen',
        'jenis_spesimen',
        'hasil_lab',
        'tanggal_hasil_lab',

        // Category G: Management
        'status_rawat',
        'nama_faskes_rawat',
        'tanggal_masuk_rawat',
        'tanggal_keluar_rawat',

        // Category H: Final Status
        'kondisi_akhir',
        'tanggal_kondisi_akhir',
        'penyebab_kematian',

        // Category I: Contact Investigation
        'jumlah_kontak_serumah',
        'jumlah_kontak_diluar_rumah',
        'jumlah_kontak_bergejala',
        'tindak_lanjut_kontak',

        // Category J: Metadata
        'status_kasus',
        'id_petugas_input',
        'id_faskes_pelapor',
        'catatan_tambahan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_onset' => 'date',
        'tanggal_konsultasi' => 'date',
        'tanggal_lapor' => 'date',
        'tanggal_imunisasi_terakhir' => 'date',
        'tanggal_pengambilan_spesimen' => 'date',
        'tanggal_hasil_lab' => 'date',
        'tanggal_masuk_rawat' => 'date',
        'tanggal_keluar_rawat' => 'date',
        'tanggal_kondisi_akhir' => 'date',

        // Boolean symptoms
        'gejala_demam' => 'boolean',
        'gejala_batuk' => 'boolean',
        'gejala_pilek' => 'boolean',
        'gejala_sakit_kepala' => 'boolean',
        'gejala_mual' => 'boolean',
        'gejala_muntah' => 'boolean',
        'gejala_diare' => 'boolean',
        'gejala_ruam' => 'boolean',
        'gejala_sesak_napas' => 'boolean',
        'gejala_nyeri_otot' => 'boolean',
        'gejala_nyeri_sendi' => 'boolean',
        'gejala_lemas' => 'boolean',
        'gejala_kehilangan_nafsu_makan' => 'boolean',
        'gejala_mata_merah' => 'boolean',
        'gejala_pembengkakan_kelenjar' => 'boolean',
        'gejala_kejang' => 'boolean',
        'gejala_penurunan_kesadaran' => 'boolean',

        'riwayat_kontak_kasus' => 'boolean',
        'jumlah_kontak_serumah' => 'integer',
        'jumlah_kontak_diluar_rumah' => 'integer',
        'jumlah_kontak_bergejala' => 'integer',
    ];

    /**
     * Get the kecamatan (subdistrict) of the case
     */
    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'id_kec', 'id');
    }

    /**
     * Get the kelurahan (village) of the case
     */
    public function kelurahan()
    {
        return $this->belongsTo(Kelurahan::class, 'id_kel', 'id');
    }

    /**
     * Get the RT (neighborhood) of the case
     */
    public function rt()
    {
        return $this->belongsTo(Rt::class, 'id_rt', 'id');
    }

    /**
     * Get the disease type of the case
     */
    public function jenisKasus()
    {
        return $this->belongsTo(JenisKasusEpidemiologi::class, 'id_jenis_kasus', 'id');
    }

    /**
     * Get the staff who input the case
     */
    public function petugasInput()
    {
        return $this->belongsTo(User::class, 'id_petugas_input', 'id');
    }

    /**
     * Get the user who created the case
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get the user who last updated the case
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    /**
     * Scope to filter by disease type
     */
    public function scopeByDisease($query, $diseaseId)
    {
        return $query->where('id_jenis_kasus', $diseaseId);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status_kasus', $status);
    }

    /**
     * Scope to filter by kondisi akhir
     */
    public function scopeByOutcome($query, $outcome)
    {
        return $query->where('kondisi_akhir', $outcome);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('tanggal_onset', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by kecamatan
     */
    public function scopeByKecamatan($query, $kecId)
    {
        return $query->where('id_kec', $kecId);
    }

    /**
     * Scope to filter by kelurahan
     */
    public function scopeByKelurahan($query, $kelId)
    {
        return $query->where('id_kel', $kelId);
    }

    /**
     * Get all symptoms as an array
     */
    public function getSymptoms()
    {
        return [
            'demam' => $this->gejala_demam,
            'batuk' => $this->gejala_batuk,
            'pilek' => $this->gejala_pilek,
            'sakit_kepala' => $this->gejala_sakit_kepala,
            'mual' => $this->gejala_mual,
            'muntah' => $this->gejala_muntah,
            'diare' => $this->gejala_diare,
            'ruam' => $this->gejala_ruam,
            'sesak_napas' => $this->gejala_sesak_napas,
            'nyeri_otot' => $this->gejala_nyeri_otot,
            'nyeri_sendi' => $this->gejala_nyeri_sendi,
            'lemas' => $this->gejala_lemas,
            'kehilangan_nafsu_makan' => $this->gejala_kehilangan_nafsu_makan,
            'mata_merah' => $this->gejala_mata_merah,
            'pembengkakan_kelenjar' => $this->gejala_pembengkakan_kelenjar,
            'kejang' => $this->gejala_kejang,
            'penurunan_kesadaran' => $this->gejala_penurunan_kesadaran,
        ];
    }

    /**
     * Get count of active symptoms
     */
    public function getSymptomCount()
    {
        return count(array_filter($this->getSymptoms()));
    }
}
