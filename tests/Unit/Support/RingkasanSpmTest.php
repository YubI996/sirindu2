<?php

namespace Tests\Unit\Support;

use App\Support\CapaianSpm;
use App\Support\RingkasanSpm;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RingkasanSpmTest extends TestCase
{
    private const AMBANG = ['sesuai' => 0.90, 'tertinggal' => 0.60];

    private function capaian(?float $sasaran, array $tw): CapaianSpm
    {
        return CapaianSpm::dari($sasaran, $tw, 2026, self::AMBANG, CarbonImmutable::create(2026, 6, 15));
    }

    public function test_rata_rata_tidak_dibobot_dan_hanya_yang_punya_persen(): void
    {
        $r = RingkasanSpm::dari([
            $this->capaian(1000, [400, null, null, null]),   // 40 %
            $this->capaian(10, [6, null, null, null]),       // 60 %
            $this->capaian(1000, [null, null, null, null]),  // belum — tidak ikut
        ]);

        $this->assertSame(3, $r['jumlah_kategori']);
        $this->assertSame(2, $r['dihitung']);
        $this->assertSame(50.0, $r['rata_rata'], 'rata-rata aritmetik 40 & 60, bukan (406/1010)');
        $this->assertSame(1, $r['belum']);
    }

    public function test_kategori_belum_dilaporkan_tidak_dihitung_nol(): void
    {
        $r = RingkasanSpm::dari([
            $this->capaian(1000, [900, null, null, null]),   // 90 %
            $this->capaian(1000, [null, null, null, null]),  // belum
        ]);

        $this->assertSame(90.0, $r['rata_rata'], 'yang belum dilaporkan tidak boleh menarik rata-rata ke 45%');
    }

    public function test_tanpa_sasaran_tidak_masuk_tercapai_maupun_tertinggal(): void
    {
        $r = RingkasanSpm::dari([
            $this->capaian(0.0, [10, null, null, null]),
        ]);

        $this->assertSame(1, $r['tanpa_sasaran']);
        $this->assertSame(0, $r['tercapai']);
        $this->assertSame(0, $r['tertinggal']);
        $this->assertNull($r['rata_rata']);
        $this->assertSame(0, $r['dihitung']);
    }

    public function test_tertinggal_menggabungkan_tertinggal_dan_kritis(): void
    {
        $r = RingkasanSpm::dari([
            $this->capaian(1000, [200, 175, null, null]),  // tertinggal (0,75)
            $this->capaian(1000, [150, 100, null, null]),  // kritis (0,50)
            $this->capaian(1000, [1000, null, null, null]) // tercapai
        ]);

        $this->assertSame(2, $r['tertinggal']);
        $this->assertSame(1, $r['tercapai']);
        $this->assertSame(1, $r['per_status'][CapaianSpm::STATUS_KRITIS]);
    }

    public function test_daftar_kosong_aman(): void
    {
        $r = RingkasanSpm::dari([]);

        $this->assertSame(0, $r['jumlah_kategori']);
        $this->assertNull($r['rata_rata']);
        $this->assertSame(0, $r['tercapai']);
    }
}
