<?php

namespace Tests\Feature\IntervensiGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\IntervensiGizi;
use App\Models\Kelurahan;
use App\Services\IntervensiGiziService;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntervensiGiziServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // bb=12/tb=90/bln=24 → gizi buruk (prioritas 1) via observer snapshot.
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

    private function anakPrioritas(string $nik, ?int $idKel = null): Anak
    {
        $anak = Anak::create([
            'nama' => 'Anak ' . $nik, 'nik' => $nik, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $idKel,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);
        return $anak;
    }

    public function test_rekap_menghitung_x_dari_y(): void
    {
        $a1 = $this->anakPrioritas('3201000000009301');
        $this->anakPrioritas('3201000000009302');
        IntervensiGizi::create(['id_anak' => $a1->id, 'jenis' => 'PMT', 'status' => 'Selesai']);

        $r = app(IntervensiGiziService::class)->rekap([]);

        $this->assertSame(2, $r['total_prioritas']);
        $this->assertSame(1, $r['sudah']);
        $this->assertSame(50.0, $r['persen']);
    }

    public function test_filter_wilayah_membatasi_total_prioritas(): void
    {
        $kelA = Kelurahan::create(['name' => 'Api-Api', 'id_kecamatan' => 1]);
        $kelB = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        $this->anakPrioritas('3201000000009303', $kelA->id);
        $this->anakPrioritas('3201000000009304', $kelB->id);

        $r = app(IntervensiGiziService::class)->rekap(['kel' => $kelA->id]);

        $this->assertSame(1, $r['total_prioritas']);
    }

    public function test_daftar_prioritas_menyertakan_intervensi(): void
    {
        $a1 = $this->anakPrioritas('3201000000009305');
        IntervensiGizi::create(['id_anak' => $a1->id, 'jenis' => 'Rujukan', 'status' => 'Berjalan']);

        $daftar = app(IntervensiGiziService::class)->daftarPrioritas([]);

        $this->assertCount(1, $daftar);
        $this->assertSame(1, $daftar[0]['prioritas']);
        $this->assertSame(1, $daftar[0]['jumlah_intervensi']);
        $this->assertSame('Rujukan', $daftar[0]['intervensi'][0]['jenis']);
    }
}
