<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Puskesmas;
use App\Models\RumahSakit;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Reproduksi bug klien 2026-09-06: user Surveilans Puskesmas membuka
 * Export Data -> Laporan Kasus PD3I, memilih penyakit + tahun, unduh -> error.
 *
 * Rute admin.export.pd3i.index / .download tidak menerapkan scope wilayah
 * (LaporanKasusIndividuExport::query() tidak memakai scopeVisibleTo), jadi
 * tes ini berfokus mencari ERROR/EXCEPTION saat proses download, bukan hanya
 * memverifikasi kebocoran cakupan data (sudah diketahui & dikonfirmasi terpisah).
 */
class LaporanPd3iExportPuskesmasTest extends TestCase
{
    use DatabaseTransactions;

    private User $superadmin;
    private User $userPuskesmas;
    private User $userRs;
    private Kecamatan $kecamatan;
    private Kelurahan $kelurahan;
    private Rt $rt;
    private Puskesmas $puskesmas;
    private RumahSakit $rs;
    private JenisKasusEpidemiologi $disease;

    protected function setUp(): void
    {
        parent::setUp();

        WilkerPuskesmas::flushCache();

        $this->kecamatan = Kecamatan::factory()->create();
        $this->kelurahan = Kelurahan::factory()->create([
            'id_kecamatan' => $this->kecamatan->id,
            'name'         => 'API-API',
        ]);
        $this->rt = Rt::factory()->create(['id_kelurahan' => $this->kelurahan->id]);

        $this->puskesmas = Puskesmas::factory()->create([
            'id_kecamatan' => $this->kecamatan->id,
            'name'         => 'Puskesmas Bontang Utara I',
        ]);
        $this->rs = RumahSakit::create([
            'name'      => 'RS Uji Export',
            'kode_rs'   => 'RS-EXP-1',
            'is_active' => true,
        ]);

        $this->disease = JenisKasusEpidemiologi::factory()->create([
            'kode_penyakit' => 'CAMPAK_RUBELLA',
            'nama_penyakit' => 'Campak-Rubella',
            'is_active'     => true,
        ]);

        $this->superadmin = User::factory()->create(['type' => 0, 'role' => 'superadmin']);

        $this->userPuskesmas = User::factory()->create([
            'type'         => 1,
            'role'         => 'surveilans_puskesmas',
            'faskes_type'  => 'puskesmas',
            'id_puskesmas' => $this->puskesmas->id,
        ]);

        $this->userRs = User::factory()->create([
            'type'        => 1,
            'role'        => 'surveilans_rs',
            'faskes_type' => 'rs',
            'id_rs'       => $this->rs->id,
        ]);
    }

    private function kasusRapi(array $overrides = []): SurveillanceCase
    {
        return SurveillanceCase::factory()->create(array_merge([
            'id_kec'         => $this->kecamatan->id,
            'id_kel'         => $this->kelurahan->id,
            'id_rt'          => $this->rt->id,
            'id_jenis_kasus' => $this->disease->id,
            'faskes_type'    => 'puskesmas',
            'id_faskes'      => $this->puskesmas->id,
            'tanggal_lapor'  => now()->format('Y-m-d'),
        ], $overrides));
    }

    // ─────────────── Halaman index ───────────────

    public function test_puskesmas_bisa_buka_halaman_index(): void
    {
        $this->kasusRapi();

        $this->actingAs($this->userPuskesmas)
            ->get(route('admin.export.pd3i.index'))
            ->assertOk();
    }

    // ─────────────── Download - kasus normal ───────────────

    public function test_puskesmas_unduh_dengan_data_rapi(): void
    {
        $this->kasusRapi();

        $response = $this->actingAs($this->userPuskesmas)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $this->disease->id,
                'tahun'          => now()->year,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_rs_unduh_dengan_data_rapi(): void
    {
        $this->kasusRapi(['faskes_type' => 'rs', 'id_faskes' => $this->rs->id]);

        $response = $this->actingAs($this->userRs)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $this->disease->id,
                'tahun'          => now()->year,
            ]));

        $response->assertOk();
    }

    public function test_superadmin_unduh_dengan_data_rapi(): void
    {
        $this->kasusRapi();

        $response = $this->actingAs($this->superadmin)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $this->disease->id,
                'tahun'          => now()->year,
            ]));

        $response->assertOk();
    }

    // ─────────────── Download - tahun tanpa kasus (query kosong) ───────────────

    public function test_puskesmas_unduh_tahun_tanpa_kasus_sama_sekali(): void
    {
        // Tidak ada kasus dibuat sama sekali untuk tahun ini.
        $response = $this->actingAs($this->userPuskesmas)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $this->disease->id,
                'tahun'          => 2019,
            ]));

        $response->assertOk();
    }

    // ─────────────── Download - data "tidak rapi" ───────────────

    public function test_puskesmas_unduh_kasus_tanggal_lahir_null(): void
    {
        $this->kasusRapi(['tanggal_lahir' => null]);

        $response = $this->actingAs($this->userPuskesmas)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $this->disease->id,
                'tahun'          => now()->year,
            ]));

        $response->assertOk();
    }

    // NB: id_kel/id_kec/tanggal_lapor/alamat_lengkap TERNYATA NOT NULL di skema DB
    // (surveillance_cases, dan FK id_kel/id_kec ON DELETE RESTRICT) — dicoba null
    // dan dicoba menunjuk baris tak ada (999999), keduanya ditolak DB (integrity
    // constraint / FK violation) sebelum sempat sampai ke Excel::download() sama
    // sekali. Jadi skenario "relasi kelurahan/kecamatan hilang" & "tanggal_lapor
    // NULL" TIDAK MUNGKIN terjadi lewat aplikasi ini untuk baris yang lolos insert
    // — dihapus dari suite ini karena tak representatif (lihat laporan akhir agent).

    public function test_puskesmas_unduh_kasus_relasi_jenis_kasus_hilang(): void
    {
        // id_jenis_kasus menunjuk baris yang tak ada lagi (mis. dihapus) —
        // meski begitu, filter query where('id_jenis_kasus', ...) tetap harus
        // konsisten dengan ID yang dipilih user di form.
        $case = $this->kasusRapi();
        JenisKasusEpidemiologi::where('id', $this->disease->id)->delete(); // soft delete

        $response = $this->actingAs($this->userPuskesmas)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $this->disease->id,
                'tahun'          => now()->year,
            ]));

        // exists:jenis_kasus_epidemiologi,id tidak menolak ID yang soft-deleted
        // (rule `exists` tak sadar SoftDeletes), jadi validasi lolos; findOrFail()
        // di controller (yang MEMANG menghormati SoftDeletes) lalu 404 — bukan 500.
        $response->assertNotFound();
    }

    public function test_puskesmas_unduh_kasus_spesimen_kosong(): void
    {
        // Kasus tanpa baris spesimen sama sekali (AFP butuh Spesimen I/II).
        $afp = JenisKasusEpidemiologi::factory()->create([
            'kode_penyakit' => 'AFP',
            'nama_penyakit' => 'AFP',
            'is_active'     => true,
        ]);
        $this->kasusRapi(['id_jenis_kasus' => $afp->id]);

        $response = $this->actingAs($this->userPuskesmas)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $afp->id,
                'tahun'          => now()->year,
            ]));

        $response->assertOk();
    }

    public function test_puskesmas_unduh_banyak_field_null_sekaligus(): void
    {
        // Kombinasi realistis data prod: kolom-kolom yang MEMANG nullable
        // (tanggal_lahir, id_rt, hasil_lab, nama_orang_tua, tanggal_konsultasi)
        // kosong sekaligus. id_kel/id_kec/alamat_lengkap/tanggal_lapor/status_kasus
        // sengaja tak disertakan karena NOT NULL di skema (lihat catatan di atas).
        $this->kasusRapi([
            'tanggal_lahir'      => null,
            'id_rt'              => null,
            'hasil_lab'          => null,
            'nama_orang_tua'     => null,
            'tanggal_konsultasi' => null,
        ]);

        $response = $this->actingAs($this->userPuskesmas)
            ->get(route('admin.export.pd3i.download', [
                'jenis_kasus_id' => $this->disease->id,
                'tahun'          => now()->year,
            ]));

        $response->assertOk();
    }
}
