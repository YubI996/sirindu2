<?php

namespace Tests\Feature\Epidemiologi;

use App\Exports\LaporanKasusIndividuExport;
use App\Exports\SurveillanceExport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\RumahSakit;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Support\WilkerPuskesmas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Kebijakan export faskes: BOLEH mengunduh, TAPI hanya datanya sendiri.
 *
 * Sebelum ini aturannya berbeda-beda di lima endpoint — satu menolak dengan
 * 403 (EpidemiologiController::exportExcel), tiga lainnya membiarkan faskes
 * mengunduh data pasien SELURUH KOTA karena SurveillanceExport dan
 * LaporanKasusIndividuExport tidak memakai scope visibleTo sama sekali.
 * Tombol Excel/PDF di Dashboard PD3I pun tanpa penjagaan peran.
 *
 * Cakupan mengikuti SurveillanceCase::scopeVisibleTo(): puskesmas = kelurahan
 * catchment wilker-nya + kasus yang ia laporkan; RS = kasus yang ia input;
 * superadmin/Dinkes = seluruh kota.
 */
class ExportScopingFaskesTest extends TestCase
{
    use DatabaseTransactions;

    private User $superadmin;
    private User $userPuskesmas;
    private User $userRs;
    private Kelurahan $kelWilker;
    private Kelurahan $kelLuar;
    private Rt $rt;
    private Rt $rtLuar;
    private Puskesmas $puskesmas;
    private RumahSakit $rs;
    private JenisKasusEpidemiologi $disease;

    protected function setUp(): void
    {
        parent::setUp();

        WilkerPuskesmas::flushCache();

        $kec = Kecamatan::factory()->create();

        // "Bontang Utara I" membawahi kelurahan API-API (lihat WilkerPuskesmas).
        $this->kelWilker = Kelurahan::factory()->create(['id_kecamatan' => $kec->id, 'name' => 'API-API']);
        $this->kelLuar   = Kelurahan::factory()->create(['id_kecamatan' => $kec->id, 'name' => 'SATIMPO']);
        $this->rt        = Rt::factory()->create(['id_kelurahan' => $this->kelWilker->id]);
        $this->rtLuar    = Rt::factory()->create(['id_kelurahan' => $this->kelLuar->id]);

        $this->puskesmas = Puskesmas::factory()->create([
            'id_kecamatan' => $kec->id,
            'name'         => 'Puskesmas Bontang Utara I',
        ]);
        $this->rs = RumahSakit::create(['name' => 'RS Uji Scope', 'kode_rs' => 'RS-SCOPE', 'is_active' => true]);

        $this->disease = JenisKasusEpidemiologi::factory()->create([
            'kode_penyakit' => 'CAMPAK_RUBELLA',
            'nama_penyakit' => 'Campak-Rubella',
            'is_active'     => true,
        ]);

        $this->superadmin    = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $this->userPuskesmas = User::factory()->create([
            'type' => 1, 'role' => 'surveilans_puskesmas',
            'faskes_type' => 'puskesmas', 'id_puskesmas' => $this->puskesmas->id,
        ]);
        $this->userRs = User::factory()->create([
            'type' => 1, 'role' => 'surveilans_rs',
            'faskes_type' => 'rs', 'id_rs' => $this->rs->id,
        ]);

        // Satu kasus di dalam wilker puskesmas, satu di luar (milik RS).
        $this->kasus($this->kelWilker->id, $this->rt->id, 'puskesmas', $this->puskesmas->id);
        $this->kasus($this->kelLuar->id, $this->rtLuar->id, 'rs', $this->rs->id);
    }

    private function kasus(int $idKel, int $idRt, string $faskesType, int $idFaskes): SurveillanceCase
    {
        return SurveillanceCase::factory()->create([
            'id_kec'         => Kelurahan::find($idKel)->id_kecamatan,
            'id_kel'         => $idKel,
            'id_rt'          => $idRt,
            'id_jenis_kasus' => $this->disease->id,
            'faskes_type'    => $faskesType,
            'id_faskes'      => $idFaskes,
            'created_by'     => $this->superadmin->id,
            'updated_by'     => $this->superadmin->id,
            'tanggal_lapor'  => Carbon::create(2026, 5, 10),
            'tanggal_onset'  => Carbon::create(2026, 5, 5),
        ]);
    }

    private function jumlahSurveillanceExport(): int
    {
        return (new SurveillanceExport(tahun: null))->query()->count();
    }

    private function jumlahLaporanExport(): int
    {
        return (new LaporanKasusIndividuExport(2026, $this->disease->id))->query()->count();
    }

    // ── SurveillanceExport (Daftar Kasus + Dashboard PD3I) ──

    public function test_surveillance_export_dinkes_melihat_seluruh_kota(): void
    {
        $this->actingAs($this->superadmin);

        $this->assertSame(2, $this->jumlahSurveillanceExport());
    }

    public function test_surveillance_export_puskesmas_hanya_wilayahnya(): void
    {
        $this->actingAs($this->userPuskesmas);

        $this->assertSame(1, $this->jumlahSurveillanceExport());
    }

    public function test_surveillance_export_rs_hanya_kasus_yang_diinputnya(): void
    {
        $this->actingAs($this->userRs);

        $this->assertSame(1, $this->jumlahSurveillanceExport());
    }

    // ── LaporanKasusIndividuExport (Laporan Kasus PD3I) ──

    public function test_laporan_individu_dinkes_melihat_seluruh_kota(): void
    {
        $this->actingAs($this->superadmin);

        $this->assertSame(2, $this->jumlahLaporanExport());
    }

    public function test_laporan_individu_puskesmas_hanya_wilayahnya(): void
    {
        $this->actingAs($this->userPuskesmas);

        $this->assertSame(1, $this->jumlahLaporanExport());
    }

    public function test_laporan_individu_rs_hanya_kasus_yang_diinputnya(): void
    {
        $this->actingAs($this->userRs);

        $this->assertSame(1, $this->jumlahLaporanExport());
    }

    // ── Kelima endpoint kini seragam: boleh, tapi ter-scope ──

    public function test_faskes_tidak_lagi_ditolak_403_saat_export_excel_daftar_kasus(): void
    {
        $this->actingAs($this->userPuskesmas)
            ->get(route('admin.epidemiologi.exportExcel'))
            ->assertOk();
    }

    public function test_tombol_export_excel_tampil_untuk_faskes_di_daftar_kasus(): void
    {
        $this->actingAs($this->userPuskesmas)
            ->get(route('admin.epidemiologi.index'))
            ->assertOk()
            // aria-label tombolnya, bukan sekadar URL — URL itu juga muncul di
            // blok <script> walau tombolnya sedang disembunyikan.
            ->assertSee('aria-label="Export ke Excel"', false);
    }

    public function test_tombol_import_tetap_hanya_untuk_dinkes(): void
    {
        $this->actingAs($this->userPuskesmas)
            ->get(route('admin.epidemiologi.index'))
            ->assertOk()
            ->assertDontSee('modalImportPd3i', false);
    }

    public function test_faskes_boleh_export_excel_dashboard_pd3i(): void
    {
        $this->actingAs($this->userPuskesmas)
            ->get(route('admin.pd3i.exportExcel'))
            ->assertOk();
    }

    public function test_faskes_boleh_unduh_laporan_kasus_pd3i(): void
    {
        $this->actingAs($this->userPuskesmas)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $this->disease->id,
                'tahun'          => 2026,
            ]))
            ->assertOk();
    }
}
