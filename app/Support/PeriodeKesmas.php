<?php
// app/Support/PeriodeKesmas.php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Periode dasbor Kesmas — spec docs/superpowers/specs/2026-09-21-dasbor-kesmas-design.md §2.2.
 * Syarat "N× setahun" diprorata: tahun ×1, semester ×½, triwulan ×¼.
 * DDTKA & Vit A dievaluasi pada semester induk (jadwalnya semesteran); untuk
 * periode 'tahun' semester = tahun penuh. Murni PHP, tanpa DB.
 */
final class PeriodeKesmas
{
    public const KODE = ['tahun', 's1', 's2', 'tw1', 'tw2', 'tw3', 'tw4'];

    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private function __construct(
        private readonly int $tahun,
        private readonly string $kode,
        private readonly CarbonImmutable $awal,
        private readonly CarbonImmutable $akhir,
        private readonly CarbonImmutable $semesterAwal,
        private readonly CarbonImmutable $semesterAkhir,
        private readonly float $p,
    ) {
    }

    public static function dari(int $tahun, string $periode = 'tahun'): self
    {
        if (!in_array($periode, self::KODE, true)) {
            throw new InvalidArgumentException("Periode tidak dikenal: {$periode}");
        }

        $tgl = fn (int $bulan, int $hari) => CarbonImmutable::create($tahun, $bulan, $hari)->startOfDay();

        [$awal, $akhir, $p] = match ($periode) {
            'tahun' => [$tgl(1, 1), $tgl(12, 31), 1.0],
            's1'    => [$tgl(1, 1), $tgl(6, 30), 0.5],
            's2'    => [$tgl(7, 1), $tgl(12, 31), 0.5],
            'tw1'   => [$tgl(1, 1), $tgl(3, 31), 0.25],
            'tw2'   => [$tgl(4, 1), $tgl(6, 30), 0.25],
            'tw3'   => [$tgl(7, 1), $tgl(9, 30), 0.25],
            'tw4'   => [$tgl(10, 1), $tgl(12, 31), 0.25],
        };

        [$sAwal, $sAkhir] = match (true) {
            $periode === 'tahun' => [$awal, $akhir],
            $awal->month <= 6    => [$tgl(1, 1), $tgl(6, 30)],
            default              => [$tgl(7, 1), $tgl(12, 31)],
        };

        return new self($tahun, $periode, $awal, $akhir, $sAwal, $sAkhir, $p);
    }

    public function tahun(): int { return $this->tahun; }
    public function kode(): string { return $this->kode; }
    public function awal(): CarbonImmutable { return $this->awal; }
    public function akhir(): CarbonImmutable { return $this->akhir; }
    public function semesterAwal(): CarbonImmutable { return $this->semesterAwal; }
    public function semesterAkhir(): CarbonImmutable { return $this->semesterAkhir; }
    public function p(): float { return $this->p; }

    /** Syarat minimal dalam periode ini untuk indikator "N× setahun". */
    public function syarat(int $nPerTahun): int
    {
        return (int) ceil($nPerTahun * $this->p);
    }

    public function label(): string
    {
        $rentang = sprintf(
            '(%d %s–%d %s)',
            $this->awal->day, self::BULAN[$this->awal->month],
            $this->akhir->day, self::BULAN[$this->akhir->month]
        );

        return match ($this->kode) {
            'tahun' => "Tahun {$this->tahun}",
            's1'    => "Semester I {$this->tahun} {$rentang}",
            's2'    => "Semester II {$this->tahun} {$rentang}",
            'tw1'   => "Triwulan I {$this->tahun} {$rentang}",
            'tw2'   => "Triwulan II {$this->tahun} {$rentang}",
            'tw3'   => "Triwulan III {$this->tahun} {$rentang}",
            'tw4'   => "Triwulan IV {$this->tahun} {$rentang}",
        };
    }
}
