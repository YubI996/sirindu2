<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tabel peringkat wilayah dashboard Gizi & Timbang.
 *
 * Penyebut = balita TERDAFTAR per wilayah (pilihan Dinkes), pembilang =
 * klasifikasi indikator dari z-score tersimpan (classifier enumEppgbm yang
 * sama dipakai kartu KPI). % di sini = jumlah ÷ balita terdaftar.
 */
class TimbangPeringkatWilayahTest extends TestCase
{
    use RefreshDatabase;

    /** Balita ≤60 bln terdaftar di kelurahan, opsional dengan z-score stunting. */
    private function balita(Kelurahan $kel, string $nik, ?float $zTbU, ?int $rtId = null): void
    {
        $anak = Anak::create([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1, 'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id, 'id_rt' => $rtId,
        ]);

        if ($zTbU !== null) {
            DataAnak::create([
                'id_anak' => $anak->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 24,
                'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
                'zscore_bb_u' => 0.0, 'zscore_pb_u' => $zTbU, 'zscore_bb_pb' => 0.0,
            ]);
        }
    }

    public function test_peringkat_kelurahan_terurut_prevalensi_dengan_penyebut_terdaftar(): void
    {
        $super = User::factory()->create(['type' => 0]);

        // Kel A: 4 balita terdaftar, 2 stunting (z ≤ -2.01) → 50%.
        $kelA = Kelurahan::factory()->create(['name' => 'Kel. Alfa']);
        $this->balita($kelA, '3201000000001001', -2.5);
        $this->balita($kelA, '3201000000001002', -3.0);
        $this->balita($kelA, '3201000000001003', -1.0);   // normal
        $this->balita($kelA, '3201000000001004', null);   // terdaftar, belum diukur

        // Kel B: 4 balita terdaftar, 1 stunting → 25%.
        $kelB = Kelurahan::factory()->create(['name' => 'Kel. Bravo']);
        $this->balita($kelB, '3201000000002001', -2.5);
        $this->balita($kelB, '3201000000002002', -1.0);
        $this->balita($kelB, '3201000000002003', 0.0);
        $this->balita($kelB, '3201000000002004', null);

        $data = $this->actingAs($super)
            ->getJson(route('admin.timbang.peringkat', ['indikator' => 'stunting', 'level' => 'kel']))
            ->assertStatus(200)
            ->json();

        $this->assertSame('stunting', $data['indikator']);
        $this->assertSame('kel', $data['level']);
        $this->assertCount(2, $data['rows']);

        // Peringkat 1 = prevalensi tertinggi (Kel A 50%).
        $r1 = $data['rows'][0];
        $this->assertSame(1, $r1['peringkat']);
        $this->assertSame('Kel. Alfa', $r1['nama']);
        $this->assertSame(4, $r1['total_balita']);   // penyebut = TERDAFTAR (termasuk yg belum diukur)
        $this->assertSame(2, $r1['jumlah']);
        $this->assertEquals(50.0, $r1['persentase']);

        $r2 = $data['rows'][1];
        $this->assertSame(2, $r2['peringkat']);
        $this->assertSame('Kel. Bravo', $r2['nama']);
        $this->assertSame(4, $r2['total_balita']);
        $this->assertSame(1, $r2['jumlah']);
        $this->assertEquals(25.0, $r2['persentase']);
    }

    public function test_level_rt_tanpa_kelurahan_minta_pilih_kelurahan(): void
    {
        $super = User::factory()->create(['type' => 0]);

        $data = $this->actingAs($super)
            ->getJson(route('admin.timbang.peringkat', ['level' => 'rt']))
            ->assertStatus(200)
            ->json();

        $this->assertTrue($data['needs_kelurahan']);
        $this->assertSame([], $data['rows']);
    }

    public function test_level_rt_dengan_kelurahan_mengelompokkan_per_rt(): void
    {
        $super = User::factory()->create(['type' => 0]);

        $kel = Kelurahan::factory()->create(['name' => 'Kel. Charlie']);
        $rt1 = Rt::factory()->create(['id_kelurahan' => $kel->id, 'name' => 'RT 01']);
        $rt2 = Rt::factory()->create(['id_kelurahan' => $kel->id, 'name' => 'RT 02']);

        // RT 01: 2 terdaftar, 1 stunting → 50%. RT 02: 2 terdaftar, 0 stunting → 0%.
        $this->balita($kel, '3201000000003001', -2.5, $rt1->id);
        $this->balita($kel, '3201000000003002', -1.0, $rt1->id);
        $this->balita($kel, '3201000000003003', -1.0, $rt2->id);
        $this->balita($kel, '3201000000003004', 0.0, $rt2->id);

        $data = $this->actingAs($super)
            ->getJson(route('admin.timbang.peringkat', [
                'indikator' => 'stunting', 'level' => 'rt', 'kelurahan' => $kel->id,
            ]))
            ->assertStatus(200)
            ->json();

        $this->assertSame('rt', $data['level']);
        $this->assertCount(2, $data['rows']);
        $this->assertSame('RT 01', $data['rows'][0]['nama']);
        $this->assertEquals(50.0, $data['rows'][0]['persentase']);
        $this->assertSame(0, $data['rows'][1]['jumlah']);
    }
}
