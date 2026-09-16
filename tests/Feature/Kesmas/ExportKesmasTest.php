<?php

namespace Tests\Feature\Kesmas;

use App\Exports\KesmasExport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Export Kesmas (spec §5): dua sheet, label Ya/Tidak/kosong, filter wilayah & tanggal,
 * faskes surveilans ditolak, NIK tetap teks.
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
            file_put_contents($path, Excel::raw(new KesmasExport($filter), \Maatwebsite\Excel\Excel::XLSX));
            $book = IOFactory::load($path);

            return [$book->getSheetByName('Per Anak'), $book->getSheetByName('Per Kunjungan')];
        } finally {
            unlink($path);
        }
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

    public function test_download_memicu_export_dengan_nama_berkas(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('admin.export.kesmas.download', ['id_kel' => $this->kelA->id]))->assertOk();

        Excel::assertDownloaded('kesmas-berbas-tengah-' . now()->format('Ymd') . '.xlsx', fn (KesmasExport $e) => count($e->sheets()) === 2);
    }

    public function test_download_tanpa_filter_bernama_semua(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('admin.export.kesmas.download'))->assertOk();

        Excel::assertDownloaded('kesmas-semua-' . now()->format('Ymd') . '.xlsx');
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
}
