<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\DataAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportOperasiTimbangCommandTest extends TestCase
{
    use RefreshDatabase;

    private function buatFile(): string
    {
        $header = [
            'No', 'NIK', 'Nama', 'JK', 'Tgl Lahir', 'Nama Ortu',
            'Tanggal Pengukuran', 'Berat', 'Tinggi', 'Cara Ukur', 'LiLA',
            'ZS BB/U', 'ZS TB/U', 'ZS BB/TB', 'Naik Berat Badan',
            'Jml Vit A', 'Kelas Ibu Balita', 'MBG',
        ];
        $rows = [
            // COCOK
            ['1', '02022**********', 'MUHAMMAD ABIZAR', 'L', '2024-02-02', 'AGIL ANASTASYA F',
             '2026-06-09', '10.6', '81.5', 'Terlentang', '14', '-1.73', '-2.95', '-0.15', 'N', '-', 'Tidak', 'Tidak'],
            // TAK_COCOK (tak ada anaknya)
            ['2', '07012**********', 'ANAK TIDAK ADA', 'L', '2026-01-07', 'SINTA',
             '2026-06-09', '6.5', '63', 'Terlentang', '0', '-1.31', '-1.4', '-0.51', 'T', '-', 'Ya', 'Tidak'],
        ];

        $ss = new Spreadsheet();
        $s = $ss->getActiveSheet();
        $s->fromArray($header, null, 'A1');
        $s->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'ot_') . '.xlsx';
        (new Xlsx($ss))->save($path);
        return $path;
    }

    public function test_dry_run_default_tidak_menulis(): void
    {
        Storage::fake('local');
        Anak::create(['nik' => '1111111111111111', 'nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02', 'nama_ibu' => 'AGIL ANASTASYA F', 'nama_ayah' => 'X', 'no' => 'R1', 'status' => 1]);

        $this->artisan('import:operasi-timbang', ['file' => $this->buatFile()])
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        $this->assertEquals(0, DataAnak::count());
    }

    public function test_commit_menulis_yang_cocok_dan_ekspor_takcocok(): void
    {
        Storage::fake('local');
        $anak = Anak::create(['nik' => '1111111111111111', 'nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02', 'nama_ibu' => 'AGIL ANASTASYA F', 'nama_ayah' => 'X', 'no' => 'R1', 'status' => 1]);

        $this->artisan('import:operasi-timbang', ['file' => $this->buatFile(), '--commit' => true, '--user' => 1])
            ->assertExitCode(0);

        $this->assertEquals(1, DataAnak::where('id_anak', $anak->id)->count());
        // Baris tak cocok terekspor
        $files = Storage::disk('local')->allFiles('timbang');
        $this->assertNotEmpty(array_filter($files, fn ($f) => str_contains($f, 'takcocok')));
    }

    public function test_keputusan_memetakan_baris_ambigu_ke_record_pilihan(): void
    {
        Storage::fake('local');
        // Dua anak identik → baris "MUHAMMAD ABIZAR" (rowNum 2) jadi AMBIGU
        $a1 = Anak::create(['nik' => '1111111111111111', 'nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02', 'nama_ibu' => 'AGIL ANASTASYA F', 'nama_ayah' => 'X', 'no' => 'R1', 'status' => 1]);
        $a2 = Anak::create(['nik' => '2222222222222222', 'nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02', 'nama_ibu' => 'AGIL ANASTASYA F', 'nama_ayah' => 'Y', 'no' => 'R2', 'status' => 1]);

        $kep = tempnam(sys_get_temp_dir(), 'kep_') . '.csv';
        file_put_contents($kep, "baris,keputusan_id\n2,{$a2->id}\n");

        $this->artisan('import:operasi-timbang', ['file' => $this->buatFile(), '--commit' => true, '--user' => 1, '--keputusan' => $kep])
            ->expectsOutputToContain('Keputusan dimuat')
            ->assertExitCode(0);

        $this->assertEquals(1, DataAnak::where('id_anak', $a2->id)->count());
        $this->assertEquals(0, DataAnak::where('id_anak', $a1->id)->count());
    }

    public function test_buat_tak_cocok_membuat_anak_dummy_dan_ekspor_csv_dibuat(): void
    {
        Storage::fake('local');
        Anak::create(['nik' => '1111111111111111', 'nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02', 'nama_ibu' => 'AGIL ANASTASYA F', 'nama_ayah' => 'X', 'no' => 'R1', 'status' => 1]);

        $this->artisan('import:operasi-timbang', ['file' => $this->buatFile(), '--commit' => true, '--user' => 1, '--buat-tak-cocok' => true])
            ->expectsOutputToContain('DIBUAT')
            ->assertExitCode(0);

        // Baris 'ANAK TIDAK ADA' kini jadi anak baru ber-NIK dummy + measurement
        $baru = Anak::where('nama', 'ANAK TIDAK ADA')->first();
        $this->assertNotNull($baru);
        $this->assertEquals('9', substr($baru->nik, 12, 1));
        $this->assertEquals(1, DataAnak::where('id_anak', $baru->id)->count());

        // CSV audit ditulis, CSV takcocok tidak (tidak ada yang tersisa tak-cocok)
        $files = Storage::disk('local')->allFiles('timbang');
        $this->assertNotEmpty(array_filter($files, fn ($f) => str_contains($f, 'dibuat')));
        $this->assertEmpty(array_filter($files, fn ($f) => str_contains($f, 'takcocok')));
    }
}
