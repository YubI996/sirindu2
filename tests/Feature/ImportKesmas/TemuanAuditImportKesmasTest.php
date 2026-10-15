<?php

namespace Tests\Feature\ImportKesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\ImportLog;
use App\Models\User;
use App\Services\PenautanAnakKesmas;
use App\Services\PrioritasGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Temuan Tinggi + SEC-001 audit swarm modul Import Data Kesmas (15 Okt 2026,
 * docs/audit/2026-10-15-audit-import-kesmas.md).
 */
class TemuanAuditImportKesmasTest extends TestCase
{
    use RefreshDatabase;

    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @var string[] */
    private array $sementara = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['queue.default' => 'sync']);
        $this->actingAs(User::factory()->create(['type' => 0]));
    }

    protected function tearDown(): void
    {
        foreach ($this->sementara as $p) {
            @unlink($p);
        }
        parent::tearDown();
    }

    private function anak(array $isi = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah',
        ], $isi));
    }

    private function berkas(array $baris): UploadedFile
    {
        $ss = new Spreadsheet();
        $ss->getActiveSheet()->fromArray($baris);
        $path = tempnam(sys_get_temp_dir(), 'audit').'.xlsx';
        (new Xlsx($ss))->save($path);
        $this->sementara[] = $path;

        return new UploadedFile($path, 'kesmas.xlsx', self::MIME_XLSX, null, true);
    }

    private function unggahJson(UploadedFile $file): ImportLog
    {
        $id = $this->postJson(route('admin.importCsv.kesmas'), ['file_kesmas' => $file])
            ->assertOk()->json('log_id');

        return ImportLog::findOrFail($id);
    }

    // ── UX-002 / A11Y-002: galat validasi unggah tampil, tab asal tetap terbuka ──

    public function test_galat_validasi_unggah_tampil_dan_tab_kesmas_tetap_terbuka(): void
    {
        $this->followingRedirects()
            ->from(route('admin.importCsv.index'))
            ->post(route('admin.importCsv.kesmas'), [
                'file_kesmas' => UploadedFile::fake()->createWithContent('kesmas.csv', "nama_anak\n"),
                'tab' => 'kesmas',
            ])
            ->assertOk()
            ->assertSee('File harus berformat Excel (.xlsx/.xls).')
            ->assertSee('var TAB_AWAL = "kesmas"', false);
    }

    public function test_unggah_berhasil_kembali_ke_tab_asal(): void
    {
        Queue::fake();

        $this->followingRedirects()
            ->from(route('admin.importCsv.index'))
            ->post(route('admin.importCsv.kesmas'), [
                'file_kesmas' => $this->berkas([['nama_anak', 'jk', 'tgl_lahir', 'nama_ortu']]),
                'tab' => 'kesmas',
            ])
            ->assertOk()
            ->assertSee('var TAB_AWAL = "kesmas"', false);
    }

    public function test_setiap_form_import_mengirim_nama_tabnya(): void
    {
        $html = $this->get(route('admin.importCsv.index'))->assertOk()->getContent();

        foreach (['anak', 'capil', 'pengukuran', 'imunisasi', 'ukur', 'pj', 'kesmas'] as $tab) {
            $this->assertStringContainsString('<input type="hidden" name="tab" value="'.$tab.'">', $html, "form {$tab}");
        }
    }

    // ── UX-001: header salah = import gagal, bukan "Selesai" ──

    public function test_berkas_header_salah_berstatus_gagal(): void
    {
        $log = $this->unggahJson($this->berkas([['nama_anak', 'jk', 'tgl_lahir', 'air_bersih'], ['Budi Santoso', 'L', '2023-01-01', 'Ya']]));

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('kolom wajib tidak ada: nama_ortu', implode("\n", $log->failures ?? []));
    }

    // ── DYN-UX-001: tidak menghitung ulang prioritas gizi se-kota ──

    public function test_import_tanpa_kunjungan_baru_tidak_menghitung_ulang_prioritas(): void
    {
        $this->anak();
        $this->partialMock(PrioritasGiziService::class, function ($m) {
            $m->shouldNotReceive('refreshAll');
            $m->shouldNotReceive('refreshBatch');
        });

        $log = $this->unggahJson($this->berkas([
            ['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'nama_ortu', 'air_bersih'],
            ['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah', 'Ya'],
        ]));

        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
    }

    public function test_kunjungan_baru_hanya_menghitung_ulang_anak_itu(): void
    {
        $budi = $this->anak();
        $this->partialMock(PrioritasGiziService::class, function ($m) use ($budi) {
            $m->shouldNotReceive('refreshAll');
            $m->shouldReceive('refreshBatch')->once()->with([$budi->id]);
        });

        $log = $this->unggahJson($this->berkas([
            ['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'nama_ortu', 'tgl_kunjungan', 'bb', 'tb_cm', 'posisi'],
            ['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah', '2025-03-10', '10,5', '85', 'Berdiri'],
        ]));

        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
        $this->assertSame(1, DataAnak::count());
    }

    // ── A11Y-001: penanda fokus keyboard terlihat ──

    public function test_halaman_import_memasang_penanda_fokus(): void
    {
        $html = $this->get(route('admin.importCsv.index'))->assertOk()->getContent();

        // Aturan global admin `a,button,input{outline:0!important}` hanya bisa dikalahkan !important.
        $this->assertMatchesRegularExpression('/\.imp-page[^{]*:focus-visible[^{]*\{[^}]*outline:[^;}]*solid[^;}]*!important/', $html);
    }

    // ── SEC-001: NIK + tanggal lahir sama saja tidak cukup ──

    public function test_nik_anak_lain_lahir_sama_hari_tidak_diterima_tanpa_nama_atau_ibu_cocok(): void
    {
        $citra = $this->anak(['nama' => 'Citra Lestari', 'nik' => '6474014101230002', 'jk' => 2, 'nama_ibu' => 'Rina Marlina']);

        // Baris anak lain (belum terdaftar) yang kebetulan lahir di hari yang sama, NIK salah ketik = NIK Citra.
        $hasil = (new PenautanAnakKesmas())->cari('Dewi Anggraini', 'P', '2023-01-01', 'Sri Wahyuni', '6474014101230002');

        $this->assertNotSame($citra->id, $hasil['anak']?->id);
        $this->assertContains('PERINGATAN', array_column($hasil['pesan'], 0));
    }

    public function test_nik_lahir_sama_nama_panggilan_beda_diterima_bila_jk_dan_ibu_cocok(): void
    {
        $budi = $this->anak(['nama' => 'Muhammad Budi Santoso']);

        $hasil = (new PenautanAnakKesmas())->cari('Budi', 'L', '2023-01-01', 'SITI AMINAH', '6474010101230001');

        $this->assertSame($budi->id, $hasil['anak']?->id);
        $this->assertSame(['INFO'], array_column($hasil['pesan'], 0));
    }

    public function test_nik_lahir_sama_nama_beda_tanpa_nama_ibu_di_data_tidak_diterima(): void
    {
        $budi = $this->anak(['nama' => 'Muhammad Budi Santoso', 'nama_ibu' => null]);

        $hasil = (new PenautanAnakKesmas())->cari('Budi', 'L', '2023-01-01', 'Siti Aminah', '6474010101230001');

        $this->assertNotSame($budi->id, $hasil['anak']?->id);
    }

    public function test_nik_lahir_sama_nama_beda_jk_beda_tidak_diterima(): void
    {
        $budi = $this->anak(['nama' => 'Muhammad Budi Santoso']);

        $hasil = (new PenautanAnakKesmas())->cari('Budi', 'P', '2023-01-01', 'Siti Aminah', '6474010101230001');

        $this->assertNotSame($budi->id, $hasil['anak']?->id);
    }
}
