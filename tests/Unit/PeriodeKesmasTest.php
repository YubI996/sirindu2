<?php
// tests/Unit/PeriodeKesmasTest.php

namespace Tests\Unit;

use App\Support\PeriodeKesmas;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PeriodeKesmasTest extends TestCase
{
    public function test_tahun_penuh(): void
    {
        $p = PeriodeKesmas::dari(2025, 'tahun');
        $this->assertSame('2025-01-01', $p->awal()->toDateString());
        $this->assertSame('2025-12-31', $p->akhir()->toDateString());
        $this->assertSame('2025-01-01', $p->semesterAwal()->toDateString(), 'Untuk tahun, semester = tahun penuh.');
        $this->assertSame('2025-12-31', $p->semesterAkhir()->toDateString());
        $this->assertSame(1.0, $p->p());
        $this->assertSame(8, $p->syarat(8));
        $this->assertSame(2, $p->syarat(2));
        $this->assertSame('Tahun 2025', $p->label());
        $this->assertSame(2025, $p->tahun());
        $this->assertSame('tahun', $p->kode());
    }

    public function test_semester(): void
    {
        $s1 = PeriodeKesmas::dari(2025, 's1');
        $this->assertSame(['2025-01-01', '2025-06-30'], [$s1->awal()->toDateString(), $s1->akhir()->toDateString()]);
        $this->assertSame(['2025-01-01', '2025-06-30'], [$s1->semesterAwal()->toDateString(), $s1->semesterAkhir()->toDateString()]);
        $this->assertSame(4, $s1->syarat(8));
        $this->assertSame(1, $s1->syarat(2));
        $this->assertSame('Semester I 2025 (1 Jan–30 Jun)', $s1->label());

        $s2 = PeriodeKesmas::dari(2025, 's2');
        $this->assertSame(['2025-07-01', '2025-12-31'], [$s2->awal()->toDateString(), $s2->akhir()->toDateString()]);
        $this->assertSame('Semester II 2025 (1 Jul–31 Des)', $s2->label());
    }

    public function test_triwulan_memakai_semester_induk(): void
    {
        $tw3 = PeriodeKesmas::dari(2025, 'tw3');
        $this->assertSame(['2025-07-01', '2025-09-30'], [$tw3->awal()->toDateString(), $tw3->akhir()->toDateString()]);
        $this->assertSame(['2025-07-01', '2025-12-31'], [$tw3->semesterAwal()->toDateString(), $tw3->semesterAkhir()->toDateString()]);
        $this->assertSame(0.25, $tw3->p());
        $this->assertSame(2, $tw3->syarat(8));
        $this->assertSame(1, $tw3->syarat(2));
        $this->assertSame('Triwulan III 2025 (1 Jul–30 Sep)', $tw3->label());

        $tw2 = PeriodeKesmas::dari(2025, 'tw2');
        $this->assertSame(['2025-04-01', '2025-06-30'], [$tw2->awal()->toDateString(), $tw2->akhir()->toDateString()]);
        $this->assertSame(['2025-01-01', '2025-06-30'], [$tw2->semesterAwal()->toDateString(), $tw2->semesterAkhir()->toDateString()]);

        $tw4 = PeriodeKesmas::dari(2024, 'tw4');
        $this->assertSame(['2024-10-01', '2024-12-31'], [$tw4->awal()->toDateString(), $tw4->akhir()->toDateString()]);
        $this->assertSame('Triwulan IV 2024 (1 Okt–31 Des)', $tw4->label());
    }

    public function test_periode_tidak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PeriodeKesmas::dari(2025, 'bulan');
    }
}
