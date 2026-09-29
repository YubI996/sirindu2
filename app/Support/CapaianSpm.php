<?php
// app/Support/CapaianSpm.php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Ketercapaian satu kategori SPM untuk satu tahun.
 * Spec: docs/superpowers/specs/2026-09-29-dasbor-spm-design.md §3.
 *
 * MURNI PHP — tanpa DB, tanpa config(), tanpa now(). Unit test proyek ini
 * memakai PHPUnit\Framework\TestCase polos (aplikasi tidak di-boot), jadi
 * ambang dan "sekarang" WAJIB masuk sebagai parameter.
 *
 * Dua hal yang membuat prorata jujur, jangan disederhanakan:
 *  - prorata diukur terhadap triwulan yang DILAPORKAN (twTerisi), bukan
 *    triwulan kalender — keterlambatan laporan bukan kegagalan program;
 *  - keterlambatan itu penanda terpisah (laporanTertinggal / twKosong).
 */
final class CapaianSpm
{
    public const STATUS_BELUM         = 'belum';
    public const STATUS_TANPA_SASARAN = 'tanpa_sasaran';
    public const STATUS_TERCAPAI      = 'tercapai';
    public const STATUS_SESUAI        = 'sesuai';
    public const STATUS_TERTINGGAL    = 'tertinggal';
    public const STATUS_KRITIS        = 'kritis';

    /** @param array<int,float|null> $tw nilai TW I..IV pada indeks 0..3 */
    private function __construct(
        private readonly ?float $sasaran,
        private readonly array $tw,
        private readonly int $twKalender,
        private readonly array $ambang,
    ) {
    }

    /**
     * @param array<int,float|string|null> $tw [tw1, tw2, tw3, tw4]
     * @param array{sesuai: float, tertinggal: float} $ambang dari config('spm.ambang')
     */
    public static function dari(
        ?float $sasaran,
        array $tw,
        int $tahun,
        array $ambang,
        ?CarbonImmutable $sekarang = null,
    ): self {
        $sekarang ??= CarbonImmutable::now();

        $twKalender = match (true) {
            $tahun < $sekarang->year => 4,
            $tahun > $sekarang->year => 0,
            default                  => (int) ceil($sekarang->month / 3),
        };

        $nilai = [];
        for ($i = 0; $i < 4; $i++) {
            $v = $tw[$i] ?? null;
            $nilai[$i] = ($v === null || $v === '') ? null : (float) $v;
        }

        return new self($sasaran === null ? null : (float) $sasaran, $nilai, $twKalender, $ambang);
    }

    public function sasaran(): ?float
    {
        return $this->sasaran;
    }

    /** Nilai TW ke-n (1..4). */
    public function tw(int $n): ?float
    {
        return $this->tw[$n - 1] ?? null;
    }

    /** Triwulan terakhir yang sudah dilaporkan; 0 bila belum ada. */
    public function twTerisi(): int
    {
        for ($i = 3; $i >= 0; $i--) {
            if ($this->tw[$i] !== null) {
                return $i + 1;
            }
        }

        return 0;
    }

    /** Triwulan menurut kalender: tahun lampau 4, tahun depan 0. */
    public function twKalender(): int
    {
        return $this->twKalender;
    }

    public function kumulatif(): ?float
    {
        $terisi = array_filter($this->tw, fn ($v) => $v !== null);

        return $terisi === [] ? null : (float) array_sum($terisi);
    }

    public function persen(): ?float
    {
        $kumulatif = $this->kumulatif();

        if ($kumulatif === null || !$this->punyaSasaran()) {
            return null;
        }

        return $kumulatif / $this->sasaran * 100;
    }

    /** Target sampai triwulan yang DILAPORKAN. */
    public function prorata(): ?float
    {
        return $this->punyaSasaran() ? $this->sasaran * $this->twTerisi() / 4 : null;
    }

    /** Target penuh TW ke-n (1..4) — garis target di grafik. */
    public function prorataTw(int $n): ?float
    {
        return $this->punyaSasaran() ? $this->sasaran * $n / 4 : null;
    }

    public function rasioLaju(): ?float
    {
        $prorata   = $this->prorata();
        $kumulatif = $this->kumulatif();

        if ($prorata === null || $prorata <= 0 || $kumulatif === null) {
            return null;
        }

        return $kumulatif / $prorata;
    }

    /** Sisa menuju sasaran tahunan; negatif berarti melampaui. */
    public function selisih(): ?float
    {
        $kumulatif = $this->kumulatif();

        if ($kumulatif === null || $this->sasaran === null) {
            return null;
        }

        return $this->sasaran - $kumulatif;
    }

    /** @return array<int,int> nomor TW (1..4) yang kosong DI BAWAH twTerisi. */
    public function twKosong(): array
    {
        $kosong = [];

        for ($i = 0; $i < $this->twTerisi() - 1; $i++) {
            if ($this->tw[$i] === null) {
                $kosong[] = $i + 1;
            }
        }

        return $kosong;
    }

    public function laporanTertinggal(): bool
    {
        return $this->twKalender > $this->twTerisi();
    }

    public function status(): string
    {
        if ($this->twTerisi() === 0) {
            return self::STATUS_BELUM;
        }

        if (!$this->punyaSasaran()) {
            return self::STATUS_TANPA_SASARAN;
        }

        if ($this->persen() >= 100) {
            return self::STATUS_TERCAPAI;
        }

        $rasio = $this->rasioLaju();

        if ($rasio >= $this->ambang['sesuai']) {
            return self::STATUS_SESUAI;
        }

        if ($rasio >= $this->ambang['tertinggal']) {
            return self::STATUS_TERTINGGAL;
        }

        return self::STATUS_KRITIS;
    }

    /**
     * Kumulatif di tiap TW untuk grafik garis; null setelah twTerisi supaya
     * triwulan yang belum dilaporkan tidak digambar sebagai penurunan ke 0.
     *
     * @return array<int,float|null>
     */
    public function kumulatifPerTw(): array
    {
        $out   = [];
        $jalan = 0.0;
        $batas = $this->twTerisi();

        for ($i = 0; $i < 4; $i++) {
            if ($i + 1 > $batas) {
                $out[] = null;
                continue;
            }

            $jalan += $this->tw[$i] ?? 0.0;
            $out[]  = $jalan;
        }

        return $out;
    }

    private function punyaSasaran(): bool
    {
        return $this->sasaran !== null && $this->sasaran > 0;
    }
}
