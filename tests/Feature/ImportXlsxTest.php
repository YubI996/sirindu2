<?php

namespace Tests\Feature;

use App\Imports\ImunisasiImport;
use App\Imports\PjImport;
use App\Jobs\ImportAnakJob;
use App\Jobs\ImportImunisasiJob;
use App\Jobs\ImportPjJob;
use App\Models\Anak;
use App\Models\ImportLog;
use App\Models\JenisVaksin;
use App\Models\User;
use Database\Seeders\JenisVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Semua import menerima .xlsx/.xls (Sep 2026). Latar: berkas imunisasi puskesmas
 * berisi tanggal bergaris miring bulan/hari/tahun yang ambigu di CSV — di Excel
 * sel tanggal adalah tanggal sungguhan (serial), jadi ambiguitas itu tidak ada.
 * Pembacanya (Maatwebsite) sudah mengenali xlsx; yang memblokir adalah validasi
 * controller, atribut accept di form, dan PjImport yang membaca dengan fgetcsv.
 */
class ImportXlsxTest extends TestCase
{
    use RefreshDatabase;

    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const MIME_XLS  = 'application/vnd.ms-excel';

    /** @var string[] berkas sementara yang dibuat tes */
    private array $sementara = [];

    protected function tearDown(): void
    {
        foreach ($this->sementara as $p) {
            @unlink($p);
        }
        parent::tearDown();
    }

    /**
     * Tulis workbook ke berkas sementara. $baris = array baris; sel array ['tgl' => 'Y-m-d']
     * ditulis sebagai sel tanggal sungguhan berformat m/d/yyyy, sel ['teks' => '...'] sebagai teks eksplisit.
     */
    private function buatWorkbook(array $baris, string $ekstensi = 'xlsx'): string
    {
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        foreach ($baris as $r => $kolom) {
            foreach (array_values($kolom) as $c => $nilai) {
                $sel = $ws->getCell([$c + 1, $r + 1]);
                if (is_array($nilai) && isset($nilai['tgl'])) {
                    $sel->setValue(Date::PHPToExcel(new \DateTime($nilai['tgl'])));
                    $sel->getStyle()->getNumberFormat()->setFormatCode('m/d/yyyy');
                } elseif (is_array($nilai) && isset($nilai['teks'])) {
                    $sel->setValueExplicit($nilai['teks'], DataType::TYPE_STRING);
                } else {
                    $sel->setValue($nilai);
                }
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'uji') . '.' . $ekstensi;
        ($ekstensi === 'xls' ? new Xls($ss) : new Xlsx($ss))->save($path);
        $this->sementara[] = $path;
        return $path;
    }

    private function unggahan(string $path, string $nama, string $mime): UploadedFile
    {
        return new UploadedFile($path, $nama, $mime, null, true);
    }

    private function workbookImunisasi(string $ekstensi = 'xlsx'): string
    {
        return $this->buatWorkbook([
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'BCG'],
            // NIK sebagai sel angka (kebiasaan petugas), tgl lahir & HB0 sel tanggal tampil 7/30/2025, BCG diketik teks.
            [3201011501200001, 'Budi Santoso', ['tgl' => '2020-01-15'], ['tgl' => '2020-01-15'], ['teks' => '2020-02-15']],
        ], $ekstensi);
    }

    public function test_controller_menerima_xlsx_dan_xls_untuk_import_imunisasi(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->actingAs(User::factory()->create(['type' => 0]));

        foreach (['xlsx' => self::MIME_XLSX, 'xls' => self::MIME_XLS] as $ekstensi => $mime) {
            $this->postJson(route('admin.importCsv.imunisasi'), [
                'file_imunisasi' => $this->unggahan($this->workbookImunisasi($ekstensi), "imunisasi.{$ekstensi}", $mime),
            ])->assertOk()->assertJsonPath('ok', true);
        }

        $this->assertSame(2, ImportLog::where('type', 'imunisasi')->count());
        Queue::assertPushed(ImportImunisasiJob::class, 2);
    }

    public function test_controller_menerima_xlsx_untuk_import_anak_dan_pj(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->actingAs(User::factory()->create(['type' => 0]));

        $anak = $this->buatWorkbook([['nik', 'nama', 'tgl_lahir', 'jk'], [['teks' => '3201011501200001'], 'Budi', ['tgl' => '2020-01-15'], 'L']]);
        $this->postJson(route('admin.importCsv.anak'), ['file_anak' => $this->unggahan($anak, 'anak.xlsx', self::MIME_XLSX)])
            ->assertOk()->assertJsonPath('ok', true);

        $pj = $this->buatWorkbook([['nama_pj'], ['Kader Sari']]);
        $this->postJson(route('admin.importCsv.pj'), ['file_pj' => $this->unggahan($pj, 'pj.xlsx', self::MIME_XLSX)])
            ->assertOk()->assertJsonPath('ok', true);

        Queue::assertPushed(ImportAnakJob::class, 1);
        Queue::assertPushed(ImportPjJob::class, 1);
    }

    public function test_berkas_selain_csv_dan_excel_tetap_ditolak(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->actingAs(User::factory()->create(['type' => 0]));

        $this->postJson(route('admin.importCsv.imunisasi'), [
            'file_imunisasi' => UploadedFile::fake()->create('imunisasi.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['file_imunisasi']);

        Queue::assertNothingPushed();
    }

    public function test_form_import_menerima_excel(): void
    {
        $this->actingAs(User::factory()->create(['type' => 0]));

        $html = $this->get(route('admin.importCsv.index'))->assertOk()->getContent();

        foreach (['file-anak', 'file-pengukuran', 'file-imunisasi', 'file-pj', 'file-ukur'] as $id) {
            $this->assertMatchesRegularExpression(
                '~id="' . $id . '"[^>]*accept="[^"]*\.xlsx[^"]*"~',
                $html,
                "Input #{$id} harus menerima .xlsx"
            );
        }
    }

    public function test_import_imunisasi_membaca_xlsx_nik_angka_dan_sel_tanggal_sungguhan(): void
    {
        $this->seed(JenisVaksinSeeder::class);
        $admin = User::factory()->create(['type' => 1]);
        $anak = Anak::create([
            'nama' => 'Budi Santoso', 'nik' => '3201011501200001', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2020-01-15', 'status' => 1,
        ]);

        $import = new ImunisasiImport($admin->id);
        Excel::import($import, $this->workbookImunisasi());

        $tanggal = DB::table('imunisasi')->where('id_anak', $anak->id)
            ->join('jenis_vaksin', 'jenis_vaksin.id', '=', 'imunisasi.id_jenis_vaksin')
            ->pluck('imunisasi.tanggal_pemberian', 'jenis_vaksin.kode')->all();

        // Sel tanggal Excel yang TAMPIL 1/15/2020 tetap terbaca 2020-01-15 — tidak ada ambiguitas bulan/hari.
        $this->assertEqualsCanonicalizing(['HB0' => '2020-01-15', 'BCG' => '2020-02-15'], $tanggal);
        $this->assertSame(0, $import->getResults()['error_count'], implode('; ', $import->getResults()['failures']));
    }

    public function test_pj_import_membaca_xlsx_dan_tetap_membaca_csv(): void
    {
        $xlsx = $this->buatWorkbook([
            ['Keterangan', 'Nama_PJ'],
            ['posyandu A', 'Kader Sari'],
            [null, null],
            [null, 'Bidan Rina'],
            ['tanpa nama', ''],
        ]);
        $r = (new PjImport())->baca($xlsx);
        $this->assertSame(
            [['Kader Sari', 2], ['Bidan Rina', 4]],
            array_map(fn ($b) => [$b['nama_pj'], $b['baris']], $r['baris'])
        );
        $this->assertSame(['Baris 5: Kolom nama_pj wajib diisi.'], $r['gagal']);

        $csv = tempnam(sys_get_temp_dir(), 'pj') . '.csv';
        file_put_contents($csv, "\xEF\xBB\xBFNama_PJ\nKader Sari\n");
        $this->sementara[] = $csv;
        $r = (new PjImport())->baca($csv);
        $this->assertSame([['Kader Sari', 2]], array_map(fn ($b) => [$b['nama_pj'], $b['baris']], $r['baris']));
    }
}
