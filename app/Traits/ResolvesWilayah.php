<?php

namespace App\Traits;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;

/**
 * Trait untuk resolve nama wilayah (kecamatan, kelurahan, RT) dengan fuzzy matching.
 *
 * Urutan lookup:
 *   1. Exact match (uppercase)
 *   2. Exact match setelah normalisasi (strip prefix, collapse spasi)
 *   3. Fuzzy match similar_text >= FUZZY_THRESHOLD %
 *   4. Buat baru (hanya jika benar-benar tidak ada kemiripan)
 */
trait ResolvesWilayah
{
    protected array $kecamatanCache = [];
    protected array $kelurahanCache = [];
    protected array $rtCache = [];

    /** Threshold kemiripan (0–100). Nilai < threshold → buat data baru. */
    private const FUZZY_THRESHOLD = 80.0;

    /** Prefix wilayah yang sering muncul di data Excel dan perlu distrip sebelum dibandingkan. */
    private const WILAYAH_PREFIXES = [
        'KELURAHAN ', 'KECAMATAN ', 'KEL. ', 'KEC. ', 'KEL ', 'KEC ',
        'DESA ', 'DUSUN ', 'KAMPUNG ', 'KP. ', 'KP ',
    ];

    /**
     * Muat cache wilayah dari DB. Panggil di constructor masing-masing import.
     */
    protected function initWilayahCache(): void
    {
        $this->kecamatanCache = Kecamatan::pluck('id', 'name')
            ->mapWithKeys(fn($id, $name) => [strtoupper(trim($name)) => $id])
            ->toArray();

        $this->kelurahanCache = Kelurahan::pluck('id', 'name')
            ->mapWithKeys(fn($id, $name) => [strtoupper(trim($name)) => $id])
            ->toArray();
    }

    /**
     * Normalisasi nama wilayah: uppercase, collapse spasi, strip prefix umum.
     */
    protected function normalizeWilayah(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = preg_replace('/\s+/', ' ', $name);

        foreach (self::WILAYAH_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $name = trim(substr($name, strlen($prefix)));
                break;
            }
        }

        return $name;
    }

    /**
     * Cari entri terbaik di $cache yang mirip dengan $normalized.
     * Return ID jika similarity >= FUZZY_THRESHOLD, null jika tidak ada.
     */
    protected function findFuzzyMatch(string $normalized, array $cache): ?int
    {
        if (empty($normalized) || empty($cache)) return null;

        $bestScore = 0.0;
        $bestId    = null;

        foreach ($cache as $cachedName => $id) {
            $cachedNorm = $this->normalizeWilayah($cachedName);
            similar_text($normalized, $cachedNorm, $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $bestId    = $id;
            }
        }

        return ($bestScore >= self::FUZZY_THRESHOLD) ? $bestId : null;
    }

    /**
     * Resolve kecamatan: exact → fuzzy → buat baru.
     */
    protected function resolveKecamatan(string $name): ?int
    {
        $key = strtoupper(trim($name));
        if (empty($key)) return null;

        // 1. Exact match
        if (isset($this->kecamatanCache[$key])) {
            return $this->kecamatanCache[$key];
        }

        // 2. Exact match setelah normalisasi
        $normalized = $this->normalizeWilayah($name);
        foreach ($this->kecamatanCache as $cachedName => $id) {
            if ($this->normalizeWilayah($cachedName) === $normalized) {
                $this->kecamatanCache[$key] = $id;
                return $id;
            }
        }

        // 3. Fuzzy match
        $id = $this->findFuzzyMatch($normalized, $this->kecamatanCache);
        if ($id !== null) {
            $this->kecamatanCache[$key] = $id;
            return $id;
        }

        // 4. Buat baru
        $kec = Kecamatan::firstOrCreate(['name' => ucwords(strtolower(trim($name)))]);
        $this->kecamatanCache[$key] = $kec->id;

        return $kec->id;
    }

    /**
     * Resolve kelurahan: exact → fuzzy → buat baru.
     * $idKec digunakan hanya saat membuat record baru.
     */
    protected function resolveKelurahan(string $name, ?int $idKec): ?int
    {
        $key = strtoupper(trim($name));
        if (empty($key)) return null;

        // 1. Exact match
        if (isset($this->kelurahanCache[$key])) {
            return $this->kelurahanCache[$key];
        }

        // 2. Exact match setelah normalisasi
        $normalized = $this->normalizeWilayah($name);
        foreach ($this->kelurahanCache as $cachedName => $id) {
            if ($this->normalizeWilayah($cachedName) === $normalized) {
                $this->kelurahanCache[$key] = $id;
                return $id;
            }
        }

        // 3. Fuzzy match
        $id = $this->findFuzzyMatch($normalized, $this->kelurahanCache);
        if ($id !== null) {
            $this->kelurahanCache[$key] = $id;
            return $id;
        }

        // 4. Buat baru
        $attrs = ['name' => ucwords(strtolower(trim($name)))];
        if ($idKec) $attrs['id_kecamatan'] = $idKec;

        $kel = Kelurahan::firstOrCreate($attrs);
        $this->kelurahanCache[$key] = $kel->id;

        return $kel->id;
    }

    /**
     * Resolve RT berdasarkan nama dan kelurahan — TIDAK auto-create.
     */
    protected function resolveRt(string $name, ?int $idKel): ?int
    {
        $key = strtoupper(trim($name));
        if (empty($key) || !$idKel) return null;

        $cacheKey = $key . '_' . $idKel;
        if (!array_key_exists($cacheKey, $this->rtCache)) {
            $rt = Rt::where('id_kelurahan', $idKel)
                ->where('name', 'like', '%' . trim($name) . '%')
                ->first();
            $this->rtCache[$cacheKey] = $rt?->id;
        }

        return $this->rtCache[$cacheKey];
    }
}
