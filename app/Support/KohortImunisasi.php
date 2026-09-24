<?php
// app/Support/KohortImunisasi.php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Kohort sasaran imunisasi — spec docs/superpowers/specs/2026-09-24-kohort-sasaran-imunisasi-design.md.
 *
 * Sasaran tahun X = anak lahir 1 April X-1 s.d. 31 Maret X, umur dipotret pada
 * 31 Maret X. Batas atas SI dibaca "belum genap 12 bulan": anak yang lahir
 * 1 April X-1 berumur 11 bulan 30 hari di hari potret, jadi membaca "11 bln
 * 29 hr" harfiah justru membuang hari pertama kohortnya sendiri.
 *
 * Keempat batas jatuh di tanggal kalender tetap, jadi tidak ada operasi
 * "tambah N bulan" yang bisa meleset di akhir bulan atau tahun kabisat.
 * Murni PHP, tanpa DB — sebangun dengan PeriodeKesmas.
 */
final class KohortImunisasi
{
    /** Urutannya menentukan urutan keluaran kelompokDari(). */
    public const KELOMPOK = ['BBL', 'SI', 'SELURUH', 'BADUTA'];

    private function __construct(
        private readonly int $tahun,
        private readonly CarbonImmutable $lahirAwal,
        private readonly CarbonImmutable $lahirAkhir,
    ) {
    }

    public static function dari(int $tahun): self
    {
        if ($tahun < 2000 || $tahun > 2100) {
            throw new InvalidArgumentException("Tahun kohort di luar akal: {$tahun}");
        }

        return new self(
            $tahun,
            CarbonImmutable::create($tahun - 1, 4, 1)->startOfDay(),
            CarbonImmutable::create($tahun, 3, 31)->startOfDay(),
        );
    }

    public function tahun(): int
    {
        return $this->tahun;
    }

    /**
     * Rentang tanggal lahir satu kelompok, inklusif di kedua ujung.
     *
     * @return array{0: string, 1: string} [awal, akhir] format Y-m-d
     */
    public function rentang(string $kelompok): array
    {
        $t = $this->tahun;
        $tgl = fn (int $th, int $b, int $h) => CarbonImmutable::create($th, $b, $h)->toDateString();

        return match ($kelompok) {
            'BBL'     => [$tgl($t, 2, 1),     $tgl($t, 3, 31)],
            'SI'      => [$tgl($t - 1, 4, 1), $tgl($t, 1, 31)],
            'SELURUH' => [$tgl($t - 1, 4, 1), $tgl($t, 3, 31)],
            'BADUTA'  => [$tgl($t - 2, 4, 1), $tgl($t - 1, 3, 31)],
            default   => throw new InvalidArgumentException("Kelompok kohort tidak dikenal: {$kelompok}"),
        };
    }

    /**
     * Kelompok mana saja yang memuat tanggal lahir ini. BBL & SI saling
     * meniadakan; SELURUH memuat keduanya; BADUTA terpisah dari ketiganya.
     *
     * @return list<string>
     */
    public function kelompokDari(string $tglLahir): array
    {
        $tgl = substr($tglLahir, 0, 10);
        $hasil = [];

        foreach (self::KELOMPOK as $kelompok) {
            [$awal, $akhir] = $this->rentang($kelompok);
            if ($tgl >= $awal && $tgl <= $akhir) {
                $hasil[] = $kelompok;
            }
        }

        return $hasil;
    }

    public function label(): string
    {
        $f = fn (CarbonImmutable $d) => $d->format('j M Y');

        return sprintf(
            'Kohort %d · lahir %s – %s · potret umur %s',
            $this->tahun,
            $f($this->lahirAwal),
            $f($this->lahirAkhir),
            $f($this->lahirAkhir),
        );
    }

    /**
     * Pilihan dropdown: tahun berjalan mundur 4 tahun. Kohort yang lebih tua
     * sudah lewat usia imunisasi rutin.
     *
     * @return list<int>
     */
    public static function pilihanTahun(?int $tahunIni = null): array
    {
        $tahunIni ??= (int) date('Y');

        return range($tahunIni, $tahunIni - 4);
    }

    /** Tahun dari input mentah query string; yang di luar daftar → default. */
    public static function tahunTervalidasi(mixed $input, ?int $tahunIni = null): int
    {
        $tahunIni ??= (int) date('Y');
        $tahun = filter_var($input, FILTER_VALIDATE_INT);

        return ($tahun !== false && in_array($tahun, self::pilihanTahun($tahunIni), true))
            ? $tahun
            : $tahunIni;
    }
}
