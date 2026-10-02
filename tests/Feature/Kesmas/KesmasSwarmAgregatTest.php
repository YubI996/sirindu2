<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Imunisasi;
use App\Models\JenisVaksin;
use App\Models\Kecamatan;
use App\Models\KelompokVaksin;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Services\StatusGiziService;
use App\Support\PeriodeKesmas;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Kasus silang yang tidak tertangkap ketika setiap kartu diuji sendiri. */
class KesmasSwarmAgregatTest extends TestCase
{
    use RefreshDatabase;

    private KesmasDashboardService $svc;
    private Kecamatan $kec;
    private Kelurahan $kel;
    private int $nomor = 0;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->svc = app(KesmasDashboardService::class);
        $this->kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $this->kec->id]);
    }

    private function anak(string $lahir = '2023-06-30', array $extra = []): Anak
    {
        $this->nomor++;

        return Anak::create(array_merge([
            'nama' => 'Anak Swarm ' . $this->nomor,
            'nik' => str_pad((string) $this->nomor, 16, '7', STR_PAD_LEFT),
            'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => $lahir,
            'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id, 'sasaran_balita_kesmas' => 1,
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tanggal, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tanggal,
            'bln' => 30, 'posisi' => 'L', 'tb' => 80, 'bb' => 10,
            'lla' => 13, 'lk' => 45, 'id_user' => 1, 'sumber' => 'manual',
        ], $extra));
    }

    private function seedVaksin(): void
    {
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
        ImunisasiStatusService::flushCache();
    }

    public function test_jumlah_perhatian_k4_sama_dengan_drilldown_setelah_koreksi_pada_hari_yang_sama(): void
    {
        $this->seedVaksin();
        $anak = $this->anak();
        $this->kunjungan($anak, '2025-09-30', ['ntob' => 'T', 'zscore_bb_u' => -2.5]);
        $this->kunjungan($anak, '2025-09-30', ['ntob' => 'N', 'zscore_bb_u' => 0]);
        $periode = PeriodeKesmas::dari(2025, 'tw3');

        $k4 = $this->svc->pemantauanTk($periode, []);
        $drilldown = $this->svc->registri($periode, [], 'semua', '', 'perhatian');

        $this->actingAs(User::factory()->create(['type' => 1]));
        $this->getJson(route('admin.kesmas.registri', ['tahun' => 2025, 'periode' => 'tw3', 'status_gizi' => 'perhatian']))
            ->assertOk()->assertJsonPath('total', $drilldown['total']);
        $this->get(route('admin.kesmas.dashboard', ['tahun' => 2025, 'periode' => 'tw3']))
            ->assertOk()->assertViewHas('tk', fn ($v) => $v['perhatian'] === $k4['perhatian']);

        $this->assertSame(0, $drilldown['total'], 'Registri memakai entri koreksi dengan id terbesar.');
        $this->assertSame($drilldown['total'], $k4['perhatian'],
            'Tautan K4 harus menampilkan sebanyak anak yang disebut kartu, termasuk setelah koreksi setanggal.');
    }

    public function test_sdidtk_memakai_triwulan_saat_kelengkapan_spm_memakai_semester_induk(): void
    {
        $anak = $this->anak();
        $this->kunjungan($anak, '2025-01-01');
        $this->kunjungan($anak, '2025-03-31');
        $this->kunjungan($anak, '2025-06-30', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $lain = $this->anak();
        $this->kunjungan($lain, '2025-01-01');
        $this->kunjungan($lain, '2025-03-31');
        $this->kunjungan($lain, '2025-07-01', ['ddtka' => 'Sesuai', 'vit_a' => 1]);

        $p = PeriodeKesmas::dari(2025, 'tw1');
        $spm = $this->svc->spmKohort($p, []);
        $tk = $this->svc->pemantauanTk($p, []);
        $sdidtk = $this->svc->sdidtk($p, []);

        $this->assertSame(1, $spm['anak_balita']['lengkap'], '30 Juni masih semester induk; 1 Juli tidak.');
        $this->assertSame(1, $tk['lengkap']);
        $this->assertSame(0, $sdidtk['total']['realisasi'], 'DDTKA di luar triwulan bukan realisasi SDIDTK triwulan.');
        $this->assertSame(2, $sdidtk['total']['sasaran']);
    }

    public function test_ckg_mengikuti_tanggal_penanda_sedangkan_gigi_mengikuti_tanggal_kunjungan(): void
    {
        $a = $this->anak();
        $this->kunjungan($a, '2024-12-31', ['tgl_penanda_ckg' => '2025-01-01', 'pemeriksaan_gigi' => 'Sehat', 'rujukan' => 'Dokter gigi']);
        $this->kunjungan($a, '2026-01-01', ['tgl_penanda_ckg' => '2025-12-31', 'pemeriksaan_gigi' => 'Sehat', 'rujukan' => 'Dokter gigi']);
        $b = $this->anak();
        $this->kunjungan($b, '2025-01-01', ['tgl_penanda_ckg' => '2024-12-31', 'pemeriksaan_gigi' => 'Karies', 'rujukan' => 'Dokter gigi']);
        $this->kunjungan($b, '2025-12-31', ['tgl_penanda_ckg' => '2026-01-01', 'pemeriksaan_gigi' => 'Sehat', 'rujukan' => 'Dokter gigi']);

        $ckg = $this->svc->ckg(PeriodeKesmas::dari(2025), []);

        $this->assertSame([2, 1], [$ckg['kelompok']['t2']['sasaran'], $ckg['kelompok']['t2']['realisasi']]);
        $this->assertSame(['terisi' => 2, 'sehat' => 1, 'persen_sehat' => 50.0], $ckg['gigi']);
        $this->assertSame(1, $ckg['rujuk_gigi'], 'Dua kunjungan rujuk tetap satu anak.');
    }

    public function test_semua_layanan_memisahkan_null_nol_dan_pernah_ya_serta_membatasi_periode(): void
    {
        $kolom = array_keys(config('kesmas.layanan'));
        $ya = array_fill_keys($kolom, 1);
        $tidak = array_fill_keys($kolom, 0);
        $pernah = $this->anak();
        $this->kunjungan($pernah, '2025-01-01', $ya);
        $this->kunjungan($pernah, '2025-03-31', $tidak);
        $nol = $this->anak();
        $this->kunjungan($nol, '2025-01-15', $tidak);
        $kosong = $this->anak();
        $this->kunjungan($kosong, '2025-02-01');
        $luar = $this->anak();
        $this->kunjungan($luar, '2024-12-31', $ya);
        $this->kunjungan($luar, '2025-04-01', $ya);
        $this->anak();
        $tua = $this->anak('2018-12-31'); // 75 bulan pada akhir TW I.
        $this->kunjungan($tua, '2025-02-01', $ya);

        $hasil = $this->svc->layananLingkungan(PeriodeKesmas::dari(2025, 'tw1'), []);

        $this->assertSame(5, $hasil['sasaran']);
        foreach ($kolom as $k) {
            $baris = $hasil['layanan']['baris'][$k];
            $this->assertSame([1, 2, 50.0, 3], [$baris['ya'], $baris['terisi'], $baris['persen'], $baris['belum_diisi']], $k);
        }
    }

    public function test_batas_umur_tepat_72_83_bulan_dan_lahir_sehari_setelah_akhir_periode(): void
    {
        $this->seedVaksin();
        $this->anak('2019-12-31', ['nama' => 'Tepat 72']);
        $this->anak('2019-11-30', ['nama' => 'Tepat 73']);
        $this->anak('2019-01-31', ['nama' => 'Tepat 83']);
        $this->anak('2018-12-31', ['nama' => 'Tepat 84']);
        $this->anak('2025-12-31', ['nama' => 'Lahir hari akhir']);
        $this->anak('2026-01-01', ['nama' => 'Lahir besok']);
        $p = PeriodeKesmas::dari(2025);

        $sasaran = $this->svc->sasaran($p, []);
        $this->assertSame(2, $sasaran['semua']);
        $this->assertSame(1, $sasaran['prasekolah']);
        $this->assertSame(1, $sasaran['bayi']);
        $this->assertSame(3, $sasaran['tahun_3_6']);
        $this->assertSame(3, $this->svc->ckg($p, [])['kelompok']['t3']['sasaran']);
        $this->assertSame(['Lahir hari akhir', 'Tepat 72'], array_column($this->svc->registri($p, [])['data'], 'nama'));
    }

    public function test_filter_catchment_dan_wilayah_digabung_and_di_semua_agregat_dan_registri(): void
    {
        $this->seedVaksin();
        $pkm = Puskesmas::create(['name' => 'Puskesmas Bontang Selatan I', 'id_kecamatan' => $this->kec->id]);
        $pkmLain = Puskesmas::create(['name' => 'Bontang Selatan 2', 'id_kecamatan' => $this->kec->id]);
        $pos = Posyandu::create(['name' => 'Swarm Melati', 'id_puskesmas' => $pkmLain->id]);
        $posLain = Posyandu::create(['name' => 'Swarm Mawar', 'id_puskesmas' => $pkm->id]);
        $rt = Rt::create(['name' => '01', 'id_kelurahan' => $this->kel->id, 'id_posyandu' => $pos->id]);
        $kelKembarCatchment = Kelurahan::create(['name' => 'Satimpo', 'id_kecamatan' => $this->kec->id]);
        $kelLuar = Kelurahan::create(['name' => 'Berbas Pantai', 'id_kecamatan' => $this->kec->id]);
        $anak = [
            $this->anak('2025-05-01', ['nama' => 'Terpilih', 'id_rt' => $rt->id, 'id_posyandu' => $pos->id]),
            $this->anak('2025-05-01', ['id_rt' => $rt->id, 'id_posyandu' => $posLain->id]),
            $this->anak('2025-05-01', ['id_kel' => $kelKembarCatchment->id, 'id_posyandu' => $pos->id]),
            $this->anak('2025-05-01', ['id_kel' => $kelLuar->id, 'id_posyandu' => $pos->id]),
        ];
        foreach ($anak as $a) {
            $this->kunjungan($a, '2025-10-01', ['ddtka' => 'Sesuai', 'vit_a' => 1, 'kn1' => 1, 'tgl_penanda_ckg' => '2025-10-01']);
            $this->kunjungan($a, '2025-12-31', ['ntob' => 'T']);
        }
        $p = PeriodeKesmas::dari(2025, 'tw4');
        $catchment = ['id_puskesmas' => $pkm->id];
        $this->assertSame(3, $this->svc->sasaran($p, $catchment)['semua'], 'Catchment mengikuti kelurahan, bukan induk posyandu.');
        $filter = $catchment + ['id_kecamatan' => $this->kec->id, 'id_kelurahan' => $this->kel->id, 'id_rt' => $rt->id, 'id_posyandu' => $pos->id];

        $this->assertSame(1, $this->svc->sasaran($p, $filter)['semua']);
        $this->assertSame(1, $this->svc->spmKohort($p, $filter)['bayi']['lengkap']);
        $this->assertSame(1, $this->svc->pemantauanTk($p, $filter)['perhatian']);
        $this->assertSame(1, $this->svc->sdidtk($p, $filter)['total']['realisasi']);
        $this->assertSame(1, $this->svc->ckg($p, $filter)['kelompok']['t0']['realisasi']);
        $this->assertSame(1, $this->svc->layananLingkungan($p, $filter)['layanan']['baris']['kn1']['ya']);
        $this->assertSame(['Terpilih'], array_column($this->svc->registri($p, $filter)['data'], 'nama'));
        $this->assertSame(0, $this->svc->sasaran($p, $catchment + ['id_kelurahan' => $kelLuar->id])['semua'], 'Wilayah yang bertentangan menghasilkan kosong, bukan salah satu filter dibuang.');
        $this->assertSame(1, $this->svc->sasaran($p, ['id_puskesmas' => $pkmLain->id])['semua'], 'Cache tidak boleh mencampur ID puskesmas.');
        $this->assertSame(0, $this->svc->sasaran($p, ['id_puskesmas' => 999999])['semua']);
        $this->assertSame(3, $this->svc->sasaran($p, $catchment)['semua']);
    }

    public function test_filter_gizi_sql_sesuai_enum_pada_ambang_dan_outlier(): void
    {
        $this->seedVaksin();
        $gizi = app(StatusGiziService::class);
        $kasus = [
            [-2.01, null, null], [-2.00, null, null], [-3.01, null, null],
            [null, -6.01, null], [null, -6.00, null], [null, -2.01, null], [null, -2.00, null],
            [null, null, -3.01], [null, null, -2.01], [null, null, -2.00],
        ];
        $expected = ['stunted' => [], 'underweight' => [], 'wasted' => []];
        foreach ($kasus as $z) {
            $a = $this->anak();
            $this->kunjungan($a, '2025-09-30', ['zscore_bb_u' => $z[0], 'zscore_pb_u' => $z[1], 'zscore_bb_pb' => $z[2]]);
            $enum = $gizi->enumEppgbm(...$z);
            foreach (['stunted' => 'tb_u', 'underweight' => 'bb_u', 'wasted' => 'bb_tb'] as $status => $dimensi) {
                if (in_array($enum[$dimensi], [$status, 'severely_' . $status], true)) {
                    $expected[$status][] = $a->id;
                }
            }
        }
        foreach ($expected as $status => $ids) {
            $actual = array_column($this->svc->registri(PeriodeKesmas::dari(2025), [], 'semua', '', $status)['data'], 'id');
            sort($ids);
            sort($actual);
            $this->assertSame($ids, $actual, $status);
        }
    }

    public function test_enam_agregat_dengan_filter_puskesmas_tetap_dalam_anggaran_20_query(): void
    {
        $pkm = Puskesmas::create(['name' => 'Bontang Selatan 1', 'id_kecamatan' => $this->kec->id]);
        $this->anak();
        $p = PeriodeKesmas::dari(2025);
        $filter = ['id_puskesmas' => $pkm->id];
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $sasaran = $this->svc->sasaran($p, $filter);
            $this->svc->spmKohort($p, $filter);
            $this->svc->pemantauanTk($p, $filter);
            $this->svc->sdidtk($p, $filter);
            $this->svc->ckg($p, $filter);
            $this->svc->layananLingkungan($p, $filter);
            $jumlahQuery = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame(1, $sasaran['semua']);
        $this->assertLessThanOrEqual(20, $jumlahQuery, 'Anggaran spec berlaku juga saat petugas memilih puskesmas.');
    }

    public function test_kartu_idl_ibl_mengikuti_tahun_kohort_dan_wilayah_tetapi_bukan_triwulan(): void
    {
        $this->seedVaksin();
        $idl2025 = $this->anak('2024-04-01');
        $ibl2025 = $this->anak('2023-04-01');
        $this->anak('2025-04-01');
        $kelLain = Kelurahan::create(['name' => 'Satimpo', 'id_kecamatan' => $this->kec->id]);
        $this->anak('2024-04-01', ['id_kel' => $kelLain->id]);
        foreach (['IDL' => $idl2025, 'IBL' => $ibl2025] as $kode => $anak) {
            $kelompok = KelompokVaksin::where('kode', $kode)->firstOrFail();
            foreach (JenisVaksin::where('id_kelompok_vaksin', $kelompok->id)->get() as $vaksin) {
                Imunisasi::create(['id_anak' => $anak->id, 'id_jenis_vaksin' => $vaksin->id, 'dosis' => 1, 'status' => 'sudah', 'tanggal_pemberian' => '2025-12-01']);
            }
        }
        $this->actingAs(User::factory()->create(['type' => 1]));
        foreach (['tw1', 'tw4'] as $periode) {
            $this->get(route('admin.kesmas.dashboard', ['tahun' => 2025, 'periode' => $periode, 'id_kelurahan' => $this->kel->id]))
                ->assertOk()
                ->assertViewHas('idl', fn ($v) => $v['total'] === 1 && $v['idl_lengkap'] === 1)
                ->assertViewHas('ibl', fn ($v) => $v['total'] === 1 && $v['ibl_lengkap'] === 1);
        }
        $this->get(route('admin.kesmas.dashboard', ['tahun' => 2026, 'periode' => 'tw1', 'id_kelurahan' => $this->kel->id]))
            ->assertOk()
            ->assertViewHas('idl', fn ($v) => $v['total'] === 1 && $v['idl_lengkap'] === 0)
            ->assertViewHas('ibl', fn ($v) => $v['total'] === 1 && $v['ibl_lengkap'] === 0);
    }
}
