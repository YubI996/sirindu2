<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * % pengambilan sampel   = status_lab 'diperiksa' / total kasus tahun itu.
 * % hasil lab diterima   = status_kasus confirmed/discarded / total kasus tahun itu.
 * positivity_rate        = confirmed / (confirmed + discarded).
 */
class Pd3iLabPercentagesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lab_percentages_use_binary_status_and_total_cases(): void
    {
        $kec   = Kecamatan::factory()->create();
        $kel   = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt    = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $admin = User::factory()->create(['type' => 0]);

        // id_jenis_kasus 1 = Campak-Rubella (di-hardcode di getPd3iKinerja).
        JenisKasusEpidemiologi::factory()->create([
            'id' => 1, 'kode_penyakit' => 'CAMPAK_RUBELLA', 'nama_penyakit' => 'Campak-Rubella',
        ]);

        // 4 kasus CR: 3 diperiksa; resolved = 2 confirmed + 1 discarded.
        $rows = [
            ['diperiksa', 'confirmed'],
            ['diperiksa', 'confirmed'],
            ['diperiksa', 'discarded'],
            ['tidak',     'suspected'],
        ];
        foreach ($rows as $i => [$lab, $kasus]) {
            SurveillanceCase::factory()->create([
                'no_registrasi'    => 'C-171026' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'id_jenis_kasus'   => 1,
                'id_kec'           => $kec->id,
                'id_kel'           => $kel->id,
                'id_rt'            => $rt->id,
                'id_petugas_input' => $admin->id,
                'created_by'       => $admin->id,
                'updated_by'       => $admin->id,
                'status_lab'       => $lab,
                'status_kasus'     => $kasus,
                'tanggal_lapor'    => '2026-02-01',
            ]);
        }

        $cr = app(SurveillanceRepository::class)->getPd3iKinerja(2026, null, null, null, null)['campak_rubella'];

        $this->assertSame(75.0, $cr['pct_sampel']);        // 3 diperiksa / 4
        $this->assertSame(75.0, $cr['pct_lab_diterima']);  // 3 resolved / 4
        $this->assertSame(66.7, $cr['positivity_rate']);   // 2 confirmed / 3 resolved
    }
}
