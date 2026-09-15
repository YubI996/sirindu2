<?php

namespace Tests\Feature\Pj;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Services\OtGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtGiziServiceTest extends TestCase
{
    use RefreshDatabase;

    private function anakOt(string $nik, float $bbU, float $pbU, float $bbPb, array $o = []): Anak
    {
        $a = Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => now()->subMonths(24)->toDateString(), 'status' => 1, 'sumber' => 'operasi_timbang'], $o));
        DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => now()->subDays(10)->toDateString(), 'bln' => 24, 'posisi' => 'berdiri',
            'tb' => 85, 'bb' => 10, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => $bbU, 'zscore_pb_u' => $pbU, 'zscore_bb_pb' => $bbPb, 'sumber' => 'operasi_timbang']);
        return $a;
    }

    public function test_id_anak_masalah_gizi_mengikuti_kategori_dan_wilayah(): void
    {
        $svc = app(OtGiziService::class);
        $stunt = $this->anakOt('3201000000030001', 0, -2.5, 0, ['id_kel' => 1]);
        $wast  = $this->anakOt('3201000000030002', 0, 0, -2.5, ['id_kel' => 1]);
        $buruk = $this->anakOt('3201000000030003', 0, 0, -3.5, ['id_kel' => 1]);
        $under = $this->anakOt('3201000000030004', -2.5, 0, 0, ['id_kel' => 2]);
        $this->anakOt('3201000000030005', 0, 0, 0, ['id_kel' => 1]); // normal

        $semua = $svc->idAnakMasalahGizi($svc->filterKosong(), ['stunting', 'wasting', 'underweight']);
        sort($semua);
        $this->assertSame([$stunt->id, $wast->id, $buruk->id, $under->id], $semua, 'gizi buruk termasuk wasting');

        $kel1 = $svc->idAnakMasalahGizi($svc->filterKosong(['kel' => 1]), ['stunting', 'wasting', 'underweight']);
        sort($kel1);
        $this->assertSame([$stunt->id, $wast->id, $buruk->id], $kel1);

        $this->assertSame([$buruk->id], $svc->idAnakMasalahGizi($svc->filterKosong(), ['gizi_buruk']));
    }

    public function test_anak_manual_dan_pengukuran_non_ot_diabaikan(): void
    {
        $svc = app(OtGiziService::class);
        $a = Anak::create(['nama' => 'Manual', 'nik' => '3201000000030006', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => now()->subMonths(24)->toDateString(), 'status' => 1]);
        DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 24, 'posisi' => 'berdiri',
            'tb' => 85, 'bb' => 10, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => -4, 'zscore_pb_u' => -4, 'zscore_bb_pb' => -4]);

        $this->assertSame([], $svc->idAnakMasalahGizi($svc->filterKosong(), ['stunting', 'wasting', 'underweight']));
    }
}
