<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Mengunci perbaikan formulir FP-1 (AFP): dulu blade membaca kolom yang TIDAK ADA
 * (tanggal_penyelidikan, tanggal_lumpuh) sehingga selalu kosong. Sekarang:
 *  - "Tanggal Penyelidikan"        ← tanggal_penyidikan
 *  - "Tanggal mulai lemah/lumpuh"  ← tanggal_onset (untuk AFP, onset = tgl lumpuh)
 */
class FormulirFp1RendersTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCase(array $overrides = []): SurveillanceCase
    {
        $kec = Kecamatan::factory()->create();
        $kel = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt  = Rt::factory()->create(['id_kelurahan' => $kel->id]);

        return SurveillanceCase::factory()->create(array_merge([
            'id_kec'            => $kec->id,
            'id_kel'            => $kel->id,
            'id_rt'             => $rt->id,
            'tanggal_lahir'     => '2020-01-01',
            'tanggal_onset'     => '2026-03-05',
            'tanggal_penyidikan' => '2026-03-10',
            'instansi_pelapor'  => 'Bontang Utara 1',
        ], $overrides));
    }

    public function test_fp1_menampilkan_tanggal_penyidikan_dan_onset_sebagai_tgl_lumpuh(): void
    {
        $case = $this->makeCase();

        $html = View::make('admin.epidemiologi.pdf.formulir-fp1', ['case' => $case])->render();

        // tanggal_penyidikan kini tampil di sel "Tanggal Penyelidikan"
        $this->assertStringContainsString('10-Mar-2026', $html);
        // tanggal_onset kini tampil di sel "Tanggal mulai lemah/lumpuh"
        $this->assertStringContainsString('05-Mar-2026', $html);
        // instansi pelapor tampil
        $this->assertStringContainsString('Bontang Utara 1', $html);
    }
}
