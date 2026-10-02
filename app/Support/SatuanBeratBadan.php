<?php
// app/Support/SatuanBeratBadan.php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Satuan input berat badan di form pengukuran (spec docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md §5.5):
 * umur di bawah 2 bulan pada tanggal kunjungan → gram, selain itu kg. Yang DISIMPAN selalu kg —
 * data_anak.bb dibaca z-score, PrioritasGiziService, OtGiziService, dasbor, dan importer.
 *
 * Batas = tgl_lahir + 2 bulan TANPA luapan akhir bulan (31 Des → 28/29 Feb), setara MySQL
 * DATE_ADD(tgl_lahir, INTERVAL 2 MONTH). Kunjungan tepat di batas = kg. JS form hanya
 * membandingkan tanggal dengan batas ini (string ISO), jadi tidak ada aritmetika bulan yang bisa
 * berbeda antara PHP dan browser.
 *
 * Rentang gram (≥ 300) dan kg (≤ 150) sengaja tidak beririsan: salah satuan pasti ditolak, tidak
 * pernah tersimpan diam-diam. Tanggal tak sah → kg (perilaku sebelum aturan ini ada).
 * Murni: tanpa DB, config(), atau now().
 */
final class SatuanBeratBadan
{
    public const GRAM = 'g';
    public const KG = 'kg';

    /** [min, maks] inklusif per satuan. */
    public const RENTANG = [self::GRAM => [300, 8000], self::KG => [1, 150]];

    /** Y-m-d tanggal mulai kg; '' bila tanggal lahir kosong/tak sah. */
    public static function batasGram(?string $tglLahir): string
    {
        $lahir = self::tanggal($tglLahir);

        return $lahir ? $lahir->addMonthsNoOverflow(2)->toDateString() : '';
    }

    public static function untuk(?string $tglLahir, ?string $tglKunjungan): string
    {
        $batas = self::batasGram($tglLahir);
        $kunjungan = self::tanggal($tglKunjungan);
        if ($batas === '' || $kunjungan === null) {
            return self::KG;
        }

        return $kunjungan->toDateString() < $batas ? self::GRAM : self::KG;
    }

    public static function keKg(float $nilai, string $satuan): float
    {
        return $satuan === self::GRAM ? $nilai / 1000 : $nilai;
    }

    /** Nilai awal di form: gram dibulatkan ke gram bulat (sisa presisi FLOAT MySQL dibuang). */
    public static function untukTampil(float $kg, string $satuan): int|float
    {
        return $satuan === self::GRAM ? (int) round($kg * 1000) : $kg;
    }

    public static function dalamRentang(float $nilai, string $satuan): bool
    {
        [$min, $maks] = self::RENTANG[$satuan];

        return $nilai >= $min && $nilai <= $maks;
    }

    /** Sama dalam toleransi 0,5 gram — membandingkan input ulang dengan nilai tersimpan. */
    public static function sama(float $kgA, float $kgB): bool
    {
        return abs($kgA - $kgB) < 0.0005;
    }

    public static function label(string $satuan): string
    {
        return $satuan === self::GRAM ? 'gram' : 'kg';
    }

    private static function tanggal(?string $tgl): ?CarbonImmutable
    {
        $tgl = substr(trim((string) $tgl), 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m)) {
            return null;
        }
        [$tahun, $bulan, $hari] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($tahun < 1900 || !checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $tgl);
    }
}
