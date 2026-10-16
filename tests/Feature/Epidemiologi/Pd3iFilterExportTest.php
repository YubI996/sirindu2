<?php

namespace Tests\Feature\Epidemiologi;

use App\Exports\SurveillanceExport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\RumahSakit;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Support\FilterPd3i;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Tombol Excel Dasbor PD3I mengikuti filter Faskes Pelapor & rentang tanggal,
 * tanpa melonggarkan scope faskes (visibleTo).
 */
class Pd3iFilterExportTest extends TestCase
{
    use DatabaseTransactions;

    private const FASKES = 'PKM Uji Ekspor Filter';

    private User $superadmin;
    private User $userRs;

    protected function setUp(): void
    {
        parent::setUp();

        $kec   = Kecamatan::factory()->create();
        $kel   = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt    = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $rs    = RumahSakit::create(['name' => 'RS Uji Ekspor', 'kode_rs' => 'RS-EKS', 'is_active' => true]);
        $jenis = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'UJI_EKS', 'nama_penyakit' => 'Uji Ekspor', 'is_active' => true]);

        $this->superadmin = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $this->userRs     = User::factory()->create([
            'type' => 1, 'role' => 'surveilans_rs', 'faskes_type' => 'rs', 'id_rs' => $rs->id,
        ]);

        $kasus = fn (string $tgl, string $instansi, array $faskes) => SurveillanceCase::factory()->create([
            'id_jenis_kasus' => $jenis->id, 'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_rt' => $rt->id,
            'instansi_pelapor' => $instansi, 'tanggal_lapor' => $tgl, 'tanggal_onset' => $tgl,
            'created_by' => $this->superadmin->id, 'updated_by' => $this->superadmin->id,
        ] + $faskes);

        // Faskes uji: Feb (bukan RS), Jun (milik RS), Jun (bukan RS). Faskes lain: Jun.
        $kasus('2026-02-10', self::FASKES, ['faskes_type' => 'puskesmas', 'id_faskes' => 999999]);
        $kasus('2026-06-15', self::FASKES, ['faskes_type' => 'rs', 'id_faskes' => $rs->id]);
        $kasus('2026-06-16', self::FASKES, ['faskes_type' => 'puskesmas', 'id_faskes' => 999999]);
        $kasus('2026-06-17', 'Faskes Lain Uji', ['faskes_type' => 'puskesmas', 'id_faskes' => 999999]);
    }

    private function filterJuni(): FilterPd3i
    {
        return new FilterPd3i(tahun: 2026, faskesPelapor: [self::FASKES], tanggalDari: '2026-05-01', tanggalSampai: '2026-06-30');
    }

    public function test_ekspor_menghormati_faskes_dan_rentang(): void
    {
        $this->actingAs($this->superadmin);
        $this->assertSame(2, SurveillanceExport::untukDasborPd3i($this->filterJuni())->query()->count());
    }

    public function test_akun_rs_tetap_hanya_melihat_kasusnya_sendiri(): void
    {
        $this->actingAs($this->userRs);
        $this->assertSame(1, SurveillanceExport::untukDasborPd3i($this->filterJuni())->query()->count());
    }

    public function test_tombol_excel_dasbor_meneruskan_filter_baru(): void
    {
        Excel::fake();

        $this->actingAs($this->superadmin)->get(route('admin.pd3i.exportExcel', [
            'tahun' => 2026, 'faskes_pelapor' => [self::FASKES],
            'tanggal_dari' => '2026-05-01', 'tanggal_sampai' => '2026-06-30',
        ]))->assertOk();

        Excel::assertDownloaded('surveilans-pd3i-2026_2026-05-01_sd_2026-06-30.xlsx', fn (SurveillanceExport $e) => $e->query()->count() === 2);
    }

    /** Karakter `/` di wilker dulu membuat nama berkas tak sah → 500 (audit SEC-004). */
    public function test_nama_berkas_excel_aman_dari_karakter_path(): void
    {
        Excel::fake();

        $this->actingAs($this->superadmin)->get(route('admin.pd3i.exportExcel', [
            'tahun' => 2026, 'wilker' => ['Bontang/Utara I'],
        ]))->assertOk();

        Excel::assertDownloaded('surveilans-pd3i-2026-Bontang_Utara_I.xlsx');
    }

    /**
     * Teks bebas berawalan "=" tidak boleh menjadi rumus Excel: petugas faskes
     * mengetik, superadmin membuka ekspor se-kota (audit SEC-001).
     */
    public function test_teks_berawalan_sama_dengan_tetap_literal(): void
    {
        $sheet = (new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
        $export = new SurveillanceExport(tahun: 2026);

        $export->bindValue($sheet->getCell('A1'), '=HYPERLINK("http://contoh","klik")');
        $export->bindValue($sheet->getCell('A2'), 42);

        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $sheet->getCell('A1')->getDataType());
        $this->assertSame('=HYPERLINK("http://contoh","klik")', $sheet->getCell('A1')->getValue());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC, $sheet->getCell('A2')->getDataType());
    }
}
