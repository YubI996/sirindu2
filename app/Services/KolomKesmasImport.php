<?php

namespace App\Services;

use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Models\Anak;
use App\Support\NilaiSelKesmas;
use Illuminate\Support\Facades\Validator;

/**
 * Kolom Kesmas di Import Data Kesmas (spec 2026-10-15-import-kesmas-design.md §3): sel → nilai, lalu
 * divalidasi dengan KesmasRules — sumber yang sama dengan form. Sel tak sah hanya membatalkan kolom itu.
 */
final class KolomKesmasImport
{
    /** Pilihan berlabel tanpa kunci: kolom => kunci config('kesmas'). */
    private const PILIHAN = [
        'status_tk_paud' => 'status_tk_paud', 'jenis_persalinan' => 'jenis_persalinan',
        'penolong_lahir' => 'penolong_lahir', 'pemeriksaan_gigi' => 'pemeriksaan_gigi', 'rujukan' => 'rujukan',
    ];

    /** Pilihan berkunci (enum di DB): kolom => kunci config('kesmas'). */
    private const PILIHAN_KUNCI = [
        'skrining_shk' => 'skrining', 'skrining_shak' => 'skrining', 'skrining_g6pd' => 'skrining',
        'pemeriksaan_hepatitis_b' => 'hepatitis_b',
    ];

    private const TANGGAL = ['tgl_hbig', 'tgl_penanda_ckg'];
    private const ANGKA = ['bbl', 'pbl', 'lk_lahir', 'usia_kehamilan_lahir'];

    /** @return string[] */
    public static function kolom(string $tingkat): array
    {
        return array_keys($tingkat === 'anak' ? KesmasRules::anak() : KesmasRules::kunjungan());
    }

    /** @return string[]|null pilihan tampil untuk dropdown template; null = isian bebas */
    public static function pilihan(string $kolom): ?array
    {
        if (self::yaTidak($kolom)) {
            return ['Ya', 'Tidak'];
        }
        if (isset(self::PILIHAN[$kolom])) {
            return config('kesmas.'.self::PILIHAN[$kolom]);
        }
        if (isset(self::PILIHAN_KUNCI[$kolom])) {
            return array_values(config('kesmas.'.self::PILIHAN_KUNCI[$kolom]));
        }

        return null;
    }

    /**
     * @param 'anak'|'kunjungan'      $tingkat
     * @param array<string,mixed>     $sel         kolom => sel mentah, hanya yang tidak kosong
     * @param callable(mixed):?string $bacaTanggal format tanggal berkas yang sudah dikunci importer
     * @return array{nilai: array<string,mixed>, salah: array<string,string>}
     */
    public static function ubah(string $tingkat, array $sel, Anak $anak, callable $bacaTanggal): array
    {
        $label = KesmasRules::atribut();
        $nilai = [];
        $salah = [];

        foreach ($sel as $kolom => $mentah) {
            $v = self::ubahSatu($kolom, $mentah, $anak, $bacaTanggal);
            if ($v === null) {
                $pilihan = self::pilihan($kolom);
                $salah[$kolom] = ($label[$kolom] ?? $kolom)." — nilai '".NilaiSelKesmas::teks($mentah)."' tidak dikenal"
                    .($pilihan !== null ? ' (pilihan: '.implode(', ', $pilihan).')' : '');
            } else {
                $nilai[$kolom] = $v;
            }
        }

        if ($nilai !== []) {
            $aturan = array_intersect_key(
                $tingkat === 'anak' ? KesmasRules::anak($anak->penolong_lahir) : KesmasRules::kunjungan(),
                $nilai
            );
            $validator = Validator::make(
                $nilai + ['tgl_lahir' => substr((string) $anak->tgl_lahir, 0, 10)],
                $aturan,
                KesmasRules::pesan($aturan),
                $label
            );
            foreach ($validator->errors()->messages() as $kolom => $pesan) {
                $salah[$kolom] = rtrim($pesan[0], '.')." (nilai '".NilaiSelKesmas::teks($sel[$kolom])."')";
                unset($nilai[$kolom]);
            }
        }

        return ['nilai' => $nilai, 'salah' => $salah];
    }

    private static function yaTidak(string $kolom): bool
    {
        return in_array($kolom, KesmasRules::BOOL_ANAK, true) || array_key_exists($kolom, config('kesmas.layanan'));
    }

    private static function ubahSatu(string $kolom, mixed $mentah, Anak $anak, callable $bacaTanggal): mixed
    {
        if (self::yaTidak($kolom)) {
            return NilaiSelKesmas::yaTidak($mentah);
        }
        if ($kolom === 'penolong_lahir') {
            // Nilai lama dari import terdahulu tetap sah (sama dengan KesmasRules::anak($penolongLama)).
            $daftar = array_values(array_unique(array_merge(config('kesmas.penolong_lahir'), array_filter([$anak->penolong_lahir]))));

            return NilaiSelKesmas::pilihan($mentah, $daftar);
        }
        if (isset(self::PILIHAN[$kolom])) {
            return NilaiSelKesmas::pilihan($mentah, config('kesmas.'.self::PILIHAN[$kolom]));
        }
        if (isset(self::PILIHAN_KUNCI[$kolom])) {
            return NilaiSelKesmas::pilihanBerkunci($mentah, config('kesmas.'.self::PILIHAN_KUNCI[$kolom]));
        }
        if (in_array($kolom, self::TANGGAL, true)) {
            return $bacaTanggal($mentah);
        }
        if (in_array($kolom, self::ANGKA, true)) {
            return NilaiSelKesmas::angka($mentah);
        }

        return NilaiSelKesmas::teks($mentah);
    }
}
