<?php

namespace Tests\Feature;

use App\Exports\AnakExport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Export Data Anak (view `alldata`, satu baris per KUNJUNGAN) vs data hasil import.
 *
 * Dua sebab export tak sama dengan berkas import:
 *  1. NIK/No KK 16 digit diekspor sebagai ANGKA. Excel hanya presisi 15 digit: tampil 6,47401E+15 dan
 *     digit ke-16 menjadi 0 bila disimpan ulang, lalu berkas itu dibaca import sebagai NIK tak valid.
 *  2. Anak tanpa kunjungan — termasuk semua anak hasil AnakImport (identitas saja) — tak ada di view
 *     `alldata` (FROM data_anak INNER JOIN anak), jadi tak pernah muncul di export.
 */
class ExportAnakTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nama, string $nik, array $extra = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => $nama, 'nik' => $nik, 'no_kk' => '6474011205100007', 'nik_ortu' => '6474015501850002',
            'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'manual',
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tgl): DataAnak
    {
        return DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 12, 'posisi' => 'L',
            'tb' => 70, 'bb' => 8, 'lla' => 13, 'lk' => 44, 'id_user' => 1]);
    }

    private function buka(AnakExport $export): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'anak') . '.xlsx';
        try {
            $export->simpan($path);

            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }

    /** @return list<list<mixed>> baris (tanpa judul) hasil export yang dibuka kembali dari berkas xlsx */
    private function barisXlsx(AnakExport $export): array
    {
        return array_slice($this->buka($export)->getActiveSheet()->toArray(null, true, false, false), 1);
    }

    private function sel(AnakExport $export, string $alamat): \PhpOffice\PhpSpreadsheet\Cell\Cell
    {
        return $this->buka($export)->getActiveSheet()->getCell($alamat);
    }

    public function test_nik_no_kk_dan_nik_ortu_tersimpan_sebagai_teks_bukan_angka(): void
    {
        $a = $this->anak('Anak Panjang', '6474010101230001');
        $this->kunjungan($a, '2025-05-05');

        foreach (['A2' => '6474011205100007', 'B2' => '6474010101230001', 'D2' => '6474015501850002'] as $alamat => $nilai) {
            $sel = $this->sel(new AnakExport(new Request()), $alamat);
            $this->assertSame('s', $sel->getDataType(), "$alamat tersimpan sebagai angka — Excel akan memotongnya ke 15 digit");
            $this->assertSame($nilai, $sel->getValue());
        }
    }

    public function test_kolom_lain_tetap_angka_dan_teks_sebagaimana_semula(): void
    {
        $a = $this->anak('Anak Biasa', '6474010101230001');
        $this->kunjungan($a, '2025-05-05');

        $this->assertSame('n', $this->sel(new AnakExport(new Request()), 'U2')->getDataType()); // Tinggi Badan
        $this->assertSame('Anak Biasa', $this->sel(new AnakExport(new Request()), 'C2')->getValue());
    }

    public function test_bawaan_hanya_anak_yang_punya_kunjungan_dan_anak_berkunjungan_ganda_muncul_per_kunjungan(): void
    {
        $dua = $this->anak('Dua Kunjungan', '6474010101230001');
        $this->kunjungan($dua, '2025-01-05');
        $this->kunjungan($dua, '2025-04-05');
        $this->anak('Tanpa Kunjungan', '6474010101230002');

        $nama = array_column($this->barisXlsx(new AnakExport(new Request())), 2);

        $this->assertSame(['Dua Kunjungan', 'Dua Kunjungan'], $nama, 'perilaku bawaan tak boleh berubah');
    }

    public function test_opsi_menyertakan_anak_tanpa_kunjungan_satu_baris_dengan_kolom_pengukuran_kosong(): void
    {
        $dua = $this->anak('Dua Kunjungan', '6474010101230001');
        $this->kunjungan($dua, '2025-01-05');
        $this->kunjungan($dua, '2025-04-05');
        $this->anak('Tanpa Kunjungan', '6474010101230002');

        $baris = $this->barisXlsx(new AnakExport(new Request(['sertakan_tanpa_kunjungan' => '1'])));

        $this->assertSame(['Dua Kunjungan', 'Dua Kunjungan', 'Tanpa Kunjungan'], array_column($baris, 2));
        $tanpa = $baris[2];
        $this->assertSame('6474010101230002', (string) $tanpa[1]);
        $this->assertNull($tanpa[17], 'Tanggal Kunjungan harus kosong');
        $this->assertNull($tanpa[20], 'Tinggi Badan harus kosong');
        $this->assertNull($tanpa[22], 'BMI harus kosong');
    }

    public function test_opsi_tidak_terkena_rentang_tanggal_tetapi_tetap_mengikuti_filter_wilayah(): void
    {
        $utara = Kecamatan::create(['name' => 'Bontang Utara']);
        $selatan = Kecamatan::create(['name' => 'Bontang Selatan']);
        $a = $this->anak('Berkunjung Utara', '6474010101230001', ['id_kec' => $utara->id]);
        $this->kunjungan($a, '2025-05-05');
        $this->anak('Tanpa Utara', '6474010101230002', ['id_kec' => $utara->id]);
        $this->anak('Tanpa Selatan', '6474010101230003', ['id_kec' => $selatan->id]);

        $req = new Request([
            'sertakan_tanpa_kunjungan' => '1', 'from_date' => '2025-01-01', 'to_date' => '2025-12-31', 'id_kec' => (string) $utara->id,
        ]);

        // rentang tanggal hanya menyaring kunjungan; anak tanpa kunjungan tak punya tanggal untuk dibandingkan
        $this->assertSame(['Berkunjung Utara', 'Tanpa Utara'], array_column($this->barisXlsx(new AnakExport($req)), 2));

        $di_luar_rentang = new Request([
            'sertakan_tanpa_kunjungan' => '1', 'from_date' => '2030-01-01', 'to_date' => '2030-12-31', 'id_kec' => (string) $utara->id,
        ]);
        $this->assertSame(['Tanpa Utara'], array_column($this->barisXlsx(new AnakExport($di_luar_rentang)), 2));
    }

    public function test_nilai_berawalan_sama_dengan_tetap_teks_bukan_rumus(): void
    {
        $a = $this->anak('=HYPERLINK("http://x")', '6474010101230001', ['catatan' => '=1+1']);
        $this->kunjungan($a, '2025-05-05');

        $this->assertSame('s', $this->sel(new AnakExport(new Request()), 'C2')->getDataType());
        $this->assertSame('=HYPERLINK("http://x")', $this->sel(new AnakExport(new Request()), 'C2')->getValue());
        $this->assertSame('s', $this->sel(new AnakExport(new Request()), 'L2')->getDataType());
    }

    public function test_tombol_export_data_all_menyertakan_anak_tanpa_kunjungan(): void
    {
        $this->kunjungan($this->anak('Berkunjung', '6474010101230001'), '2025-05-05');
        $this->anak('Tanpa Kunjungan', '6474010101230002');

        $response = $this->actingAs(User::factory()->create(['type' => 1]))->get(route('admin.exportAllExcel'));
        $path = tempnam(sys_get_temp_dir(), 'anak') . '.xlsx';
        try {
            file_put_contents($path, $response->streamedContent());
            $baris = array_slice(IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false), 1);
        } finally {
            @unlink($path);
        }

        $this->assertStringContainsString('all-data-anak.xlsx', $response->headers->get('content-disposition') ?? '');
        $this->assertSame(['Berkunjung', 'Tanpa Kunjungan'], array_column($baris, 2));
    }

    public function test_form_export_menawarkan_opsi_anak_tanpa_kunjungan_dan_menjelaskan_rentang_tanggal(): void
    {
        $html = $this->actingAs(User::factory()->create(['type' => 1]))
            ->get(route('admin.exportView'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*type="checkbox"[^>]*name="sertakan_tanpa_kunjungan"[^>]*value="1"/', $html);
        $this->assertStringContainsString('belum punya kunjungan', $html);
        $this->assertStringContainsString('tidak terkena rentang tanggal', $html);
    }

    public function test_satu_tombol_unduh_tanggal_opsional_dan_anak_tanpa_kunjungan_tercentang(): void
    {
        $html = $this->actingAs(User::factory()->create(['type' => 1]))
            ->get(route('admin.exportView'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '>Unduh Excel</button>'));
        $this->assertStringNotContainsString(route('admin.exportAllExcel'), $html, 'tombol kedua "Export Data All" sudah dilebur');
        $this->assertDoesNotMatchRegularExpression('/name="(from|to)_date"[^>]*required/', $html, 'tanggal harus opsional — kosong = semua');
        $this->assertMatchesRegularExpression('/name="sertakan_tanpa_kunjungan"[^>]*checked/', $html);
    }

    public function test_tanggal_boleh_diisi_salah_satu_dan_tetap_berlaku(): void
    {
        $a = $this->anak('Anak', '6474010101230001');
        $this->kunjungan($a, '2025-01-05');
        $this->kunjungan($a, '2025-06-05');

        $hanyaDari = $this->barisXlsx(new AnakExport(new Request(['from_date' => '2025-03-01'])));
        $this->assertSame(['2025-06-05'], array_column($hanyaDari, 17));

        $hanyaSampai = $this->barisXlsx(new AnakExport(new Request(['to_date' => '2025-03-01'])));
        $this->assertSame(['2025-01-05'], array_column($hanyaSampai, 17));
    }
}
