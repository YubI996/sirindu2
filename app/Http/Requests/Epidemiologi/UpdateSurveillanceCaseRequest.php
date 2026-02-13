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
                'required',
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

            // Category B: Reporter Identity (nama_pelapor required)
            'nama_pelapor' => 'required|string|max:255',
            'jabatan_pelapor' => 'nullable|string|max:100',
            'instansi_pelapor' => 'nullable|string|max:255',
            'telepon_pelapor' => 'nullable|string|max:20',

            // Category C: Case Data (Required fields)
            'id_jenis_kasus' => 'required|exists:jenis_kasus_epidemiologi,id',
            'kode_icd10' => 'nullable|string|max:10',
            'tanggal_onset' => 'required|date|after_or_equal:tanggal_lahir|before_or_equal:today',
            'tanggal_konsultasi' => 'required|date|after_or_equal:tanggal_onset|before_or_equal:today',
            'tanggal_lapor' => 'nullable|date|after_or_equal:tanggal_konsultasi|before_or_equal:today',
            'sumber_penularan' => 'nullable|in:lokal,import,unknown',
            'lokasi_penularan' => 'nullable|string',

            // Category D: Symptoms (All optional boolean, no validation needed)

            // Category E: History
            'riwayat_perjalanan' => 'nullable|string',
            'riwayat_imunisasi' => 'nullable|in:lengkap,tidak_lengkap,tidak_tahu,tidak_ada',
            'tanggal_imunisasi_terakhir' => 'nullable|date|before_or_equal:tanggal_onset',

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

            // Category J: Metadata
            'status_kasus' => 'nullable|in:suspected,probable,confirmed,discarded',
            'id_faskes_pelapor' => 'nullable|exists:users,id',
            'catatan_tambahan' => 'nullable|string',
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
            'no_registrasi.required' => 'Nomor registrasi wajib diisi',
            'no_registrasi.unique' => 'Nomor registrasi sudah terdaftar',
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
