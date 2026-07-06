<?php

namespace App\Support;

use App\Models\Kelurahan;

/**
 * Sumber tunggal pemetaan Kelurahan ⇄ Wilayah Kerja (Wilker) Puskesmas.
 *
 * Dipakai untuk:
 *  - Autofill field "Wilker Puskesmas" saat input/edit kasus (dari kelurahan).
 *  - Scoping data surveilans PD3I: user puskesmas hanya melihat kasus di
 *    kelurahan catchment puskesmas-nya (lihat SurveillanceCase::scopeVisibleTo).
 *
 * Kenapa keyed by kelurahan ID di runtime:
 *  Nama puskesmas (mis. "Bontang Utara I", Romawi) dan nama wilker di data
 *  (mis. "Bontang Utara 1", Arab) sering berbeda ejaan/format, begitu pula
 *  nama kelurahan antar sumber ("API-API" vs "Api Api", "BEREBAS TENGAH" vs
 *  "Berbas Tengah", "LOK TUAN" vs "Loktuan"). Karena itu setiap kelurahan DB
 *  dicocokkan SEKALI ke tabel kanonik di bawah via normalisasi + fuzzy match,
 *  lalu semua scoping bekerja atas kelurahan ID yang stabil.
 */
class WilkerPuskesmas
{
    /**
     * Pemetaan kanonik: nama kelurahan (ejaan resmi) → nama wilker (bentuk display).
     * Sinkron dengan WILKER_MAP di form-section-a.blade.php dan spec FR-002.
     */
    public const KELURAHAN_TO_WILKER = [
        'API-API'            => 'Bontang Utara 1',
        'BONTANG BARU'       => 'Bontang Utara 1',
        'GUNUNG ELAI'        => 'Bontang Utara 1',
        'BONTANG KUALA'      => 'Bontang Utara 1',
        'GUNTUNG'            => 'Bontang Utara 2',
        'LOK TUAN'           => 'Bontang Utara 2',
        'BELIMBING'          => 'Bontang Barat',
        'KANAAN'             => 'Bontang Barat',
        'GUNUNG TELIHAN'     => 'Bontang Barat',
        'BONTANG LESTARI'    => 'Bontang Lestari',
        'TANJUNG LAUT'       => 'Bontang Selatan 1',
        'TANJUNG LAUT INDAH' => 'Bontang Selatan 1',
        'SATIMPO'            => 'Bontang Selatan 1',
        'BERBAS PANTAI'      => 'Bontang Selatan 2',
        'BEREBAS TENGAH'     => 'Bontang Selatan 2',
    ];

    /** Ambang kemiripan fuzzy (0–100) untuk mencocokkan nama kelurahan DB ke tabel kanonik. */
    private const FUZZY_THRESHOLD = 85.0;

    /** Cache per-proses: kelurahan_id → nama wilker (display). */
    private static ?array $idToWilkerCache = null;

    /**
     * Normalisasi nama untuk perbandingan: uppercase, buang tanda baca jadi spasi,
     * rapikan spasi, strip prefix "PUSKESMAS ", dan ubah angka Romawi di akhir
     * (I/II/III) menjadi Arab (1/2/3). Sehingga "Puskesmas Bontang Utara I" dan
     * "Bontang Utara 1" sama-sama menjadi "BONTANG UTARA 1".
     */
    public static function normalizeName(string $name): string
    {
        $n = strtoupper(trim($name));
        $n = preg_replace('/[^A-Z0-9]+/', ' ', $n);
        $n = trim(preg_replace('/\s+/', ' ', $n));
        $n = preg_replace('/^PUSKESMAS\s+/', '', $n);

        // Angka Romawi standalone di akhir → Arab
        $n = preg_replace_callback('/\s(III|II|I)$/', function ($m) {
            return ' ' . ['I' => '1', 'II' => '2', 'III' => '3'][$m[1]];
        }, $n);

        return trim($n);
    }

    /**
     * Wilker (dinormalisasi) untuk sebuah nama puskesmas.
     * Mis. "Bontang Utara I" / "Puskesmas Bontang Utara 1" → "BONTANG UTARA 1".
     */
    public static function wilkerKeyForPuskesmasName(string $puskesmasName): string
    {
        return self::normalizeName($puskesmasName);
    }

    /**
     * Daftar nama kelurahan kanonik yang termasuk catchment sebuah puskesmas.
     * Pure (tanpa DB) — dipakai juga oleh test.
     *
     * @return list<string> nama kelurahan (ejaan tabel kanonik)
     */
    public static function catchmentKelurahanNames(string $puskesmasName): array
    {
        $wilkerKey = self::wilkerKeyForPuskesmasName($puskesmasName);

        $names = [];
        foreach (self::KELURAHAN_TO_WILKER as $kelName => $wilkerDisplay) {
            if (self::normalizeName($wilkerDisplay) === $wilkerKey) {
                $names[] = $kelName;
            }
        }

        return $names;
    }

    /**
     * Peta kelurahan_id → nama wilker (display), dibangun dari data DB sekali per proses.
     * Setiap kelurahan DB dicocokkan ke tabel kanonik via exact-normalized lalu fuzzy.
     *
     * @return array<int,string>
     */
    public static function kelurahanIdToWilker(): array
    {
        if (self::$idToWilkerCache !== null) {
            return self::$idToWilkerCache;
        }

        // Pra-hitung bentuk ternormalisasi dari kunci kanonik.
        $canonNorm = [];
        foreach (self::KELURAHAN_TO_WILKER as $kelName => $wilkerDisplay) {
            $canonNorm[$kelName] = self::normalizeName($kelName);
        }

        $map = [];
        foreach (Kelurahan::query()->get(['id', 'name']) as $kel) {
            $wilker = self::matchWilker((string) $kel->name, $canonNorm);
            if ($wilker !== null) {
                $map[(int) $kel->id] = $wilker;
            }
        }

        return self::$idToWilkerCache = $map;
    }

    /**
     * Cocokkan satu nama kelurahan DB ke wilker: exact-normalized → fuzzy ≥ threshold.
     *
     * @param array<string,string> $canonNorm  kelName kanonik → bentuk ternormalisasi
     */
    private static function matchWilker(string $dbName, array $canonNorm): ?string
    {
        $norm = self::normalizeName($dbName);
        if ($norm === '') {
            return null;
        }

        // 1. Exact match setelah normalisasi.
        foreach ($canonNorm as $kelName => $canon) {
            if ($canon === $norm) {
                return self::KELURAHAN_TO_WILKER[$kelName];
            }
        }

        // 2. Fuzzy match — ambil skor tertinggi di atas ambang.
        $bestScore = 0.0;
        $bestKel   = null;
        foreach ($canonNorm as $kelName => $canon) {
            similar_text($norm, $canon, $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $bestKel   = $kelName;
            }
        }

        return ($bestScore >= self::FUZZY_THRESHOLD && $bestKel !== null)
            ? self::KELURAHAN_TO_WILKER[$bestKel]
            : null;
    }

    /**
     * ID kelurahan dalam catchment sebuah puskesmas (untuk scoping query).
     *
     * @return list<int>
     */
    public static function catchmentKelurahanIds(string $puskesmasName): array
    {
        $wilkerKey = self::wilkerKeyForPuskesmasName($puskesmasName);

        $ids = [];
        foreach (self::kelurahanIdToWilker() as $id => $wilker) {
            if (self::normalizeName($wilker) === $wilkerKey) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Nama wilker (display) untuk sebuah kelurahan ID, atau '' jika tak dikenal.
     * Menggantikan resolveWilker lama yang berbasis pencocokan nama rapuh.
     */
    public static function wilkerForKelurahanId(?int $idKel): string
    {
        if (!$idKel) {
            return '';
        }

        return self::kelurahanIdToWilker()[$idKel] ?? '';
    }

    /** Reset cache — dipakai di test. */
    public static function flushCache(): void
    {
        self::$idToWilkerCache = null;
    }
}
