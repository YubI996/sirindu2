<?php

namespace App\Http\Requests\Admin\Anak;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

/**
 * Aturan validasi field Kesmas (spec §3.3). Semua nullable — form/klien lama yang tidak
 * mengirim field ini tetap lolos. Dipakai storeAnakRequest, AdminController::updateAnak,
 * AdminController::storeDataAnak, AdminController::updateDataAnak.
 *
 * array_keys(anak()) dan array_keys(kunjungan()) juga menjadi daftar kolom yang
 * dibaca AnakRepository — jangan menaruh key yang bukan kolom di sini.
 */
final class KesmasRules
{
    /** Boolean per anak yang diisi lewat select tiga keadaan ('' / 1 / 0). */
    public const BOOL_ANAK = ['air_bersih', 'jamban_sehat', 'merokok_keluarga', 'imd', 'riwayat_kek_ibu'];

    /**
     * @param string|null $penolongLama nilai penolong_lahir yang sudah tersimpan (import lama)
     *                                  agar tetap sah saat edit meski tidak ada di daftar config
     */
    public static function anak(?string $penolongLama = null): array
    {
        $c = config('kesmas');
        $penolong = array_values(array_unique(array_merge($c['penolong_lahir'], array_filter([$penolongLama]))));
        $skrining = Rule::in(array_keys($c['skrining']));

        return [
            'no_id_epus'              => 'nullable|string|max:50',
            'fktp_bpjs'               => 'nullable|string|max:100',
            'air_bersih'              => 'nullable|boolean',
            'jamban_sehat'            => 'nullable|boolean',
            'merokok_keluarga'        => 'nullable|boolean',
            'status_tk_paud'          => ['nullable', Rule::in($c['status_tk_paud'])],
            'penyakit_penyerta'       => 'nullable|string|max:255',
            'pjb'                     => 'nullable|string|max:100',
            'bbl'                     => 'nullable|numeric|min:0',
            'pbl'                     => 'nullable|numeric|min:0',
            'lk_lahir'                => 'nullable|numeric|min:0',
            'usia_kehamilan_lahir'    => 'nullable|integer|between:20,45',
            'tempat_bersalin'         => 'nullable|string|max:150',
            'jenis_persalinan'        => ['nullable', Rule::in($c['jenis_persalinan'])],
            'penolong_lahir'          => ['nullable', Rule::in($penolong)],
            'imd'                     => 'nullable|boolean',
            'riwayat_kek_ibu'         => 'nullable|boolean',
            'komplikasi_persalinan'   => 'nullable|string|max:255',
            'skrining_shk'            => ['nullable', $skrining],
            'skrining_shak'           => ['nullable', $skrining],
            'skrining_g6pd'           => ['nullable', $skrining],
            'pemeriksaan_hepatitis_b' => ['nullable', Rule::in(array_keys($c['hepatitis_b']))],
            // HBIG: field tanggal, BUKAN jenis vaksin — tak ikut IDL/jadwal/kejar (spec 2026-10-02 §5.1).
            'tgl_hbig'                => 'nullable|date|after_or_equal:tgl_lahir|before_or_equal:today',
            'komplikasi_neonatal'     => 'nullable|string',
        ];
    }

    /**
     * Tanda Sasaran Balita Kesmas (spec 2026-10-02 §5.2). SENGAJA di luar anak(): kunci anak()
     * dibaca kolomKesmas() sebagai daftar kolom, sedangkan tanda ini punya aturan simpan sendiri
     * (anak NULL + tak dicentang di form Edit tetap NULL) — lihat AnakRepository::sasaranEdit().
     */
    public static function sasaran(): array
    {
        return ['sasaran_balita_kesmas' => 'nullable|boolean'];
    }

    /** Kalimat galat per nama aturan; `:attribute` diisi label dari atribut(). */
    private const PESAN = [
        'boolean'         => ':attribute harus dipilih Ya atau Tidak.',
        'date'            => ':attribute bukan tanggal yang valid.',
        'numeric'         => ':attribute harus berupa angka.',
        'integer'         => ':attribute harus berupa bilangan bulat.',
        'in'              => ':attribute berisi pilihan yang tidak dikenal.',
        'string'          => ':attribute harus berupa teks.',
        'min'             => ':attribute tidak boleh kurang dari :min.',
        'max'             => ':attribute maksimal :max karakter.',
        'between'         => ':attribute harus antara :min sampai :max.',
        'after_or_equal'  => ':attribute tidak boleh sebelum :date.',
        'before_or_equal' => ':attribute tidak boleh setelah hari ini.',
    ];

    /**
     * Pesan galat berbahasa Indonesia untuk field di $aturan saja (key `field.aturan`), jadi
     * pesan field non-Kesmas dalam validasi yang sama tidak ikut berubah. Tanpa ini galatnya
     * "The usia kehamilan lahir must be between 20 and 45." — Inggris, nama kolom mentah —
     * padahal field-nya ada di kartu collapse yang tertutup (audit Kesmas 2026-10-06, A11Y-002).
     *
     * @param array<string, string|array> $aturan salah satu dari anak()/sasaran()/kunjungan()
     */
    public static function pesan(array $aturan): array
    {
        $pesan = [];
        foreach ($aturan as $field => $rule) {
            foreach (is_string($rule) ? explode('|', $rule) : $rule as $bagian) {
                $nama = $bagian instanceof Rules\In ? 'in'
                    : (is_string($bagian) ? strtolower(explode(':', $bagian, 2)[0]) : null);
                if ($nama !== null && isset(self::PESAN[$nama])) {
                    $pesan["$field.$nama"] = self::PESAN[$nama];
                }
            }
        }

        return $pesan;
    }

    /** Label field = teks label di form, supaya petugas mengenali field yang dimaksud galat. */
    public static function atribut(): array
    {
        $atribut = [
            'tgl_lahir'               => 'tanggal lahir',
            'no_id_epus'              => 'No. ID ePuskesmas',
            'fktp_bpjs'               => 'FKTP BPJS terdaftar',
            'air_bersih'              => 'Akses air bersih di rumah',
            'jamban_sehat'            => 'Jamban sehat',
            'merokok_keluarga'        => 'Anggota keluarga serumah yang merokok',
            'status_tk_paud'          => 'Keikutsertaan TK/PAUD',
            'penyakit_penyerta'       => 'Riwayat penyakit penyerta',
            'pjb'                     => 'Penyakit Jantung Bawaan (PJB)',
            'bbl'                     => 'Berat lahir',
            'pbl'                     => 'Panjang lahir',
            'lk_lahir'                => 'Lingkar kepala lahir',
            'usia_kehamilan_lahir'    => 'Usia kehamilan saat lahir',
            'tempat_bersalin'         => 'Tempat bersalin',
            'jenis_persalinan'        => 'Jenis persalinan',
            'penolong_lahir'          => 'Penolong persalinan',
            'imd'                     => 'Inisiasi Menyusu Dini (IMD)',
            'riwayat_kek_ibu'         => 'Riwayat KEK ibu saat hamil',
            'komplikasi_persalinan'   => 'Komplikasi persalinan',
            'skrining_shk'            => 'Skrining Hipotiroid Kongenital (SHK)',
            'skrining_shak'           => 'Skrining Hiperplasia Adrenal Kongenital (SHAK)',
            'skrining_g6pd'           => 'Skrining G6PD',
            'pemeriksaan_hepatitis_b' => 'Pemeriksaan Hepatitis B',
            'tgl_hbig'                => 'Tanggal pemberian HBIG',
            'komplikasi_neonatal'     => 'Pelayanan / tindakan komplikasi neonatal',
            'sasaran_balita_kesmas'   => 'Sasaran Balita Kesmas',
            'tgl_penanda_ckg'         => 'Tanggal penanda CKG',
            'pemeriksaan_gigi'        => 'Hasil pemeriksaan gigi',
            'rujukan'                 => 'Rujukan',
            'mt_pangan_lokal'         => 'Makanan tambahan (MT) pangan lokal',
            'catatan_pengukuran'      => 'Catatan perkembangan / hasil pemeriksaan',
            'pemeriksaan_lainnya'     => 'Hasil pemeriksaan kesehatan lainnya',
            'pola_makan'              => 'Pola makan anak',
            'pola_asuh'               => 'Pola asuh',
            'intervensi'              => 'Intervensi',
        ];
        foreach (config('kesmas.layanan') as $kolom => $def) {
            $atribut[$kolom] = $def['label'];
        }

        return $atribut;
    }

    /** Layanan Kesmas per kunjungan (data_anak). Checkbox = nullable|boolean. */
    public static function kunjungan(): array
    {
        $c = config('kesmas');
        $rules = [
            'tgl_penanda_ckg'     => 'nullable|date',
            'pemeriksaan_gigi'    => ['nullable', Rule::in($c['pemeriksaan_gigi'])],
            'rujukan'             => ['nullable', Rule::in($c['rujukan'])],
            'mt_pangan_lokal'     => 'nullable|string|max:100',
            'catatan_pengukuran'  => 'nullable|string',
            'pemeriksaan_lainnya' => 'nullable|string',
            'pola_makan'          => 'nullable|string',
            'pola_asuh'           => 'nullable|string',
            'intervensi'          => 'nullable|string',
        ];
        foreach (array_keys($c['layanan']) as $kolom) {
            $rules[$kolom] = 'nullable|boolean';
        }

        return $rules;
    }
}
