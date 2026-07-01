<?php

namespace Tests\Feature;

use App\Imports\CapilImport;
use App\Models\Anak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportCapilTest extends TestCase
{
    use RefreshDatabase;

    /** Header Capil standar untuk fixture in-memory. */
    private function header(): array
    {
        return [
            'NIK', 'NAMA LENGKAP', 'JENIS KLMIN', 'TGL LHR',
            'USIA BLN PER 31-05-2026', 'NO KK', 'NAMA LENGKAP IBU',
            'NAMA LENGKAP AYAH', 'ALAMAT', 'NO RT', 'NO KEL',
            'NAMA KELURAHAN', 'NO KEC', 'NAMA KECAMATAN',
        ];
    }

    public function test_baris_tanpa_padanan_membuat_anak_baru(): void
    {
        $row = [
            '3274010101200001', 'BUDI SANTOSO', 'LAKI-LAKI', '2020-01-15',
            '77', '3274999999999999', 'SITI AMINAH', 'JOKO WIDODO',
            'JL MAWAR NO 5', '003', '01', 'BERBAS PANTAI', '02', 'BONTANG SELATAN',
        ];

        $import = new CapilImport(1, '2026-06-25');
        $import->collection(collect([$this->header(), $row]));

        $anak = Anak::where('nik', '3274010101200001')->first();
        $this->assertNotNull($anak);
        $this->assertEquals('BUDI SANTOSO', $anak->nama);
        $this->assertEquals(1, $anak->jk);
        $this->assertEquals('2020-01-15', (string) $anak->tgl_lahir);
        $this->assertEquals('SITI AMINAH', $anak->nama_ibu);
        $this->assertEquals(
            'JL MAWAR NO 5, RT 003, Kel. BERBAS PANTAI, Kec. BONTANG SELATAN',
            $anak->alamat_ktp
        );
        $this->assertNull($anak->id_kec);
        $this->assertNull($anak->alamat);
        $this->assertStringContainsString('Impor Capil 2026-06-25', $anak->catatan);

        $results = $import->getResults();
        $this->assertEquals(1, $results['created']);
        $this->assertEquals(0, $results['updated']);
    }

    public function test_match_memperbarui_kependudukan_tanpa_menyentuh_kesehatan(): void
    {
        $existing = Anak::create([
            'nik'         => '9999999999999999', // NIK dummy sigizi
            'nama'        => 'BUDI SANTOSO',
            'jk'          => 1,
            'tgl_lahir'   => '2020-01-15',
            'no'          => 'REG-1',
            'status'      => 1,
            'alamat'      => 'Jl Domisili Sigizi',
            'id_kec'      => 5,
            'id_posyandu' => 3,
            'catatan'     => 'Catatan manual petugas',
            'bbl'         => 3.2,
        ]);

        $row = [
            '3274010101200001', 'BUDI SANTOSO', 'LAKI-LAKI', '2020-01-15',
            '77', '3274KK', 'SITI AMINAH', 'JOKO WIDODO',
            'JL MAWAR', '003', '01', 'BERBAS PANTAI', '02', 'BONTANG SELATAN',
        ];

        $import = new CapilImport(1, '2026-06-25');
        $import->collection(collect([$this->header(), $row]));

        $existing->refresh();
        // Kependudukan ikut Capil:
        $this->assertEquals('3274010101200001', $existing->nik);
        $this->assertEquals('SITI AMINAH', $existing->nama_ibu);
        $this->assertStringContainsString('JL MAWAR', $existing->alamat_ktp);
        // Kesehatan & domisili & catatan TIDAK berubah:
        $this->assertEquals('Jl Domisili Sigizi', $existing->alamat);
        $this->assertEquals(5, $existing->id_kec);
        $this->assertEquals(3, $existing->id_posyandu);
        $this->assertEquals('Catatan manual petugas', $existing->catatan);
        $this->assertEquals(3.2, (float) $existing->bbl);
        // Tidak ada baris baru:
        $this->assertEquals(1, Anak::count());

        $this->assertEquals(1, $import->getResults()['updated']);
    }

    public function test_sel_capil_kosong_tidak_menimpa_sigizi(): void
    {
        $existing = Anak::create([
            'nik' => '9999999999999999', 'nama' => 'ANI WIJAYA', 'jk' => 2,
            'tgl_lahir' => '2021-03-10', 'no' => 'REG-2', 'status' => 1,
            'nama_ayah' => 'AYAH SIGIZI',
        ]);

        // NAMA LENGKAP AYAH dikosongkan
        $row = [
            '3274010101210002', 'ANI WIJAYA', 'PEREMPUAN', '2021-03-10',
            '62', '3274KK', 'IBU CAPIL', '', 'JL ANGGREK', '004', '01',
            'GUNUNG ELAI', '01', 'BONTANG UTARA',
        ];

        (new CapilImport(1, '2026-06-25'))->collection(collect([$this->header(), $row]));

        $existing->refresh();
        $this->assertEquals('AYAH SIGIZI', $existing->nama_ayah);  // tidak ditimpa kosong
        $this->assertEquals('IBU CAPIL', $existing->nama_ibu);     // ditimpa karena terisi
    }

    public function test_dua_anak_nama_tgl_sama_dilewati_sebagai_ambigu(): void
    {
        Anak::create(['nik' => '1111111111111111', 'nama' => 'RANI PUTRI', 'jk' => 2, 'tgl_lahir' => '2022-02-02', 'no' => 'R1', 'status' => 1]);
        Anak::create(['nik' => '2222222222222222', 'nama' => 'RANI PUTRI', 'jk' => 2, 'tgl_lahir' => '2022-02-02', 'no' => 'R2', 'status' => 1]);

        $row = [
            '3274010101220003', 'RANI PUTRI', 'PEREMPUAN', '2022-02-02',
            '52', '3274KK', 'IBU', 'AYAH', 'JL X', '001', '01', 'KEL', '01', 'KEC',
        ];

        $import = new CapilImport(1, '2026-06-25');
        $import->collection(collect([$this->header(), $row]));

        $this->assertEquals(2, Anak::count());                          // tidak buat baris baru
        $this->assertEquals(0, $import->getResults()['created']);
        $this->assertEquals(1, $import->getResults()['error_count']);

        $hasWarning = collect($import->getResults()['failures'])
            ->contains(fn ($f) => str_contains($f, '[PERINGATAN]') && str_contains($f, 'RANI PUTRI'));
        $this->assertTrue($hasWarning);
    }

    public function test_tgl_lahir_kosong_diestimasi_dari_usia(): void
    {
        // USIA BLN PER 31-05-2026 = 12 → lahir Mei 2025 (awal bulan)
        $row = [
            '3274010101250004', 'DEWI LESTARI', 'PEREMPUAN', '',
            '12', '3274KK', 'IBU', 'AYAH', 'JL Y', '002', '01', 'KEL', '01', 'KEC',
        ];

        (new CapilImport(1, '2026-06-25'))->collection(collect([$this->header(), $row]));

        $anak = Anak::where('nik', '3274010101250004')->first();
        $this->assertNotNull($anak);
        $this->assertEquals('2025-05-01', (string) $anak->tgl_lahir);
    }

    public function test_nik_capil_otoritatif_memperbaiki_nama_via_nik_exact(): void
    {
        // Anak existing sudah punya NIK Capil-benar tapi nama salah ketik
        $existing = Anak::create([
            'nik' => '3274010101200001', 'nama' => 'BUDl SANTOSA', 'jk' => 1,
            'tgl_lahir' => '2020-01-15', 'no' => 'REG-9', 'status' => 1,
        ]);

        $row = [
            '3274010101200001', 'BUDI SANTOSO', 'LAKI-LAKI', '2020-01-15',
            '77', '3274KK', 'SITI', 'JOKO', 'JL Z', '001', '01', 'KEL', '01', 'KEC',
        ];

        $import = new CapilImport(1, '2026-06-25');
        $import->collection(collect([$this->header(), $row]));

        $existing->refresh();
        $this->assertEquals('BUDI SANTOSO', $existing->nama);   // diperbaiki dari Capil
        $this->assertEquals(1, Anak::count());                  // tidak duplikat
        $this->assertEquals(1, $import->getResults()['updated']);
    }

    public function test_hanya_sheet_pertama_yang_diproses_pada_file_multi_sheet(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'capil_multi_') . '.xlsx';

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // Sheet 0 — data yang dikehendaki
        $s0 = $ss->getActiveSheet();
        $s0->setTitle('DATA');
        $s0->fromArray($this->header(), null, 'A1');
        $s0->fromArray([[
            '3274010101200010', 'ANAK SHEET SATU', 'LAKI-LAKI', '2020-01-10',
            '77', '3274KK', 'IBU', 'AYAH', 'JL A', '001', '01', 'KEL', '01', 'KEC',
        ]], null, 'A2');

        // Sheet 1 — tersembunyi, TIDAK boleh ikut terproses
        $s1 = $ss->createSheet();
        $s1->setTitle('TERSEMBUNYI');
        $s1->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $s1->fromArray($this->header(), null, 'A1');
        $s1->fromArray([[
            '3274010101200011', 'ANAK SHEET DUA', 'PEREMPUAN', '2021-02-11',
            '64', '3274KK', 'IBU', 'AYAH', 'JL B', '002', '01', 'KEL', '01', 'KEC',
        ]], null, 'A2');

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);

        $import = new CapilImport(1, '2026-06-25');
        Excel::import($import, $path);

        // Hanya baris sheet pertama yang masuk
        $this->assertNotNull(Anak::where('nik', '3274010101200010')->first());
        $this->assertNull(Anak::where('nik', '3274010101200011')->first());
        $this->assertEquals(1, Anak::count());
        $this->assertEquals(1, $import->getResults()['created']);

        @unlink($path);
    }

    public function test_import_dari_file_xlsx_nyata_dengan_alamat_bersimbol(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'capil_') . '.xlsx';

        $ss    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray($this->header(), null, 'A1');
        // Alamat mengandung koma & petik — perusak CSV, aman di xlsx
        $sheet->fromArray([[
            '3274010101200005', 'EKO PRASETYO', 'LAKI-LAKI', '2019-07-20',
            '82', '3274KK', 'IBU EKO', 'AYAH EKO',
            'JL. "MELATI", NO. 7, RT/RW', '005', '01', 'API-API', '02', 'BONTANG BARAT',
        ]], null, 'A2');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);

        Excel::import(new CapilImport(1, '2026-06-25'), $path);

        $anak = Anak::where('nik', '3274010101200005')->first();
        $this->assertNotNull($anak);
        $this->assertStringContainsString('JL. "MELATI", NO. 7, RT/RW', $anak->alamat_ktp);
        $this->assertStringContainsString('Kel. API-API', $anak->alamat_ktp);

        @unlink($path);
    }

    public function test_sheet_pertama_yang_terlihat_dipilih_bukan_yang_hidden(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'capil_vis_') . '.xlsx';

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // Sheet 0 — TERSEMBUNYI (meniru "DATA AWAL"), tidak boleh terpilih
        $s0 = $ss->getActiveSheet();
        $s0->setTitle('RAW');
        $s0->fromArray($this->header(), null, 'A1');
        $s0->fromArray([[
            '3274010101200010', 'ANAK HIDDEN', 'LAKI-LAKI', '2020-01-10',
            '77', '3274KK', 'IBU', 'AYAH', 'JL A', '001', '01', 'KEL', '01', 'KEC',
        ]], null, 'A2');

        // Sheet 1 — TERLIHAT (meniru "DATA OLAH"), yang harus dipilih
        $s1 = $ss->createSheet();
        $s1->setTitle('OLAH');
        $s1->fromArray($this->header(), null, 'A1');
        $s1->fromArray([[
            '3274010101200011', 'ANAK VISIBLE', 'PEREMPUAN', '2021-02-11',
            '64', '3274KK', 'IBU', 'AYAH', 'JL B', '002', '01', 'KEL', '01', 'KEC',
        ]], null, 'A2');

        $s0->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $ss->setActiveSheetIndex(1); // active sheet tidak boleh hidden
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);

        $sheets = CapilImport::inspectSheets($path);
        $target = CapilImport::firstVisibleSheet($sheets);
        $this->assertEquals('OLAH', $target);

        Excel::import(new CapilImport(1, '2026-06-25', $target), $path);

        $this->assertNotNull(Anak::where('nik', '3274010101200011')->first()); // sheet terlihat
        $this->assertNull(Anak::where('nik', '3274010101200010')->first());    // sheet hidden dilewati

        @unlink($path);
    }

    public function test_peringatan_dibuat_saat_file_lebih_dari_satu_sheet(): void
    {
        $sheets = [
            ['index' => 0, 'name' => 'DATA AWAL', 'visibility' => 'hidden'],
            ['index' => 1, 'name' => 'DATA OLAH', 'visibility' => 'visible'],
        ];

        $warn = CapilImport::sheetWarning($sheets, 'DATA OLAH');
        $this->assertNotNull($warn);
        $this->assertStringContainsString('[PERINGATAN]', $warn);
        $this->assertStringContainsString('DATA AWAL', $warn);
        $this->assertStringContainsString('DATA OLAH', $warn);

        // Satu sheet → tidak ada peringatan
        $this->assertNull(CapilImport::sheetWarning(
            [['index' => 0, 'name' => 'Sheet1', 'visibility' => 'visible']],
            'Sheet1'
        ));
    }
}
