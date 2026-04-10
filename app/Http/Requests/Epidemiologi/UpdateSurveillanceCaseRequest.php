<?php

namespace App\Http\Requests\Epidemiologi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveillanceCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $caseId = $this->route('id'); // Get ID from route parameter

        return [
            // Category A: Patient Identity (Required fields)
            'no_registrasi' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('surveillance_cases', 'no_registrasi')->ignore($caseId)
            ],
            'nik' => 'required|string|size:16',
            'nama_lengkap' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date|before:today',
            'jenis_kelamin' => 'required|in:L,P',
            'alamat_lengkap' => 'required|string',
            'id_kec' => 'required|exists:kecamatan,id',
            'id_kel' => 'required|exists:kelurahan,id',
            'id_rt' => 'required|exists:rt,id',
            'no_telepon' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            // Patient Identity — Google Form additions
            'tempat_kerja_sekolah' => 'nullable|string|max:255',
            'nama_orang_tua' => 'nullable|string|max:255',
            'no_hp_orang_tua' => 'nullable|string|max:20',
            'provinsi' => 'nullable|string|max:100',
            'kab_kota' => 'nullable|string|max:100',

            // Category B: Reporter Identity (nama_pelapor required)
            'nama_pelapor' => 'required|string|max:255',
            'jabatan_pelapor' => 'nullable|string|max:100',
            'instansi_pelapor' => 'nullable|string|max:255',
            'telepon_pelapor' => 'nullable|string|max:20',
            // Reporter — Google Form additions
            'wilker_puskesmas' => 'nullable|string|max:255',
            'tanggal_terima_laporan' => 'nullable|date|before_or_equal:today',
            'tanggal_penyidikan' => 'nullable|date|before_or_equal:today',

            // Category C: Case Data (Required fields)
            'id_jenis_kasus' => 'required|exists:jenis_kasus_epidemiologi,id',
            'kode_icd10' => 'nullable|string|max:10',
            'tanggal_onset' => 'required|date|after_or_equal:tanggal_lahir|before_or_equal:today',
            'tanggal_konsultasi' => 'required|date|after_or_equal:tanggal_onset|before_or_equal:today',
            'tanggal_lapor' => 'nullable|date|after_or_equal:tanggal_konsultasi|before_or_equal:today',
            'sumber_penularan' => 'nullable|in:lokal,import,unknown',
            'lokasi_penularan' => 'nullable|string',

            // Category D: Symptoms (boolean + date fields)
            'gejala_demam' => 'nullable|boolean',
            'gejala_batuk' => 'nullable|boolean',
            'gejala_pilek' => 'nullable|boolean',
            'gejala_sakit_kepala' => 'nullable|boolean',
            'gejala_mual' => 'nullable|boolean',
            'gejala_muntah' => 'nullable|boolean',
            'gejala_diare' => 'nullable|boolean',
            'gejala_ruam' => 'nullable|boolean',
            'gejala_sesak_napas' => 'nullable|boolean',
            'gejala_nyeri_otot' => 'nullable|boolean',
            'gejala_nyeri_sendi' => 'nullable|boolean',
            'gejala_lemas' => 'nullable|boolean',
            'gejala_kehilangan_nafsu_makan' => 'nullable|boolean',
            'gejala_mata_merah' => 'nullable|boolean',
            'gejala_pembengkakan_kelenjar' => 'nullable|boolean',
            'gejala_kejang' => 'nullable|boolean',
            'gejala_penurunan_kesadaran' => 'nullable|boolean',
            // Symptoms — Google Form additions
            'tanggal_demam' => 'nullable|date|before_or_equal:today',
            'gejala_adenopathy' => 'nullable|boolean',
            'gejala_arthralgia' => 'nullable|boolean',
            'gejala_kehamilan' => 'nullable|boolean',
            'gejala_lainnya' => 'nullable|string',
            // Difteri-specific symptom dates
            'tanggal_leher_bengkak' => 'nullable|date|before_or_equal:today',
            'tanggal_sesak_nafas' => 'nullable|date|before_or_equal:today',
            'tanggal_pseudomembran' => 'nullable|date|before_or_equal:today',
            // Pertusis
            'tanggal_apnea' => 'nullable|date|before_or_equal:today',

            // Category D2: Komplikasi
            'komplikasi_diare' => 'nullable|boolean',
            'komplikasi_kebutaan' => 'nullable|boolean',
            'komplikasi_pneumonia' => 'nullable|boolean',
            'komplikasi_malnutrisi' => 'nullable|boolean',
            'komplikasi_bronchopneumonia' => 'nullable|boolean',
            'komplikasi_otitis_media' => 'nullable|boolean',
            'komplikasi_encephalitis' => 'nullable|boolean',
            'komplikasi_ulkus_mukosa_mulut' => 'nullable|boolean',

            // Category D3: Vitamin A & Status Gizi
            'vitamin_a' => 'nullable|in:ya,tidak,tidak_tahu',
            'berat_badan' => 'nullable|numeric|min:0|max:300',
            'tinggi_badan' => 'nullable|numeric|min:0|max:300',
            'status_gizi' => 'nullable|in:baik,kurang,buruk,lebih',

            // Category D4: Pengobatan
            'jenis_antibiotik' => 'nullable|string|max:255',
            'dosis_ads' => 'nullable|string|max:255',
            'obat_lainnya' => 'nullable|string',

            // Category D5: AFP/Polio
            'kelumpuhan_akut' => 'nullable|in:ya,tidak',
            'kelumpuhan_flaccid' => 'nullable|in:ya,tidak',
            'kelumpuhan_rudapaksa' => 'nullable|in:ya,tidak',

            // Category D6: Diagnosis & Pemeriksaan Fisik
            'diagnosis' => 'nullable|string|max:255',
            'tanda_tungkai_kanan' => 'nullable|string|max:255',
            'tanda_tungkai_kiri' => 'nullable|string|max:255',
            'tanda_lengan_kanan' => 'nullable|string|max:255',
            'tanda_lengan_kiri' => 'nullable|string|max:255',
            'kekuatan_otot' => 'nullable|integer|min:0|max:5',
            'lokasi_kelemahan_lain' => 'nullable|string',
            'tanda_penyakit_observasi' => 'nullable|string',

            // Category D7: Kontak Polio
            'kontak_polio_oral' => 'nullable|in:ya,tidak,tidak_tahu',

            // Category D8: Sanitasi
            'jamban_sendiri' => 'nullable|in:ya,tidak',
            'jamban_saluran_kedap' => 'nullable|in:ya,tidak',
            'jenis_jamban' => 'nullable|string|max:100',
            'selalu_gunakan_jamban' => 'nullable|in:ya,tidak,kadang_kadang',
            'pembuangan_diapers' => 'nullable|string|max:255',

            // Category D9: Dokter
            'nama_dokter' => 'nullable|string|max:255',
            'no_telp_dokter' => 'nullable|string|max:20',
            'diagnosis_dokter' => 'nullable|string|max:255',

            // Category D10: Tempat Berobat
            'tempat_berobat' => 'nullable|string',
            'nama_rs' => 'nullable|string|max:255',
            'tanggal_kunjungan_rs' => 'nullable|date|before_or_equal:today',
            'nama_fktp' => 'nullable|string|max:255',
            'tanggal_kunjungan_fktp' => 'nullable|date|before_or_equal:today',
            'nama_pengobatan_tradisional' => 'nullable|string|max:255',
            'tanggal_kunjungan_tradisional' => 'nullable|date|before_or_equal:today',

            // Category E: History
            'riwayat_perjalanan' => 'nullable|string',
            'riwayat_imunisasi' => 'nullable|in:lengkap,tidak_lengkap,tidak_tahu,tidak_ada',
            'tanggal_imunisasi_terakhir' => 'nullable|date|before_or_equal:tanggal_onset',
            // Immunization — Google Form detail
            'imunisasi_1' => 'nullable|string|max:255',
            'imunisasi_2' => 'nullable|string|max:255',
            'imunisasi_3' => 'nullable|string|max:255',
            'imunisasi_4' => 'nullable|string|max:255',
            'imunisasi_5' => 'nullable|string|max:255',
            'sumber_informasi_imunisasi' => 'nullable|string|max:255',
            'alasan_imunisasi_tidak_lengkap' => 'nullable|string',

            // Category F: Laboratory
            'status_lab' => 'nullable|in:belum_diperiksa,proses,positif,negatif',
            'tanggal_pengambilan_spesimen' => 'nullable|date|after_or_equal:tanggal_onset|before_or_equal:today',
            'jenis_spesimen' => 'nullable|string|max:100',
            'hasil_lab' => 'nullable|string',
            'tanggal_hasil_lab' => [
                'nullable',
                'date',
                'after_or_equal:tanggal_pengambilan_spesimen',
                'before_or_equal:today',
                'required_if:status_lab,positif,negatif'
            ],
            // Lab — Google Form additional specimens
            'jenis_spesimen_2' => 'nullable|string|max:100',
            'tanggal_spesimen_2' => 'nullable|date|before_or_equal:today',
            'jenis_spesimen_3' => 'nullable|string|max:100',
            'tanggal_spesimen_3' => 'nullable|date|before_or_equal:today',

            // Category G: Management (Required fields)
            'status_rawat' => 'required|in:rawat_jalan,rawat_inap,isolasi_mandiri,rujuk',
            'nama_faskes_rawat' => 'required|string|max:255',
            'tanggal_masuk_rawat' => 'nullable|date|after_or_equal:tanggal_onset|before_or_equal:today',
            'tanggal_keluar_rawat' => 'nullable|date|after_or_equal:tanggal_masuk_rawat|before_or_equal:today',

            // Category H: Final Status
            'kondisi_akhir' => 'nullable|in:sembuh,meninggal,dalam_perawatan,pindah,unknown',
            'tanggal_kondisi_akhir' => 'nullable|date|after_or_equal:tanggal_onset|before_or_equal:today',
            'penyebab_kematian' => 'required_if:kondisi_akhir,meninggal|nullable|string',

            // Category I: Contact Investigation
            'jumlah_kontak_serumah' => 'nullable|integer|min:0',
            'jumlah_kontak_diluar_rumah' => 'nullable|integer|min:0',
            'jumlah_kontak_bergejala' => 'nullable|integer|min:0',
            'tindak_lanjut_kontak' => 'nullable|string',
            // Contact — Google Form additions
            'keluarga_sakit_sama' => 'nullable|in:ya,tidak',
            'jumlah_keluarga_sakit' => 'nullable|integer|min:0',
            'riwayat_bepergian' => 'nullable|in:ya,tidak',
            'lokasi_bepergian' => 'nullable|string|max:255',
            'tanggal_bepergian' => 'nullable|date|before_or_equal:today',

            // Category TN: Tetanus Neonatorum
            'lama_tinggal_desa' => 'nullable|string|max:100',
            'bayi_lahir_hidup' => 'nullable|in:ya,tidak',
            'umur_bayi_meninggal_hari' => 'nullable|integer|min:0',
            'bayi_menangis_lahir' => 'nullable|in:ya,tidak,tidak_tahu',
            'tanda_kelahiran_hidup' => 'nullable|in:ya,tidak,tidak_tahu',
            'bayi_bisa_menyusu' => 'nullable|in:ya,tidak,tidak_tahu',
            'bayi_mulut_mencucu' => 'nullable|in:ya,tidak,tidak_tahu',
            'bayi_mudah_kejang' => 'nullable|in:ya,tidak,tidak_tahu',
            'jumlah_kunjungan_anc' => 'nullable|integer|min:0|max:50',
            'tempat_pemeriksaan_hamil' => 'nullable|string|max:255',
            'pemeriksa_kehamilan' => 'nullable|string|max:255',
            'tempat_persalinan' => 'nullable|string|max:255',
            'usia_kehamilan_bulan' => 'nullable|integer|min:1|max:12',
            'penolong_persalinan' => 'nullable|string|max:255',
            'alat_potong_tali_pusat' => 'nullable|string|max:255',
            'perawatan_tali_pusat' => 'nullable|string|max:255',
            'keadaan_ibu_saat_ini' => 'nullable|string|max:255',

            // Category J: Metadata
            'status_kasus' => 'nullable|in:suspected,probable,confirmed,discarded',
            'id_faskes_pelapor' => 'nullable|integer|exists:puskesmas,id',
            'catatan_tambahan' => 'nullable|string',

            // MoD: Imunisasi per antigen
            'imunisasi' => 'nullable|array',
            'imunisasi.*.diberikan' => 'nullable|in:ya,tidak,tidak_tahu',
            'imunisasi.*.sumber_informasi' => 'nullable|string|max:255',
            'imunisasi.*.tanggal_imunisasi' => 'nullable|date|before_or_equal:today',

            // MoD: Faskes berobat
            'faskes_berobat' => 'nullable|array',
            'faskes_berobat.*.jenis_faskes' => 'nullable|in:rs,puskesmas,klinik,pengobatan_tradisional,lainnya',
            'faskes_berobat.*.nama_faskes' => 'nullable|string|max:255',
            'faskes_berobat.*.tanggal_berobat' => 'nullable|date|before_or_equal:today',
            'faskes_berobat.*.jenis_perawatan' => 'nullable|in:inap,jalan',
            'faskes_berobat.*.tanggal_keluar' => 'nullable|date|before_or_equal:today',

            // MoD: Spesimen
            'spesimen' => 'nullable|array',
            'spesimen.*.jenis_spesimen' => 'nullable|string|max:100',
            'spesimen.*.tanggal_ambil_spesimen' => 'nullable|date|before_or_equal:today',
            'spesimen.*.tanggal_kirim_sampel' => 'nullable|date|before_or_equal:today',
            'spesimen.*.tanggal_terima_lab' => 'nullable|date|before_or_equal:today',
            'spesimen.*.status_pemeriksaan' => 'nullable|string|max:100',
            'spesimen.*.id_jenis_kasus_terkonfirmasi' => 'nullable|integer|exists:jenis_kasus_epidemiologi,id',
            'spesimen.*.nama_variant_genotype' => 'nullable|string|max:255',

            // MoD: Kontak erat
            'kontak_erat' => 'nullable|array',
            'kontak_erat.*.nama' => 'nullable|string|max:255',
            'kontak_erat.*.hubungan' => 'nullable|string|max:100',
            'kontak_erat.*.no_telepon' => 'nullable|string|max:20',
            'kontak_erat.*.alamat' => 'nullable|string',
            'kontak_erat.*.tanggal_kontak_terakhir' => 'nullable|date|before_or_equal:today',
            'kontak_erat.*.ada_gejala' => 'nullable|boolean',
            'kontak_erat.*.catatan' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom validation messages
     *
     * @return array
     */
    public function messages()
    {
        return [
            'no_registrasi.unique' => 'No. Epid sudah terdaftar',
            'nik.required' => 'NIK wajib diisi',
            'nik.size' => 'NIK harus 16 digit',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi',
            'tanggal_lahir.before' => 'Tanggal lahir harus sebelum hari ini',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih',
            'alamat_lengkap.required' => 'Alamat lengkap wajib diisi',
            'id_kec.required' => 'Kecamatan wajib dipilih',
            'id_kel.required' => 'Kelurahan wajib dipilih',
            'id_rt.required' => 'RT wajib dipilih',

            'nama_pelapor.required' => 'Nama pelapor wajib diisi',

            'id_jenis_kasus.required' => 'Jenis kasus wajib dipilih',
            'tanggal_onset.required' => 'Tanggal onset wajib diisi',
            'tanggal_onset.after_or_equal' => 'Tanggal onset harus setelah tanggal lahir',
            'tanggal_konsultasi.required' => 'Tanggal konsultasi wajib diisi',
            'tanggal_konsultasi.after_or_equal' => 'Tanggal konsultasi harus setelah atau sama dengan tanggal onset',
            'tanggal_lapor.after_or_equal' => 'Tanggal lapor harus setelah atau sama dengan tanggal konsultasi',

            'tanggal_hasil_lab.required_if' => 'Tanggal hasil lab wajib diisi jika status lab positif atau negatif',

            'status_rawat.required' => 'Status rawat wajib dipilih',
            'nama_faskes_rawat.required' => 'Nama faskes rawat wajib diisi',
            'tanggal_keluar_rawat.after_or_equal' => 'Tanggal keluar rawat harus setelah atau sama dengan tanggal masuk rawat',

            'penyebab_kematian.required_if' => 'Penyebab kematian wajib diisi jika kondisi akhir meninggal',
        ];
    }
}
