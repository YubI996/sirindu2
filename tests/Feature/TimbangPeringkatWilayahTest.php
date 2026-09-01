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
 * Penyebut = sasaran Operasi Timbang TERKUNCI per wilayah (anak+data_anak
 * sumber='operasi_timbang'), pembilang = klasifikasi indikator dari z-score
 * tersimpan (classifier enumEppgbm yang sama dipakai kartu KPI). % di sini =
 * jumlah ÷ sasaran OT terkunci — statistiknya tidak bergeser saat ada anak
 * baru terdaftar di luar OT.
 */
class TimbangPeringkatWilayahTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Balita ≤60 bln, sasaran Operasi Timbang, opsional dengan z-score stunting.
     *
     * zTbU=null mensimulasikan anak yang BUKAN bagian populasi OT terkunci
     * (mis. terdaftar lewat jalur lain, tak pernah ditimbang saat OT) — anak
     * OT sungguhan selalu punya pengukurannya sendiri, jadi baris begini
     * sengaja TIDAK ditandai sumber='operasi_timbang' dan harus tidak ikut
     * penyebut sasaran terkunci.
     */
    private function balita(Kelurahan $kel, string $nik, ?float $zTbU, ?int $rtId = null): void
    {
        $anak = Anak::create([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1, 'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id, 'id_rt' => $rtId,
            'sumber' => $zTbU !== null ? 'operasi_timbang' : 'manual',
        ]);

        if ($zTbU !== null) {
            DataAnak::create([
                'id_anak' => $anak->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 24,
                'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
                'zscore_bb_u' => 0.0, 'zscore_pb_u' => $zTbU, 'zscore_bb_pb' => 0.0,
                'sumber' => 'operasi_timbang',
            ]);
        }
    }

    public function test_peringkat_kelurahan_terurut_prevalensi_dengan_penyebut_sasaran_ot(): void
    {
        $super = User::factory()->create(['type' => 0]);

        // Kel A: 3 sasaran OT terkunci, 2 stunting (z ≤ -2.01) → 66.7%.
        // Anak ke-4 BUKAN sasaran OT (tak pernah ditimbang) → tak ikut penyebut.
        $kelA = Kelurahan::factory()->create(['name' => 'Kel. Alfa']);
        $this->balita($kelA, '3201000000001001', -2.5);
        $this->balita($kelA, '3201000000001002', -3.0);
        $this->balita($kelA, '3201000000001003', -1.0);   // normal
        $this->balita($kelA, '3201000000001004', null);   // bukan sasaran OT terkunci

        // Kel B: 3 sasaran OT terkunci, 1 stunting → 33.3%.
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

        // Peringkat 1 = prevalensi tertinggi (Kel A 66.7%).
        $r1 = $data['rows'][0];
        $this->assertSame(1, $r1['peringkat']);
        $this->assertSame('Kel. Alfa', $r1['nama']);
        $this->assertSame(3, $r1['total_balita']);   // penyebut = sasaran OT terkunci saja
        $this->assertSame(2, $r1['jumlah']);
        $this->assertEqualsWithDelta(66.7, $r1['persentase'], 0.1);

        $r2 = $data['rows'][1];
        $this->assertSame(2, $r2['peringkat']);
        $this->assertSame('Kel. Bravo', $r2['nama']);
        $this->assertSame(3, $r2['total_balita']);
        $this->assertSame(1, $r2['jumlah']);
        $this->assertEqualsWithDelta(33.3, $r2['persentase'], 0.1);
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
