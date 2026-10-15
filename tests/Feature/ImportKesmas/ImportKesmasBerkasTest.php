<?php

namespace Tests\Feature\ImportKesmas;

use App\Imports\KesmasImport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import Data Kesmas lewat halaman Import CSV: template unduhan, unggah Excel, reimport, akses.
 * Spec docs/superpowers/specs/2026-10-15-import-kesmas-design.md.
 */
class ImportKesmasBerkasTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        foreach ($this->sementara as $p) {
            @unlink($p);
        }
        parent::tearDown();
    }

    private function superadmin(): User
    {
        return User::factory()->create(['type' => 0]);
    }

    private function budi(): Anak
    {
        return Anak::create(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah']);
    }

    /** Unduh template lewat rute, isi baris 2 dari peta kolom => nilai, simpan ke berkas sementara. */
    private function templateTerisi(array $isi): string
    {
        $res = $this->get(route('admin.importCsv.template', 'kesmas'))->assertOk();
        $ss = IOFactory::load($res->baseResponse->getFile()->getPathname());
        $ws = $ss->getSheetByName('Data');
        $header = $ws->rangeToArray('A1:'.$ws->getHighestColumn().'1')[0];
        foreach ($isi as $kolom => $nilai) {
            $c = array_search($kolom, $header, true) + 1;
            $sel = $ws->getCell([$c, 2]);
            if (is_array($nilai) && isset($nilai['tgl'])) {
                $sel->setValue(Date::PHPToExcel(new \DateTime($nilai['tgl'])));
                $sel->getStyle()->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            } else {
                $sel->setValueExplicit((string) $nilai, DataType::TYPE_STRING);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'kesmas').'.xlsx';
        (new Xlsx($ss))->save($path);
        $this->sementara[] = $path;

        return $path;
    }

    private function unggah(string $path, string $format = 'auto')
    {
        return $this->postJson(route('admin.importCsv.kesmas'), [
            'file_kesmas' => new UploadedFile($path, 'kesmas.xlsx', self::MIME_XLSX, null, true),
            'format_tanggal' => $format,
        ]);
    }

    public function test_template_dua_sheet_dengan_header_kolom_dikenal_dan_dropdown(): void
    {
        $this->actingAs($this->superadmin());

        $res = $this->get(route('admin.importCsv.template', 'kesmas'))->assertOk();
        $ss = IOFactory::load($res->baseResponse->getFile()->getPathname());

        $this->assertSame(['Data', 'Petunjuk'], $ss->getSheetNames());
        $ws = $ss->getSheetByName('Data');
        $header = array_values(array_filter($ws->rangeToArray('A1:'.$ws->getHighestColumn().'1')[0]));
        $this->assertEqualsCanonicalizing(KesmasImport::kolomDikenal(), $header);
        // Kolom NIK diformat teks sampai baris 2001, jadi getHighestDataRow() tak bisa dipakai; yang dijaga: baris 2 kosong.
        $baris2 = $ws->rangeToArray('A2:'.$ws->getHighestColumn().'2')[0];
        $this->assertSame([], array_filter($baris2, fn ($v) => $v !== null && $v !== ''), 'template tanpa baris contoh');

        $kolomAir = array_search('air_bersih', $header, true) + 1;
        $validasi = $ws->getCell([$kolomAir, 2])->getDataValidation();
        $this->assertSame('list', $validasi->getType());
        $this->assertSame('"Ya,Tidak"', $validasi->getFormula1());
    }

    public function test_template_diisi_lalu_diimpor_ulang_petunjuk_tidak_terbaca(): void
    {
        $this->actingAs($this->superadmin());
        $anak = $this->budi();

        $path = $this->templateTerisi([
            'nik_anak' => '6474010101230001', 'nama_anak' => 'Budi Santoso', 'jk' => 'L',
            'tgl_lahir' => ['tgl' => '2023-01-01'], 'nama_ortu' => 'Siti Aminah',
            'tgl_kunjungan' => ['tgl' => '2025-03-10'], 'bb' => '10,5', 'tb_cm' => '85', 'posisi' => 'Berdiri',
            'mbg' => 'Ya', 'air_bersih' => 'Ya',
        ]);

        $id = $this->unggah($path)->assertOk()->assertJsonPath('ok', true)->json('log_id');

        $log = ImportLog::findOrFail($id);
        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
        $this->assertSame('kesmas', $log->type);
        $this->assertSame(1, (int) $log->success_count);
        $this->assertSame(0, (int) $log->failure_count);
        $this->assertEmpty($log->failures, 'sheet Petunjuk tidak boleh terbaca sebagai data');
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
        $this->assertSame(1, (int) DataAnak::sole()->mbg);
    }

    public function test_reimport_memakai_format_tanggal_yang_sama(): void
    {
        $this->actingAs($this->superadmin());
        $this->budi();
        $path = $this->templateTerisi(['nama_anak' => 'Budi Santoso', 'jk' => 'L', 'tgl_lahir' => '01/01/2023',
            'nama_ortu' => 'Siti Aminah', 'air_bersih' => 'Ya']);
        $id = $this->unggah($path, 'dmy')->assertOk()->json('log_id');

        $baru = $this->postJson(route('admin.importCsv.reimport', $id))->assertOk()->json('log_id');

        $log = ImportLog::findOrFail($baru);
        $this->assertSame('dmy', $log->format_tanggal);
        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
    }

    public function test_csv_ditolak(): void
    {
        $this->actingAs($this->superadmin());

        $this->postJson(route('admin.importCsv.kesmas'), [
            'file_kesmas' => UploadedFile::fake()->createWithContent('kesmas.csv', "nama_anak,jk,tgl_lahir,nama_ortu\n"),
        ])->assertStatus(422)->assertJsonValidationErrors('file_kesmas');
    }

    public function test_bukan_superadmin_ditolak(): void
    {
        $this->actingAs(User::factory()->create(['type' => 1]));

        $this->get(route('admin.importCsv.template', 'kesmas'))->assertForbidden();
        $this->postJson(route('admin.importCsv.kesmas'), [
            'file_kesmas' => UploadedFile::fake()->create('kesmas.xlsx', 10, self::MIME_XLSX),
        ])->assertForbidden();
    }

    public function test_halaman_import_menampilkan_tab_kesmas(): void
    {
        $this->actingAs($this->superadmin());

        $this->get(route('admin.importCsv.index'))->assertOk()
            ->assertSee('id="panel-kesmas"', false)
            ->assertSee(route('admin.importCsv.kesmas'), false)
            ->assertSee(route('admin.importCsv.template', 'kesmas'), false);
    }
}
