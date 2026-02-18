<?php

namespace App\Http\Requests\Epidemiologi;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveillanceCaseRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // Section A
            'no_registrasi'    => 'required|string|max:30|unique:surveillance_cases,no_registrasi',
            'nama_lengkap'     => 'required|string|max:100',
            'nik'              => 'nullable|string|size:16',
            'tanggal_lahir'    => 'required|date|before:today',
            'jenis_kelamin'    => 'required|in:L,P',
            'alamat_lengkap'   => 'required|string|max:255',
            'id_kec'           => 'nullable|exists:kecamatan,id',
            'id_kel'           => 'nullable|exists:kelurahan,id',
            'id_rt'            => 'nullable|exists:rt,id',
            'nama_orang_tua'   => 'nullable|string|max:100',
            'pekerjaan'        => 'nullable|string|max:100',
            // Section B
            'nama_pelapor'     => 'nullable|string|max:100',
            'jabatan_pelapor'  => 'nullable|string|max:100',
            'instansi_pelapor' => 'nullable|string|max:150',
            'telp_pelapor'     => 'nullable|string|max:20',
            // Section C
            'id_jenis_kasus'   => 'required|exists:jenis_kasus_epidemiologi,id',
            'tanggal_onset'    => 'required|date|after_or_equal:tanggal_lahir',
            'tanggal_konsultasi' => 'nullable|date|after_or_equal:tanggal_onset',
            'tanggal_lapor'    => 'required|date|after_or_equal:tanggal_onset',
            'tempat_berobat'   => 'nullable|string|max:150',
            'diagnosa_awal'    => 'nullable|string|max:200',
            'catatan_kasus'    => 'nullable|string',
            // Section D
            'gejala_demam'               => 'nullable|boolean',
            'gejala_ruam'                => 'nullable|boolean',
            'gejala_batuk'               => 'nullable|boolean',
            'gejala_pilek'               => 'nullable|boolean',
            'gejala_konjungtivitis'      => 'nullable|boolean',
            'gejala_sesak_napas'         => 'nullable|boolean',
            'gejala_nyeri_tenggorokan'   => 'nullable|boolean',
            'gejala_membran_tenggorokan' => 'nullable|boolean',
            'gejala_kejang'              => 'nullable|boolean',
            'gejala_lumpuh_layuh'        => 'nullable|boolean',
            'gejala_kaku_rahang'         => 'nullable|boolean',
            'gejala_spasme'              => 'nullable|boolean',
            'gejala_tali_pusat'          => 'nullable|boolean',
            'gejala_diare'               => 'nullable|boolean',
            'gejala_muntah'              => 'nullable|boolean',
            'gejala_pendarahan'          => 'nullable|boolean',
            'gejala_nyeri_sendi'         => 'nullable|boolean',
            'suhu_tubuh'                 => 'nullable|numeric|min:30|max:45',
            'gejala_lainnya'             => 'nullable|string',
            // Section E
            'riwayat_imunisasi'          => 'nullable|string',
            'riwayat_perjalanan'         => 'nullable|string',
            'riwayat_kontak'             => 'nullable|string',
            'riwayat_penyakit_dahulu'    => 'nullable|string',
            // Section F
            'status_lab'                 => 'required|in:belum,pending,positif,negatif,tidak_dilakukan',
            'jenis_pemeriksaan_lab'      => 'nullable|string|max:200',
            'tanggal_pengambilan_sampel' => 'nullable|date',
            'tanggal_hasil_lab'          => 'nullable|date|required_if:status_lab,positif|required_if:status_lab,negatif',
            'hasil_lab'                  => 'nullable|string',
            // Section G
            'status_rawat'               => 'required|in:rawat_jalan,rawat_inap,rujukan,tidak_berobat',
            'nama_faskes'                => 'nullable|string|max:150',
            'tanggal_masuk_rs'           => 'nullable|date|required_if:status_rawat,rawat_inap',
            'tanggal_keluar_rs'          => 'nullable|date|after_or_equal:tanggal_masuk_rs',
            'terapi_pengobatan'          => 'nullable|string',
            // Section H
            'kondisi_akhir'              => 'required|in:sembuh,dalam_perawatan,meninggal,tidak_diketahui',
            'tanggal_kondisi_akhir'      => 'nullable|date',
            'penyebab_kematian'          => 'nullable|string|max:200|required_if:kondisi_akhir,meninggal',
            // Section I
            'jumlah_kontak_erat'         => 'nullable|integer|min:0',
            'kontak_dipantau'            => 'nullable|integer|min:0',
            'kontak_positif'             => 'nullable|integer|min:0',
            'keterangan_kontak'          => 'nullable|string',
            // Section J
            'status_kasus'               => 'required|in:suspek,probable,konfirmasi,discarded',
        ];
    }

    public function messages()
    {
        return [
            'no_registrasi.required'    => 'Nomor registrasi wajib diisi.',
            'no_registrasi.unique'      => 'Nomor registrasi sudah digunakan.',
            'nama_lengkap.required'     => 'Nama lengkap wajib diisi.',
            'tanggal_lahir.required'    => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.before'      => 'Tanggal lahir harus sebelum hari ini.',
            'jenis_kelamin.required'    => 'Jenis kelamin wajib dipilih.',
            'alamat_lengkap.required'   => 'Alamat lengkap wajib diisi.',
            'id_jenis_kasus.required'   => 'Jenis kasus wajib dipilih.',
            'tanggal_onset.required'    => 'Tanggal onset gejala wajib diisi.',
            'tanggal_lapor.required'    => 'Tanggal lapor wajib diisi.',
            'tanggal_hasil_lab.required_if' => 'Tanggal hasil lab wajib diisi jika status lab positif/negatif.',
            'tanggal_masuk_rs.required_if'  => 'Tanggal masuk RS wajib diisi untuk kasus rawat inap.',
            'penyebab_kematian.required_if' => 'Penyebab kematian wajib diisi jika kondisi akhir meninggal.',
            'status_kasus.required'     => 'Status/klasifikasi kasus wajib dipilih.',
        ];
    }
}
