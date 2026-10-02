<?php
// app/Support/TahunSasaranKesmas.php

namespace App\Support;

use InvalidArgumentException;

/**
 * Tahun sasaran per tahap — rumus Kesmas dari lembar "PERMINTAAN DATA" klien
 * (spec docs/superpowers/specs/2026-10-02-kesmas-permintaan-data-design.md §5.4):
 * tahun lahir + 1 (IDL), +2 (24 bln), +3 (IBL), +4 (48 bln), +5 (60 bln), +6 (72 bln).
 *
 * SENGAJA beda dari KohortImunisasi (cut-off 1 Apr–31 Mar, IBL dinilai di tahun Baduta ≈ +2):
 * modul imunisasi tidak disentuh, dan halaman yang menampilkan angka ini menyebut perbedaannya.
 * Kalau klien mengoreksi posisi IBL, cukup ubah TAHAP.
 *
 * Tidak disimpan — dihitung dari tgl_lahir saat tampil, jadi koreksi tanggal lahir langsung ikut.
 * Murni PHP: tanpa DB, config(), atau now().
 */
final class TahunSasaranKesmas
{
    /** kode => [selisih tahun dari tahun lahir, label]. Urutan = urutan tampil. */
    public const TAHAP = [
        'idl'    => [1, 'IDL'],
        '24_bln' => [2, 'Sasaran 24 bulan'],
        'ibl'    => [3, 'IBL'],
        '48_bln' => [4, 'Sasaran 48 bulan'],
        '60_bln' => [5, 'Sasaran 60 bulan'],
        '72_bln' => [6, 'Sasaran 72 bulan'],
    ];

    private function __construct(private readonly int $tahunLahir)
    {
    }

    /** null bila kosong, bukan Y-m-d, tanggal mustahil, atau tahun < 1900 (mis. '0000-00-00'). */
    public static function coba(?string $tglLahir): ?self
    {
        $tgl = substr(trim((string) $tglLahir), 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tgl, $m)) {
            return null;
        }
        [$tahun, $bulan, $hari] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($tahun < 1900 || !checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        return new self($tahun);
    }

    public function tahun(string $tahap): int
    {
        if (!isset(self::TAHAP[$tahap])) {
            throw new InvalidArgumentException("Tahap tahun sasaran tidak dikenal: {$tahap}");
        }

        return $this->tahunLahir + self::TAHAP[$tahap][0];
    }

    /** @return array<string, array{label: string, tahun: int}> */
    public function semua(): array
    {
        $hasil = [];
        foreach (self::TAHAP as $kode => [$selisih, $label]) {
            $hasil[$kode] = ['label' => $label, 'tahun' => $this->tahunLahir + $selisih];
        }

        return $hasil;
    }
}
