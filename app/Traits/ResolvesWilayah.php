<?php

namespace App\Traits;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use Illuminate\Support\Facades\Log;

/**
 * Trait untuk resolve nama wilayah (kecamatan, kelurahan, RT) dengan fuzzy matching.
 *
 * Urutan lookup:
 *   1. Exact match (uppercase)
 *   2. Exact match setelah normalisasi (strip prefix, collapse spasi)
 *   3. Fuzzy match similar_text >= FUZZY_THRESHOLD %
 *
 * Kecamatan & Kelurahan adalah master data dengan daftar tetap → TIDAK pernah dibuat baru.
 * Nilai yang tak cocok ditandai sebagai gagal-resolve (lihat flagUnresolvedWilayah).
 */
trait ResolvesWilayah
{
    protected array $kecamatanCache = [];
    protected array $kelurahanCache = [];
    protected array $rtCache = [];

    /** Set nama wilayah yang sudah ditandai gagal-resolve, agar tidak spam peringatan duplikat. */
    protected array $flaggedWilayah = [];

    /**
     * Threshold kemiripan (0–100). Kecamatan & Kelurahan < threshold → ditolak (di-flag, id null).
     *
     * Dinaikkan dari 80 → 85: "GUNUNG TELIHAN" vs "GUNUNG ELAI" skornya tepat 80% karena
     * berbagi prefix "GUNUNG " — di threshold lama itu lolos dan kasus Telihan tertimpa ke
     * Elai (sekaligus bikin "Gunung Elai" nyangkut di Bontang Barat). Variasi/typo yang sah
     * umumnya >= 90%, jadi 85 cukup ketat tanpa mematikan koreksi ejaan wajar.
     */
    private const FUZZY_THRESHOLD = 85.0;

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

        // 4. TIDAK auto-create. Kecamatan adalah master data dengan daftar tetap
        //    (Bontang hanya punya 3 kecamatan). Nilai yang tak cocok hampir pasti
        //    salah kolom / typo — mis. "Discarded", "Campak", "Rubella" yang nyasar
        //    dari kolom klasifikasi akhir. Tandai agar operator melihatnya, lalu null.
        $this->flagUnresolvedWilayah('Kecamatan', $name);

        return null;
    }

    /**
     * Catat nama wilayah yang tidak bisa dicocokkan ke master data.
     * Didorong ke $this->failures (jika importer menyediakannya) agar tampil di
     * ringkasan import, dan dicatat ke log. Di-dedup per nama supaya tidak spam.
     */
    protected function flagUnresolvedWilayah(string $jenis, string $name): void
    {
        $clean = trim($name);
        if ($clean === '') {
            return;
        }

        $dedupKey = strtoupper($jenis . '|' . $clean);
        if (isset($this->flaggedWilayah[$dedupKey])) {
            return;
        }
        $this->flaggedWilayah[$dedupKey] = true;

        $msg = "[PERINGATAN] {$jenis} \"{$clean}\" tidak cocok dengan data master — "
             . "dilewati (id dikosongkan). Periksa apakah kolom file sumber bergeser.";

        if (property_exists($this, 'failures') && is_array($this->failures)) {
            $this->failures[] = $msg;
        }

        Log::warning("Resolve {$jenis} gagal: '{$clean}' tidak ada di master, tidak dibuat baru.");
    }

    /**
     * Resolve kelurahan: exact → normalisasi → fuzzy. TIDAK auto-create.
     *
     * Sama prinsipnya dengan resolveKecamatan: kelurahan adalah master data dengan
     * daftar tetap (Bontang punya 15 kelurahan). Auto-create lama menimbulkan dua
     * masalah: (a) firstOrCreate di-key [name, id_kecamatan] sehingga nama sama +
     * kecamatan beda bikin baris duplikat; (b) string yang tak cocok (typo / nilai
     * nyasar dari kolom lain) jadi master palsu. Kalau tak cocok → flag + null.
     *
     * $idKec kini tidak dipakai (dipertahankan agar signature pemanggil tak berubah).
     */
    protected function resolveKelurahan(string $name, ?int $idKec = null): ?int
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

        // 4. TIDAK auto-create. Tandai agar operator melihatnya, lalu null.
        $this->flagUnresolvedWilayah('Kelurahan', $name);

        return null;
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
