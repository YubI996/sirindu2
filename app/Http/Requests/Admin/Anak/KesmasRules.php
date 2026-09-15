<?php

namespace App\Http\Requests\Admin\Anak;

use Illuminate\Validation\Rule;

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
            'komplikasi_neonatal'     => 'nullable|string',
        ];
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
