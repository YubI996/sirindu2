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
        // ===== Category A: Patient Identity =====
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
        'latitude',
        'longitude',
        'no_telepon',
        // Patient Identity — Google Form additions
        'tempat_kerja_sekolah',
        'nama_orang_tua',
        'no_hp_orang_tua',
        'provinsi',
        'kab_kota',

        // ===== Category B: Reporter Identity =====
        'nama_pelapor',
        'jabatan_pelapor',
        'instansi_pelapor',
        'telepon_pelapor',
        // Reporter — Google Form additions
        'wilker_puskesmas',
        'tanggal_terima_laporan',
        'tanggal_penyidikan',

        // ===== Category C: Case Data =====
        'id_jenis_kasus',
        'kode_icd10',
        'tanggal_onset',
        'tanggal_konsultasi',
        'tanggal_lapor',
        'sumber_penularan',
        'lokasi_penularan',

        // ===== Category D: Clinical Symptoms =====
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
        // Symptoms — Google Form additions
        'tanggal_demam',
        'gejala_adenopathy',
        'gejala_arthralgia',
        'gejala_kehamilan',
        'gejala_lainnya',
        'tanggal_leher_bengkak',
        'tanggal_sesak_nafas',
        'tanggal_pseudomembran',
        'tanggal_apnea',

        // ===== Category D2: Komplikasi =====
        'komplikasi_diare',
        'komplikasi_kebutaan',
        'komplikasi_pneumonia',
        'komplikasi_malnutrisi',
        'komplikasi_bronchopneumonia',
        'komplikasi_otitis_media',
        'komplikasi_encephalitis',
        'komplikasi_ulkus_mukosa_mulut',

        // ===== Category D3: Vitamin A & Status Gizi =====
        'vitamin_a',
        'berat_badan',
        'tinggi_badan',
        'status_gizi',

        // ===== Category D4: Pengobatan =====
        'jenis_antibiotik',
        'dosis_ads',
        'obat_lainnya',

        // ===== Category D5: AFP/Polio =====
        'kelumpuhan_akut',
        'kelumpuhan_flaccid',
        'kelumpuhan_rudapaksa',

        // ===== Category D6: Diagnosis & Pemeriksaan Fisik =====
        'diagnosis',
        'tanda_tungkai_kanan',
        'tanda_tungkai_kiri',
        'tanda_lengan_kanan',
        'tanda_lengan_kiri',
        'kekuatan_otot',
        'lokasi_kelemahan_lain',
        'tanda_penyakit_observasi',

        // ===== Category D7: Kontak Polio =====
        'kontak_polio_oral',

        // ===== Category D8: Sanitasi =====
        'jamban_sendiri',
        'jamban_saluran_kedap',
        'jenis_jamban',
        'selalu_gunakan_jamban',
        'pembuangan_diapers',

        // ===== Category D9: Dokter =====
        'nama_dokter',
        'no_telp_dokter',
        'diagnosis_dokter',

        // ===== Category D10: Tempat Berobat =====
        'tempat_berobat',
        'nama_rs',
        'tanggal_kunjungan_rs',
        'nama_fktp',
        'tanggal_kunjungan_fktp',
        'nama_pengobatan_tradisional',
        'tanggal_kunjungan_tradisional',

        // ===== Category E: History =====
        'riwayat_perjalanan',
        'riwayat_kontak_kasus',
        'riwayat_imunisasi',
        'tanggal_imunisasi_terakhir',
        // Immunization — Google Form detail
        'imunisasi_1',
        'imunisasi_2',
        'imunisasi_3',
        'imunisasi_4',
        'imunisasi_5',
        'sumber_informasi_imunisasi',
        'alasan_imunisasi_tidak_lengkap',

        // ===== Category F: Laboratory =====
        'status_lab',
        'tanggal_pengambilan_spesimen',
        'jenis_spesimen',
        'hasil_lab',
        'tanggal_hasil_lab',
        // Lab — Google Form additional specimens
        'jenis_spesimen_2',
        'tanggal_spesimen_2',
        'jenis_spesimen_3',
        'tanggal_spesimen_3',

        // ===== Category G: Management =====
        'status_rawat',
        'nama_faskes_rawat',
        'tanggal_masuk_rawat',
        'tanggal_keluar_rawat',

        // ===== Category H: Final Status =====
        'kondisi_akhir',
        'tanggal_kondisi_akhir',
        'penyebab_kematian',

        // ===== Category I: Contact Investigation =====
        'jumlah_kontak_serumah',
        'jumlah_kontak_diluar_rumah',
        'jumlah_kontak_bergejala',
        'tindak_lanjut_kontak',
        // Contact — Google Form additions
        'keluarga_sakit_sama',
        'jumlah_keluarga_sakit',
        'riwayat_bepergian',
        'lokasi_bepergian',
        'tanggal_bepergian',

        // ===== Category TN: Tetanus Neonatorum =====
        'lama_tinggal_desa',
        'bayi_lahir_hidup',
        'umur_bayi_meninggal_hari',
        'bayi_menangis_lahir',
        'tanda_kelahiran_hidup',
        'bayi_bisa_menyusu',
        'bayi_mulut_mencucu',
        'bayi_mudah_kejang',
        'jumlah_kunjungan_anc',
        'tempat_pemeriksaan_hamil',
        'pemeriksa_kehamilan',
        'tempat_persalinan',
        'usia_kehamilan_bulan',
        'penolong_persalinan',
        'alat_potong_tali_pusat',
        'perawatan_tali_pusat',
        'keadaan_ibu_saat_ini',

        // ===== Category J: Metadata =====
        'status_kasus',
        'id_petugas_input',
        'id_faskes_pelapor',
        'catatan_tambahan',
        'created_by',
        'updated_by',

        // Faskes scoping
        'faskes_type',
        'id_faskes',
    ];

    protected $casts = [
        // Dates
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
        // Google Form addition dates
        'tanggal_terima_laporan' => 'date',
        'tanggal_penyidikan' => 'date',
        'tanggal_demam' => 'date',
        'tanggal_leher_bengkak' => 'date',
        'tanggal_sesak_nafas' => 'date',
        'tanggal_pseudomembran' => 'date',
        'tanggal_apnea' => 'date',
        'tanggal_kunjungan_rs' => 'date',
        'tanggal_kunjungan_fktp' => 'date',
        'tanggal_kunjungan_tradisional' => 'date',
        'tanggal_spesimen_2' => 'date',
        'tanggal_spesimen_3' => 'date',
        'tanggal_bepergian' => 'date',

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
        // Google Form addition booleans
        'gejala_adenopathy' => 'boolean',
        'gejala_arthralgia' => 'boolean',
        'gejala_kehamilan' => 'boolean',

        // Komplikasi booleans
        'komplikasi_diare' => 'boolean',
        'komplikasi_kebutaan' => 'boolean',
        'komplikasi_pneumonia' => 'boolean',
        'komplikasi_malnutrisi' => 'boolean',
        'komplikasi_bronchopneumonia' => 'boolean',
        'komplikasi_otitis_media' => 'boolean',
        'komplikasi_encephalitis' => 'boolean',
        'komplikasi_ulkus_mukosa_mulut' => 'boolean',

        // Other
        'riwayat_kontak_kasus' => 'boolean',
        'jumlah_kontak_serumah' => 'integer',
        'jumlah_kontak_diluar_rumah' => 'integer',
        'jumlah_kontak_bergejala' => 'integer',
        'jumlah_keluarga_sakit' => 'integer',
        'kekuatan_otot' => 'integer',
        'umur_bayi_meninggal_hari' => 'integer',
        'jumlah_kunjungan_anc' => 'integer',
        'usia_kehamilan_bulan' => 'integer',
        'berat_badan' => 'decimal:1',
        'tinggi_badan' => 'decimal:1',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $appends = ['umur', 'lama_rawat'];

    /**
     * Get age (umur) calculated from tanggal_lahir
     */
    protected function umur(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tanggal_lahir ? $this->tanggal_lahir->age : null,
        );
    }

    /**
     * Get length of stay (lama_rawat) calculated from treatment dates
     */
    protected function lamaRawat(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->tanggal_masuk_rawat && $this->tanggal_keluar_rawat)
                ? $this->tanggal_masuk_rawat->diffInDays($this->tanggal_keluar_rawat) + 1
                : null,
        );
    }

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
            // Google Form additions
            'adenopathy' => $this->gejala_adenopathy,
            'arthralgia' => $this->gejala_arthralgia,
            'kehamilan' => $this->gejala_kehamilan,
        ];
    }

    /**
     * Get all complications as an array
     */
    public function getKomplikasi()
    {
        return [
            'diare' => $this->komplikasi_diare,
            'kebutaan' => $this->komplikasi_kebutaan,
            'pneumonia' => $this->komplikasi_pneumonia,
            'malnutrisi' => $this->komplikasi_malnutrisi,
            'bronchopneumonia' => $this->komplikasi_bronchopneumonia,
            'otitis_media' => $this->komplikasi_otitis_media,
            'encephalitis' => $this->komplikasi_encephalitis,
            'ulkus_mukosa_mulut' => $this->komplikasi_ulkus_mukosa_mulut,
        ];
    }

    /**
     * Get count of active symptoms
     */
    public function getSymptomCount()
    {
        return count(array_filter($this->getSymptoms()));
    }

    /**
     * Get count of active complications
     */
    public function getKomplikasiCount()
    {
        return count(array_filter($this->getKomplikasi()));
    }
}
