<?php

namespace Tests\Feature\PrioritasGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Services\PrioritasGiziService;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrioritasGiziServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // jk=1, umur 24 (var=2), tinggi 90. bb=12 → BB/TB severely_wasted (gizi buruk),
        // tinggi 90 < m2sd 83? tidak → TB/U normal. Lihat TimbangGiziBbTbTest utk pola.
        StatusGiziService::useRefs([
            '1_1_24_2' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            '2_1_24_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            '3_1_24_2' => (object) ['m3sd' => 80, 'm2sd' => 83, '3sd' => 97],
            '4_1_90_2' => (object) ['m3sd' => 15, 'm2sd' => 16, '1sd' => 20, '2sd' => 22, '3sd' => 24],
        ]);
    }

    protected function tearDown(): void
    {
        StatusGiziService::flushCache();
        parent::tearDown();
    }

    public function test_anak_gizi_buruk_diberi_prioritas_1(): void
    {
        $anak = Anak::create([
            'nama' => 'Balita Buruk', 'nik' => '3201000000009002', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
        ]);

        $hasil = app(PrioritasGiziService::class)->hitungUntukAnak($anak->fresh());

        $this->assertTrue($hasil['gizi_buruk']);
        $this->assertSame(1, $hasil['prioritas']);
        $this->assertSame(24, $hasil['usia_bln']);
    }

    public function test_anak_tanpa_kunjungan_valid_prioritas_null(): void
    {
        $anak = Anak::create([
            'nama' => 'Tanpa Ukur', 'nik' => '3201000000009003', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);

        $hasil = app(PrioritasGiziService::class)->hitungUntukAnak($anak->fresh());

        $this->assertFalse($hasil['gizi_buruk']);
        $this->assertFalse($hasil['stunting']);
        $this->assertNull($hasil['prioritas']);
    }

    public function test_bb_tidak_naik_dari_dua_kunjungan(): void
    {
        $anak = Anak::create([
            'nama' => 'BB Turun', 'nik' => '3201000000009004', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2023-06-01', 'status' => 1,
        ]);
        // Dua kunjungan, BB terakhir <= sebelumnya, tanpa tb valid (fokus bb_tidak_naik).
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-05-01', 'bln' => 11,
            'posisi' => 'telentang', 'tb' => 0, 'bb' => 9.0, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 12,
            'posisi' => 'telentang', 'tb' => 0, 'bb' => 8.9, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);

        $hasil = app(PrioritasGiziService::class)->hitungUntukAnak($anak->fresh());

        $this->assertTrue($hasil['bb_tidak_naik']);
        $this->assertSame(3, $hasil['prioritas']);
    }
}
