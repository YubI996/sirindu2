<?php

namespace Tests\Unit\Support;

use App\Support\FilterPd3i;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class FilterPd3iTest extends TestCase
{
    public function test_tanpa_tanggal_tidak_ada_rentang(): void
    {
        $f = new FilterPd3i(tahun: 2026);
        $this->assertFalse($f->adaRentang());
        $this->assertNull($f->tanggalDari);
        $this->assertNull($f->tanggalSampai);
        $this->assertSame('Tahun 2026', $f->labelPeriode());
    }

    public function test_hanya_dari_terisi(): void
    {
        $f = new FilterPd3i(tahun: 2026, tanggalDari: '2026-03-01');
        $this->assertTrue($f->adaRentang());
        $this->assertSame('2026-03-01', $f->tanggalDari);
        $this->assertNull($f->tanggalSampai);
        $this->assertSame('1 Mar 2026 – 31 Des 2026', $f->labelPeriode());
    }

    public function test_hanya_sampai_terisi(): void
    {
        $f = new FilterPd3i(tahun: 2026, tanggalSampai: '2026-06-30');
        $this->assertSame('1 Jan 2026 – 30 Jun 2026', $f->labelPeriode());
    }

    public function test_dari_lebih_besar_ditukar(): void
    {
        $f = new FilterPd3i(tahun: 2026, tanggalDari: '2026-06-30', tanggalSampai: '2026-05-01');
        $this->assertSame('2026-05-01', $f->tanggalDari);
        $this->assertSame('2026-06-30', $f->tanggalSampai);
        $this->assertSame('1 Mei 2026 – 30 Jun 2026', $f->labelPeriode());
    }

    public function test_tanggal_di_luar_tahun_diabaikan(): void
    {
        $f = new FilterPd3i(tahun: 2026, tanggalDari: '2025-12-31', tanggalSampai: '2027-01-01');
        $this->assertNull($f->tanggalDari);
        $this->assertNull($f->tanggalSampai);
        $this->assertFalse($f->adaRentang());
    }

    /** `wilker[][]=x` dulu memicu "Array to string conversion" → 500 (audit REQ-003/SEC-003). */
    public function test_nilai_bersarang_di_query_string_diabaikan(): void
    {
        $f = FilterPd3i::dariRequest(Request::create('/', 'GET', [
            'tahun'          => '2026',
            'wilker'         => [['x'], 'Bontang Utara I'],
            'kab_kota'       => [['a', 'b']],
            'faskes_pelapor' => [' PKM A ', ['b']],
            'kelurahan_id'   => [['1'], '7'],
        ]));

        $this->assertSame(['Bontang Utara I'], $f->wilker);
        $this->assertNull($f->kabKota);
        $this->assertSame(['PKM A'], $f->faskesPelapor);
        $this->assertSame([7], $f->kelurahanId);
    }

    public function test_format_salah_diabaikan(): void
    {
        foreach (['15/03/2026', '2026-02-30', '2026-3-1', 'x', ''] as $nilai) {
            $f = new FilterPd3i(tahun: 2026, tanggalDari: $nilai);
            $this->assertNull($f->tanggalDari, "nilai '{$nilai}' harus diabaikan");
        }
    }
}
