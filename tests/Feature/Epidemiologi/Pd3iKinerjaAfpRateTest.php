<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\JumlahPenduduk;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Non-Polio AFP Rate = jumlah kasus AFP yang diselidiki lalu **discarded**
 * (terbukti bukan polio) per 100.000 penduduk < 15 tahun. Pembilangnya HARUS
 * kasus discarded, bukan seluruh kasus AFP.
 */
class Pd3iKinerjaAfpRateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_npafp_rate_uses_discarded_afp_cases_as_numerator(): void
    {
        $kec   = Kecamatan::factory()->create();
        $kel   = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt    = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $admin = User::factory()->create(['type' => 0]);

        // id_jenis_kasus 3 = AFP/Polio (di-hardcode di getPd3iKinerja).
        JenisKasusEpidemiologi::factory()->create([
            'id' => 3, 'kode_penyakit' => 'AFP', 'nama_penyakit' => 'AFP/Polio',
        ]);

        JumlahPenduduk::create(['tahun' => 2026, 'kategori' => 'Dibawah 15 Tahun', 'id_kelurahan' => $kel->id, 'jumlah_penduduk' => 50000]);
        JumlahPenduduk::create(['tahun' => 2026, 'kategori' => 'Total', 'id_kelurahan' => $kel->id, 'jumlah_penduduk' => 200000]);

        // 3 kasus AFP: 2 discarded, 1 confirmed.
        foreach (['discarded', 'discarded', 'confirmed'] as $i => $status) {
            SurveillanceCase::factory()->create([
                'no_registrasi'    => '171026' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'id_jenis_kasus'   => 3,
                'id_kec'           => $kec->id,
                'id_kel'           => $kel->id,
                'id_rt'            => $rt->id,
                'id_petugas_input' => $admin->id,
                'created_by'       => $admin->id,
                'updated_by'       => $admin->id,
                'status_kasus'     => $status,
                'tanggal_lapor'    => '2026-02-01',
            ]);
        }

        $data = app(SurveillanceRepository::class)->getPd3iKinerja(2026, null, null, null, null);

        // 2 discarded / 50000 * 100000 = 4.00 (bukan 6.00 dari 3 total AFP).
        $this->assertSame(4.0, $data['afp']['npafp_rate']);
    }
}
