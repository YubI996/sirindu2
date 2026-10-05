<?php

namespace Tests\Feature\Kesmas;

use App\Exports\KesmasExport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use App\Exports\KesmasAnakSheet;
use App\Exports\KesmasKunjunganSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Export Kesmas (spec §5): dua sheet, label Ya/Tidak/kosong, filter wilayah & tanggal,
 * faskes surveilans ditolak, NIK tetap teks. Ditulis streaming (FastExcel) — lihat
 * ExportKesmasMemoriTest untuk alasannya.
 */
class ExportKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelurahan $kelA;
    private Kelurahan $kelB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kelA = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $kec->id]);
        $this->kelB = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $kec->id]);
    }

    private function anak(string $nik, Kelurahan $kel, array $extra = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak ' . $nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10',
            'status' => 1, 'sumber' => 'manual', 'no' => '1', 'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id,
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tgl, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'id_user' => $this->admin->id,
        ], $extra));
    }

    /** @return array{0: Worksheet, 1: Worksheet} sheet "Per Anak" dan "Per Kunjungan" */
    private function sheets(array $filter): array
    {
        $path = tempnam(sys_get_temp_dir(), 'kesmas');
        try {
            (new KesmasExport($filter))->simpan($path);

            return $this->bukaBerkas($path);
        } finally {
            unlink($path);
        }
    }

    /** @return array{0: Worksheet, 1: Worksheet} */
    private function bukaBerkas(string $path): array
    {
        $book = IOFactory::load($path);
        $this->assertSame(['Per Anak', 'Per Kunjungan'], $book->getSheetNames());

        return [$book->getSheetByName('Per Anak'), $book->getSheetByName('Per Kunjungan')];
    }

    public function test_halaman_export_tampil_dengan_menu_sidebar(): void
    {
        $this->actingAs($this->admin)->get(route('admin.export.kesmas.index'))
            ->assertOk()
            ->assertSee('Export Kesmas')
            ->assertSee(route('admin.export.kesmas.download'), false);
    }

    public function test_faskes_surveilans_ditolak(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'role' => 'surveilans_puskesmas']);

        $this->actingAs($faskes)->get(route('admin.export.kesmas.index'))->assertForbidden();
        $this->actingAs($faskes)->get(route('admin.export.kesmas.download'))->assertForbidden();
    }

    public function test_download_mengirim_xlsx_dua_sheet_dengan_nama_berkas(): void
    {
        $this->anak('6474010101250010', $this->kelA);

        $r = $this->actingAs($this->admin)->get(route('admin.export.kesmas.download', ['id_kel' => $this->kelA->id]));
        $isi = $r->streamedContent(); // callback streaming baru jalan saat isinya dibaca

        $r->assertOk();
        $this->assertStringContainsString('kesmas-berbas-tengah-' . now()->format('Ymd') . '.xlsx', $r->headers->get('content-disposition') ?? '');
        $this->assertStringStartsWith('PK', $isi, 'xlsx adalah arsip ZIP');

        $path = tempnam(sys_get_temp_dir(), 'kesmas');
        try {
            file_put_contents($path, $isi);
            [$anak] = $this->bukaBerkas($path);
            $this->assertSame('6474010101250010', $anak->getCell('A2')->getValue());
        } finally {
            unlink($path);
        }
    }

    public function test_download_tanpa_filter_bernama_semua(): void
    {
        $r = $this->actingAs($this->admin)->get(route('admin.export.kesmas.download'));
        $r->streamedContent();

        $r->assertOk();
        $this->assertStringContainsString('kesmas-semua-' . now()->format('Ymd') . '.xlsx', $r->headers->get('content-disposition') ?? '');
    }

    public function test_hasil_kosong_tetap_memuat_judul_kolom_kedua_sheet(): void
    {
        // Streaming menulis judul dari baris pertama data; tanpa penanganan khusus sheet kosong tak berjudul.
        [$anak, $kunj] = $this->sheets(['id_kel' => $this->kelB->id]);

        $this->assertSame('NIK', $anak->getCell('A1')->getValue());
        $this->assertSame('Thn Sasaran 72 bln', $anak->getCell('AN1')->getValue());
        $this->assertNull($anak->getCell('A2')->getValue());
        $this->assertSame('NIK', $kunj->getCell('A1')->getValue());
        $this->assertNull($kunj->getCell('A2')->getValue());
    }

    public function test_judul_kolom_tidak_ada_yang_kembar(): void
    {
        foreach ([new KesmasAnakSheet([]), new KesmasKunjunganSheet([])] as $lembar) {
            $judul = $lembar->headings();
            $this->assertSame($judul, array_values(array_unique($judul)), $lembar->title() . ' memuat judul kembar');
        }
    }

    public function test_teks_tetap_literal_dan_angka_tetap_angka(): void
    {
        $a = $this->anak('6474010101250011', $this->kelA, [
            'nama' => '=1+1', 'bbl' => 3.2, 'pbl' => 50, 'lk_lahir' => 34, 'usia_kehamilan_lahir' => 38,
        ]);
        $this->kunjungan($a, '2026-01-15', ['bb' => 0]);

        [$anak, $kunj] = $this->sheets(['id_kel' => $this->kelA->id]);

        $this->assertSame('=1+1', $anak->getCell('B2')->getValue(), 'awalan = bukan rumus');
        $this->assertSame('s', $anak->getCell('B2')->getDataType());
        foreach (['R' => 3.2, 'S' => 50, 'T' => 34, 'U' => 38] as $kolom => $nilai) {
            $this->assertSame('n', $anak->getCell($kolom . '2')->getDataType(), "$kolom harus angka");
            $this->assertEquals($nilai, $anak->getCell($kolom . '2')->getValue(), $kolom);
        }
        foreach (['D', 'E', 'F'] as $kolom) {
            $this->assertSame('n', $kunj->getCell($kolom . '2')->getDataType(), "$kolom harus angka");
        }
        $this->assertEquals(0, $kunj->getCell('E2')->getValue(), 'BB 0 tetap angka nol, bukan kosong');
    }

    public function test_download_menolak_filter_tidak_sah(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.export.kesmas.index'))
            ->get(route('admin.export.kesmas.download', ['id_kel' => 999999, 'dari' => '2026-02-01', 'sampai' => '2026-01-01']))
            ->assertRedirect(route('admin.export.kesmas.index'))
            ->assertSessionHasErrors(['id_kel', 'sampai']);
    }

    public function test_sheet_per_anak_berisi_label_dan_terfilter_kelurahan(): void
    {
        $this->anak('6474010101250001', $this->kelA, ['air_bersih' => 1, 'jamban_sehat' => 0, 'skrining_shk' => 'tidak_normal', 'no_id_epus' => 'EP-1']);
        $this->anak('6474010101250002', $this->kelB, ['air_bersih' => 1]);

        [$anak] = $this->sheets(['id_kel' => $this->kelA->id]);

        $this->assertSame('NIK', $anak->getCell('A1')->getValue());
        $this->assertSame('Air Bersih', $anak->getCell('L1')->getValue());
        $this->assertSame('SHK', $anak->getCell('AA1')->getValue());
        $this->assertSame('6474010101250001', $anak->getCell('A2')->getValue());
        $this->assertSame('s', $anak->getCell('A2')->getDataType());       // NIK tetap teks
        $this->assertSame('Berbas Tengah', $anak->getCell('F2')->getValue());
        $this->assertSame('EP-1', $anak->getCell('J2')->getValue());
        $this->assertSame('Ya', $anak->getCell('L2')->getValue());
        $this->assertSame('Tidak', $anak->getCell('M2')->getValue());
        $this->assertSame('', (string) $anak->getCell('N2')->getValue());  // NULL → kosong
        $this->assertSame('Tidak normal', $anak->getCell('AA2')->getValue());
        $this->assertNull($anak->getCell('A3')->getValue());                 // anak kelurahan B tak ikut
    }

    public function test_sheet_per_kunjungan_terfilter_tanggal(): void
    {
        $a = $this->anak('6474010101250003', $this->kelA);
        $this->kunjungan($a, '2026-01-15', ['kn1' => 1, 'mtbs' => 0, 'pemeriksaan_gigi' => 'Karies']);
        $this->kunjungan($a, '2026-03-15', ['kn3' => 1]);

        [, $kunj] = $this->sheets(['dari' => '2026-01-01', 'sampai' => '2026-01-31']);

        $this->assertSame('KN1', $kunj->getCell('H1')->getValue());
        $this->assertSame('Atresia Bilier', $kunj->getCell('M1')->getValue());
        $this->assertSame('6474010101250003', $kunj->getCell('A2')->getValue());
        $this->assertSame('s', $kunj->getCell('A2')->getDataType());
        $this->assertSame('2026-01-15', $kunj->getCell('C2')->getValue());
        $this->assertSame('Ya', $kunj->getCell('H2')->getValue());          // kn1
        $this->assertSame('', (string) $kunj->getCell('J2')->getValue());   // mtbm NULL
        $this->assertSame('Tidak', $kunj->getCell('K2')->getValue());       // mtbs = 0
        $this->assertSame('Karies', $kunj->getCell('Q2')->getValue());
        $this->assertNull($kunj->getCell('A3')->getValue());                 // kunjungan Maret tak ikut
    }

    public function test_sheet_per_kunjungan_urut_nama_lalu_tanggal_dan_anak_tanpa_kunjungan_dilewati(): void
    {
        $zed = $this->anak('6474010101250020', $this->kelA, ['nama' => 'Zed']);
        $abi = $this->anak('6474010101250021', $this->kelA, ['nama' => 'Abi']);
        $this->anak('6474010101250022', $this->kelA, ['nama' => 'Mimi']); // tanpa kunjungan
        $this->kunjungan($zed, '2026-03-15');
        $this->kunjungan($zed, '2026-01-15');
        $this->kunjungan($abi, '2026-02-15');

        [, $kunj] = $this->sheets(['id_kel' => $this->kelA->id]);

        $urutan = [];
        for ($baris = 2; $kunj->getCell("A$baris")->getValue() !== null; $baris++) {
            $urutan[] = $kunj->getCell("B$baris")->getValue() . ' ' . $kunj->getCell("C$baris")->getValue();
        }
        $this->assertSame(['Abi 2026-02-15', 'Zed 2026-01-15', 'Zed 2026-03-15'], $urutan);
    }

    public function test_sheet_per_kunjungan_ikut_filter_wilayah(): void
    {
        $a = $this->anak('6474010101250004', $this->kelA);
        $b = $this->anak('6474010101250005', $this->kelB);
        $this->kunjungan($a, '2026-01-15');
        $this->kunjungan($b, '2026-01-16');

        [, $kunj] = $this->sheets(['id_kel' => $this->kelB->id]);

        $this->assertSame('6474010101250005', $kunj->getCell('A2')->getValue());
        $this->assertNull($kunj->getCell('A3')->getValue());
    }

    public function test_sheet_per_anak_memuat_hbig_sasaran_dan_tahun_sasaran_di_ujung_kanan(): void
    {
        $this->anak('6474010101250006', $this->kelA, ['tgl_lahir' => '2025-05-10', 'tgl_hbig' => '2025-05-10', 'sasaran_balita_kesmas' => 1]);
        $this->anak('6474010101250007', $this->kelA, ['tgl_lahir' => '2024-02-01']); // belum ditandai — tetap terekspor

        [$anak] = $this->sheets(['id_kel' => $this->kelA->id]);

        $judul = [
            'AF' => 'Komplikasi Neonatal', 'AG' => 'Tgl HBIG', 'AH' => 'Sasaran Balita Kesmas',
            'AI' => 'Thn Sasaran IDL', 'AJ' => 'Thn Sasaran 24 bln', 'AK' => 'Thn Sasaran IBL',
            'AL' => 'Thn Sasaran 48 bln', 'AM' => 'Thn Sasaran 60 bln', 'AN' => 'Thn Sasaran 72 bln',
        ];
        foreach ($judul as $kolom => $teks) {
            $this->assertSame($teks, $anak->getCell($kolom . '1')->getValue(), $kolom);
        }

        // Urut nama: ...0006 lalu ...0007.
        $this->assertSame('2025-05-10', $anak->getCell('AG2')->getValue());
        $this->assertSame('s', $anak->getCell('AG2')->getDataType());
        $this->assertSame('Ya', $anak->getCell('AH2')->getValue());
        foreach (['AI' => 2026, 'AJ' => 2027, 'AK' => 2028, 'AL' => 2029, 'AM' => 2030, 'AN' => 2031] as $kolom => $tahun) {
            $this->assertSame($tahun, (int) $anak->getCell($kolom . '2')->getValue(), $kolom);
            $this->assertSame('n', $anak->getCell($kolom . '2')->getDataType(), "$kolom harus angka");
        }
        $this->assertSame('', (string) $anak->getCell('AG3')->getValue());
        $this->assertSame('', (string) $anak->getCell('AH3')->getValue(), 'NULL = belum ditandai → kosong');
        $this->assertSame(2025, (int) $anak->getCell('AI3')->getValue());
    }
}
