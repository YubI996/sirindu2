<?php

namespace Tests\Feature\Imports;

use App\Models\Anak;
use App\Models\ImportLog;
use App\Models\User;
use Database\Seeders\JenisVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pilihan format penanggalan berkas import.
 *
 * 05/02/2020 tak bisa dibaca tanpa tahu urutan berkasnya. Sebelumnya Carbon::parse()
 * membacanya 2 Mei (gaya AS) tanpa peringatan, sementara ImunisasiImport menolak
 * seluruh tanggal bergaris miring. Sekarang: petugas memilih, atau sistem
 * menyimpulkan dari isi berkas — dan menolak jalan kalau memang tak bisa dipastikan.
 */
class FormatTanggalImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['queue.default' => 'sync']);
        $this->actingAs(User::factory()->create(['type' => 0]));
    }

    private function unggahAnak(string $csv, ?string $format = null): ImportLog
    {
        $payload = ['file_anak' => UploadedFile::fake()->createWithContent('anak.csv', $csv)];
        if ($format !== null) {
            $payload['format_tanggal'] = $format;
        }

        $id = $this->postJson(route('admin.importCsv.anak'), $payload)
            ->assertOk()->assertJsonPath('ok', true)->json('log_id');

        return ImportLog::findOrFail($id);
    }

    public function test_format_disimpulkan_dari_isi_berkas_saat_otomatis(): void
    {
        $log = $this->unggahAnak(
            "nik,nama,jk,tgl_lahir\n"
            ."6474010101249001,Anak Satu,L,05/02/2020\n"
            ."6474010101249002,Anak Dua,L,25/02/2020\n"
        );

        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
        $this->assertSame('auto', $log->format_tanggal);
        // 25/02 hanya masuk akal sebagai hari/bulan — kesimpulan itu berlaku untuk seluruh berkas.
        $this->assertSame('2020-02-05', Anak::where('nik', '6474010101249001')->sole()->tgl_lahir);
        $this->assertSame('2020-02-25', Anak::where('nik', '6474010101249002')->sole()->tgl_lahir);
    }

    public function test_pilihan_bulan_hari_tahun_dipakai_apa_adanya(): void
    {
        $log = $this->unggahAnak(
            "nik,nama,jk,tgl_lahir\n"
            ."6474010101249001,Anak Satu,L,05/02/2020\n"
            ."6474010101249002,Anak Dua,L,25/02/2020\n",
            'mdy'
        );

        $this->assertSame('mdy', $log->format_tanggal);
        $this->assertSame('2020-05-02', Anak::where('nik', '6474010101249001')->sole()->tgl_lahir);
        // 25 sebagai bulan tak ada di kalender — baris gagal, BUKAN ditukar diam-diam jadi 25 Februari.
        $this->assertSame(1, (int) $log->failure_count);
        $this->assertDatabaseMissing('anak', ['nik' => '6474010101249002']);
    }

    public function test_import_dihentikan_kalau_format_tak_bisa_dipastikan(): void
    {
        $log = $this->unggahAnak(
            "nik,nama,jk,tgl_lahir\n"
            ."6474010101249003,Anak Tiga,L,05/02/2020\n"
        );

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Format tanggal', $log->failures[0]);
        $this->assertStringContainsString('05/02/2020', $log->failures[0]);
        $this->assertDatabaseMissing('anak', ['nik' => '6474010101249003']);
    }

    public function test_berkas_tanpa_tanggal_bergaris_miring_tak_terpengaruh(): void
    {
        $log = $this->unggahAnak(
            "nik,nama,jk,tgl_lahir\n"
            ."6474010101249004,Anak Empat,L,2020-02-05\n"
        );

        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
        $this->assertSame('2020-02-05', Anak::where('nik', '6474010101249004')->sole()->tgl_lahir);
    }

    public function test_import_imunisasi_menerima_tanggal_bergaris_miring(): void
    {
        $this->seed(JenisVaksinSeeder::class);
        $anak = Anak::create([
            'nama' => 'Anak Lima', 'nik' => '6474010101249005', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2020-01-15', 'status' => 1,
        ]);

        $id = $this->postJson(route('admin.importCsv.imunisasi'), [
            'file_imunisasi' => UploadedFile::fake()->createWithContent(
                'imunisasi.csv',
                "nik_anak,nama_anak,tgl_lahir_anak,HB0\n6474010101249005,Anak Lima,15/01/2020,05/02/2020\n"
            ),
        ])->assertOk()->json('log_id');

        $log = ImportLog::findOrFail($id);
        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
        $this->assertSame('2020-02-05', DB::table('imunisasi')->where('id_anak', $anak->id)->value('tanggal_pemberian'));
    }

    public function test_format_ikut_terbawa_saat_reimport(): void
    {
        $log = $this->unggahAnak("nik,nama,jk,tgl_lahir\n6474010101249006,Anak Enam,L,05/02/2020\n", 'mdy');

        $baru = ImportLog::findOrFail(
            $this->postJson(route('admin.importCsv.reimport', $log))->assertOk()->json('log_id')
        );

        $this->assertSame('mdy', $baru->format_tanggal);
    }

    public function test_format_tanggal_yang_tak_dikenal_ditolak(): void
    {
        $this->postJson(route('admin.importCsv.anak'), [
            'file_anak'      => UploadedFile::fake()->createWithContent('anak.csv', "nik,nama,jk,tgl_lahir\n"),
            'format_tanggal' => 'ymd-terserah',
        ])->assertUnprocessable()->assertJsonValidationErrors(['format_tanggal']);
    }

    public function test_form_import_menawarkan_pilihan_format_tanggal(): void
    {
        $html = $this->get(route('admin.importCsv.index'))->assertOk()->getContent();
        $this->assertSame(
            4,
            substr_count($html, 'name="format_tanggal"'),
            'Kartu Anak, Pengukuran, Imunisasi, dan Operasi Timbang harus punya pilihan format tanggal'
        );

        $kohort = $this->get(route('admin.anak'))->assertOk()->getContent();
        $this->assertStringContainsString('name="format_tanggal"', $kohort);

        $pd3i = $this->get(route('admin.epidemiologi.index'))->assertOk()->getContent();
        $this->assertStringContainsString('name="format_tanggal"', $pd3i);
    }
}
