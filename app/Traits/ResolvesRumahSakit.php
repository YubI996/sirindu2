<?php

namespace App\Traits;

use App\Models\RumahSakit;

/**
 * Trait untuk mencocokkan nama faskes pelapor (instansi_pelapor dari file impor)
 * ke id master Rumah Sakit — dipakai untuk mengisi faskes_type='rs' + id_faskes
 * agar kasus hasil import terlihat oleh user surveilans_rs (scopeVisibleTo).
 *
 * Pencocokan sengaja KETAT (exact pada "core name" ternormalisasi), bukan fuzzy:
 * false-positive berarti satu RS bisa melihat data RS lain (kebocoran scoping).
 * Nama RS yang tak ada di master → null (kasus tetap Dinkes-only, aman).
 *
 * Normalisasi "core name":
 *   - uppercase, buang tanda baca
 *   - buang token generik faskes (RS, RSUD, RUMAH, SAKIT, …) dan lokasi (BONTANG, KOTA)
 *   - urutkan token yang tersisa → toleran terhadap beda urutan
 *     ("RS LNG Badak" == "RS Badak LNG")
 */
trait ResolvesRumahSakit
{
    /** Peta core-name ternormalisasi → id RS. */
    protected array $rsCache = [];

    /** Token generik yang dibuang sebelum membandingkan nama RS. */
    private const RS_NOISE_TOKENS = [
        'RS', 'RSU', 'RSUD', 'RSIA', 'RUMAH', 'SAKIT', 'UMUM', 'DAERAH',
        'BONTANG', 'KOTA',
    ];

    /**
     * Muat cache RS dari DB. Panggil di constructor pemakai.
     */
    protected function initRumahSakitCache(): void
    {
        $this->rsCache = $this->buildRsCache(
            RumahSakit::pluck('name', 'id')->toArray()
        );
    }

    /**
     * Bangun peta core-name → id dari daftar [id => name].
     * Terpisah dari initRumahSakitCache agar mudah diuji tanpa DB.
     *
     * @param array<int, string> $idToName
     * @return array<string, int>
     */
    protected function buildRsCache(array $idToName): array
    {
        $cache = [];
        foreach ($idToName as $id => $name) {
            $core = $this->normalizeRs((string) $name);
            if ($core !== '') {
                $cache[$core] = (int) $id;
            }
        }
        return $cache;
    }

    /**
     * Normalisasi nama RS menjadi "core name" ternormalisasi (token diurutkan).
     */
    protected function normalizeRs(string $name): string
    {
        $name = strtoupper(trim($name));
        // Ganti semua non-alfanumerik jadi spasi (buang titik, koma, dsb.)
        $name = preg_replace('/[^A-Z0-9]+/', ' ', $name);

        $tokens = array_filter(
            explode(' ', $name),
            fn($t) => $t !== '' && !in_array($t, self::RS_NOISE_TOKENS, true)
        );

        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * Resolve nama faskes pelapor ke id RS. Null jika kosong atau tak cocok master.
     */
    protected function resolveRumahSakit(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }

        $core = $this->normalizeRs($name);
        if ($core === '') {
            return null;
        }

        return $this->rsCache[$core] ?? null;
    }
}
