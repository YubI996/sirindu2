<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\RumahSakit;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Reproduksi laporan klien (2026-09-06): user faskes menekan tombol "Cetak PDF"
 * pada kasus PD3I miliknya sendiri dan mendapat error.
 *
 * Tombolnya ada di halaman detail kasus (show.blade.php), dijangkau dari tabel
 * daftar kasus lewat ikon mata.
 */
class ExportPdfFaskesTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $userRs;
    private RumahSakit $rs;
    private Kecamatan $kecamatan;
    private Kelurahan $kelurahan;
    private Rt $rt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = User::factory()->create(['type' => 0]);
        $this->kecamatan = Kecamatan::factory()->create();
        $this->kelurahan = Kelurahan::factory()->create(['id_kecamatan' => $this->kecamatan->id]);
        $this->rt        = Rt::factory()->create(['id_kelurahan' => $this->kelurahan->id]);

        $this->rs = RumahSakit::create(['name' => 'RS Uji PDF', 'kode_rs' => 'RS-PDF-1', 'is_active' => true]);
        $this->userRs = User::factory()->create([
            'type' => 1, 'role' => 'surveilans_rs', 'faskes_type' => 'rs', 'id_rs' => $this->rs->id,
        ]);
    }

    private function kasus(string $kodePenyakit): SurveillanceCase
    {
        $disease = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => $kodePenyakit]);

        return SurveillanceCase::factory()->create([
            'id_kec'         => $this->kecamatan->id,
            'id_kel'         => $this->kelurahan->id,
            'id_rt'          => $this->rt->id,
            'id_jenis_kasus' => $disease->id,
            'faskes_type'    => 'rs',
            'id_faskes'      => $this->rs->id,
            'created_by'     => $this->admin->id,
            'updated_by'     => $this->admin->id,
            'tanggal_onset'  => Carbon::now()->subDays(5)->format('Y-m-d'),
        ]);
    }

    /** @return array<int,array{0:string}> */
    public static function penyakitProvider(): array
    {
        return [
            'campak-rubella (MR-01)' => ['CAMPAK_RUBELLA'],
            'AFP (FP-1)'             => ['AFP'],
            'difteri (DIF-1)'        => ['DIFTERI_OBS'],
            'pertusis (PERT-01)'     => ['PERTUSIS'],
            'tetanus neo (TN-01)'    => ['TETANUS_NEO'],
        ];
    }

    /**
     * @dataProvider penyakitProvider
     */
    public function test_faskes_dapat_mengunduh_pdf_kasus_miliknya(string $kodePenyakit): void
    {
        $case = $this->kasus($kodePenyakit);

        $response = $this->actingAs($this->userRs)
            ->get(route('admin.epidemiologi.exportPdf', $case->id));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_faskes_ditolak_saat_mengunduh_pdf_kasus_faskes_lain(): void
    {
        $rsLain = RumahSakit::create(['name' => 'RS Lain', 'kode_rs' => 'RS-PDF-2', 'is_active' => true]);
        $case   = $this->kasus('CAMPAK_RUBELLA');
        $case->update(['id_faskes' => $rsLain->id]);

        $this->actingAs($this->userRs)
            ->get(route('admin.epidemiologi.exportPdf', $case->id))
            ->assertForbidden();
    }
}
