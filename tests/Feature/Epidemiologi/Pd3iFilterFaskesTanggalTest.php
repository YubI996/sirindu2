<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Filter Faskes Pelapor & rentang Tanggal Laporan di Dasbor PD3I
 * (spec 2026-10-15-filter-pd3i-faskes-tanggal-design.md). Nama faskes unik per
 * tes supaya hitungan tak tercampur data lain di DB uji bersama.
 */
class Pd3iFilterFaskesTanggalTest extends TestCase
{
    use DatabaseTransactions;

    private const FASKES_A = 'PKM Uji Filter A';
    private const FASKES_B = 'RS Uji Filter B';

    private User $admin;
    private Kelurahan $kel;
    private Rt $rt;

    protected function setUp(): void
    {
        parent::setUp();

        $kec         = Kecamatan::factory()->create();
        $this->kel   = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $this->rt    = Rt::factory()->create(['id_kelurahan' => $this->kel->id]);
        $this->admin = User::factory()->create(['type' => 0, 'role' => 'superadmin']);

        // id 1 = Campak-Rubella (di-hardcode di getPd3iKinerja).
        if (!JenisKasusEpidemiologi::find(1)) {
            JenisKasusEpidemiologi::factory()->create([
                'id' => 1, 'kode_penyakit' => 'CAMPAK_RUBELLA', 'nama_penyakit' => 'Campak-Rubella', 'is_active' => true,
            ]);
        }

        // A: Februari & Juni; B: Juni.
        $this->kasus(self::FASKES_A, '2026-02-10');
        $this->kasus(self::FASKES_A, '2026-06-15');
        $this->kasus(self::FASKES_B, '2026-06-20');
    }

    private function kasus(string $instansi, string $tanggal): SurveillanceCase
    {
        return SurveillanceCase::factory()->create([
            'id_jenis_kasus'   => 1,
            'id_kec'           => $this->kel->id_kecamatan,
            'id_kel'           => $this->kel->id,
            'id_rt'            => $this->rt->id,
            'instansi_pelapor' => $instansi,
            'tanggal_lapor'    => $tanggal,
            'tanggal_onset'    => $tanggal,
            'id_petugas_input' => $this->admin->id,
            'created_by'       => $this->admin->id,
            'updated_by'       => $this->admin->id,
        ]);
    }

    private function api(string $rute, array $query): array
    {
        return $this->actingAs($this->admin)
            ->getJson(route($rute, ['tahun' => 2026] + $query))
            ->assertOk()
            ->json();
    }

    public function test_kinerja_hanya_menghitung_faskes_terpilih(): void
    {
        $data = $this->api('admin.pd3i.apiKinerja', ['faskes_pelapor' => [self::FASKES_A]]);
        $this->assertSame(2, $data['campak_rubella']['suspek']);

        $data = $this->api('admin.pd3i.apiKinerja', ['faskes_pelapor' => [self::FASKES_A, self::FASKES_B]]);
        $this->assertSame(3, $data['campak_rubella']['suspek']);
    }

    public function test_kinerja_menghormati_rentang_tanggal(): void
    {
        $data = $this->api('admin.pd3i.apiKinerja', [
            'faskes_pelapor' => [self::FASKES_A],
            'tanggal_dari'   => '2026-05-01',
            'tanggal_sampai' => '2026-06-30',
        ]);
        $this->assertSame(1, $data['campak_rubella']['suspek']);
    }

    public function test_tanggal_dari_url_yang_tak_sah_diabaikan(): void
    {
        foreach (['2025-05-01', '2026-02-30', '01/05/2026'] as $nilai) {
            $data = $this->api('admin.pd3i.apiKinerja', [
                'faskes_pelapor' => [self::FASKES_A],
                'tanggal_dari'   => $nilai,
            ]);
            $this->assertSame(2, $data['campak_rubella']['suspek'], "tanggal_dari={$nilai}");
        }
    }

    public function test_tren_tahunan_dan_epiweek_abaikan_rentang_tapi_ikut_faskes(): void
    {
        $data = $this->api('admin.pd3i.apiTren', [
            'faskes_pelapor' => [self::FASKES_A],
            'tanggal_dari'   => '2026-05-01',
            'tanggal_sampai' => '2026-06-30',
        ]);

        $tahun2026 = collect($data['tahunan'])->firstWhere('tahun', 2026);
        $this->assertSame(2, $tahun2026['total'], 'tahunan tidak dipersempit rentang, tapi hanya faskes A');
        $this->assertSame(2, array_sum(array_column($data['epiweek'], 'suspek')));

        $bulanan = collect($data['bulanan'])->keyBy('bulan');
        $this->assertSame(0, $bulanan[2]['total'], 'Februari di luar rentang');
        $this->assertSame(1, $bulanan[6]['total']);

        // per_faskes me-leftJoin rumah_sakits — kolom filter harus berprefix tabel.
        $perFaskes = collect($data['per_faskes']);
        $this->assertSame(1, $perFaskes->where('faskes', self::FASKES_A)->sum('jumlah'));
        $this->assertSame(0, $perFaskes->where('faskes', self::FASKES_B)->sum('jumlah'));
        $this->assertSame(1, collect($data['per_kecamatan'])->sum('jumlah'));
    }

    /**
     * Pd3iImport menyimpan instansi_pelapor tanpa trim. Nama berspasi di depan dulu
     * tampil di daftar opsi, tapi nilai yang dikirim di-trim → 0 kasus (audit REQ-001/UX-003).
     */
    public function test_nama_faskes_berspasi_tetap_cocok_dan_opsinya_tidak_kembar(): void
    {
        $this->kasus('  PKM Uji Berspasi', '2026-03-03');
        $this->kasus('PKM Uji Berspasi', '2026-03-04');

        $data = $this->api('admin.pd3i.apiKinerja', ['faskes_pelapor' => ['PKM Uji Berspasi']]);
        $this->assertSame(2, $data['campak_rubella']['suspek']);

        $this->actingAs($this->admin)->get(route('admin.pd3i.dashboard'))
            ->assertOk()
            ->assertViewHas('faskesPelapors', fn ($c) => $c->filter(fn ($n) => $n === 'PKM Uji Berspasi')->count() === 1
                && !$c->contains('  PKM Uji Berspasi'));
    }

    public function test_query_string_bersarang_tidak_menyebabkan_500(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.pd3i.apiKinerja', ['tahun' => 2026, 'faskes_pelapor' => [['x']], 'wilker' => [['y']]]))
            ->assertOk();
    }

    public function test_demografi_dan_wilayah_ikut_filter(): void
    {
        $query = ['faskes_pelapor' => [self::FASKES_A], 'tanggal_dari' => '2026-05-01'];

        $demografi = $this->api('admin.pd3i.apiDemografi', $query);
        $this->assertSame(1, array_sum($demografi['jenis_kelamin']));

        $wilayah = $this->api('admin.pd3i.apiWilayah', $query);
        $this->assertSame([self::FASKES_A], array_column($wilayah['per_faskes_pelapor'], 'faskes'));
        $this->assertSame(1, $wilayah['per_faskes_pelapor'][0]['total']);
    }
}
