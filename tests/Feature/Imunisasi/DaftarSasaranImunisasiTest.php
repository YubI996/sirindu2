<?php

namespace Tests\Feature\Imunisasi;

use App\Exports\SasaranImunisasiExport;
use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\KohortImunisasi;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Kartu Data sasaran (BBL / SI / Baduta) dasbor imunisasi membuka daftar nama & alamat (BNBA).
 * Insiden Okt 2026: petugas mencocokkan angka kartu dengan hasil memfilter Export Data All di Excel
 * dan mendapat angka berbeda — export itu satu baris per KUNJUNGAN dan kolom puskesmasnya bukan
 * wilayah kerja. Daftar di balik kartu harus memakai query yang SAMA dengan angka kartu.
 */
class DaftarSasaranImunisasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $kelA;

    private int $kelB;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->admin = User::factory()->create(['type' => 1]);

        $kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kelA = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $kec->id])->id;
        $this->kelB = Kelurahan::create(['name' => 'Satimpo', 'id_kecamatan' => $kec->id])->id;

        // Kohort 2026 — BBL: Feb–Mar 2026, SI: Apr 2025–Jan 2026, Baduta: Apr 2024–Mar 2025.
        $this->anak('BBL Awal', '2026-02-01', $this->kelA, '6474010000000001');
        $this->anak('BBL Akhir', '2026-03-31', $this->kelB, '6474010000000002');
        $this->anak('SI Awal', '2025-04-01', $this->kelA, '6474010000000003');
        $this->anak('SI Akhir', '2026-01-31', $this->kelA, '6474010000000004');
        $this->anak('SI Satimpo', '2025-09-09', $this->kelB, '6474010000000005');
        $this->anak('Baduta', '2024-04-01', $this->kelA, '6474010000000006');
        $this->anak('Di Luar Kohort', '2024-03-31', $this->kelA, '6474010000000007');
        $this->anak('Lahir Setelah Kohort', '2026-04-01', $this->kelA, '6474010000000008');
    }

    private function anak(string $nama, string $tglLahir, int $idKel, string $nik): void
    {
        Anak::create([
            'nama' => $nama, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => $tglLahir, 'status' => 1, 'id_kel' => $idKel, 'nama_ibu' => 'Ibu ' . $nama,
        ]);
    }

    public static function kasusFilter(): array
    {
        return ['tanpa filter' => [false], 'filter kelurahan' => [true]];
    }

    /** @dataProvider kasusFilter */
    public function test_jumlah_baris_daftar_sama_dengan_angka_kartu(bool $pakaiFilter): void
    {
        $query = ['tahun' => 2026] + ($pakaiFilter ? ['id_kelurahan' => $this->kelA] : []);
        $filters = $pakaiFilter ? ['id_kelurahan' => $this->kelA] : [];
        $sasaran = app(ImunisasiStatusService::class)->getRingkasanSasaran(KohortImunisasi::dari(2026), $filters);

        foreach (['bbl', 'si', 'baduta'] as $kelompok) {
            $rows = $this->actingAs($this->admin)
                ->getJson(route('admin.imunisasiDashboard.sasaran', $query + ['kelompok' => $kelompok]))
                ->assertOk()
                ->json('rows');

            $this->assertCount($sasaran[$kelompok]['jumlah'], $rows, "daftar {$kelompok} ≠ angka kartu");
        }

        // Pastikan fixture benar-benar menguji batas kohort, bukan kebetulan sama-sama nol.
        $this->assertSame($pakaiFilter ? [1, 2, 1] : [2, 3, 1], [
            $sasaran['bbl']['jumlah'], $sasaran['si']['jumlah'], $sasaran['baduta']['jumlah'],
        ]);
    }

    public function test_daftar_berisi_nama_dan_alamat_tanpa_anak_di_luar_kohort(): void
    {
        $rows = $this->actingAs($this->admin)
            ->getJson(route('admin.imunisasiDashboard.sasaran', ['tahun' => 2026, 'kelompok' => 'si']))
            ->assertOk()
            ->assertJsonPath('judul', 'SI (Surviving Infant) · Kohort 2026')
            ->json('rows');

        $this->assertSame(['SI Akhir', 'SI Awal', 'SI Satimpo'], array_column($rows, 'nama'));
        $this->assertSame('6474010000000003', $rows[1]['nik']);
        $this->assertSame('Ibu SI Awal', $rows[1]['nama_ibu']);
        $this->assertSame('Tanjung Laut', $rows[1]['kelurahan']);
        $this->assertSame('L', $rows[1]['jk']);
        // Puskesmas = wilayah kerja dari kelurahan (sama dengan filter Puskesmas dasbor).
        $this->assertSame('Bontang Selatan 1', $rows[1]['puskesmas']);
    }

    public function test_export_berisi_baris_sejumlah_angka_kartu_dan_nik_sebagai_teks(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.imunisasiDashboard.sasaranExport', ['tahun' => 2026, 'kelompok' => 'si']));
        $response->assertOk();
        $this->assertStringStartsWith('PK', $response->streamedContent());

        $rows = app(ImunisasiStatusService::class)->getDaftarSasaran(KohortImunisasi::dari(2026), 'SI');
        $path = (new SasaranImunisasiExport($rows, 'SI Kohort 2026'))->simpan(tempnam(sys_get_temp_dir(), 'tes'));

        $reader = new Reader();
        $reader->open($path);
        $baris = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $baris[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        @unlink($path);

        $this->assertSame('NIK', $baris[0][2]);
        $this->assertSame('Puskesmas', $baris[0][8]);
        $this->assertSame('Bontang Selatan 1', $baris[1][8]);
        $this->assertCount(1 + 3, $baris, 'header + 3 anak SI');
        $this->assertSame('6474010000000004', $baris[1][2], 'NIK 16 digit harus tetap teks, bukan 6,47E+15');
    }

    public function test_kelompok_tak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.imunisasiDashboard.sasaran', ['kelompok' => 'seluruh']))
            ->assertNotFound();
    }

    public function test_tamu_tidak_bisa_melihat_daftar_nama(): void
    {
        $this->getJson(route('admin.imunisasiDashboard.sasaran', ['kelompok' => 'bbl']))->assertUnauthorized();
    }

    public function test_kartu_sasaran_adalah_tombol_dan_teks_bantuan_formal(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.imunisasiDashboard', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('data-kelompok="bbl"', false)
            ->assertSee('data-kelompok="si"', false)
            ->assertSee('data-kelompok="baduta"', false)
            ->assertSee('id="sasaran-modal"', false)
            ->assertSee('id="sasaran-f-pkm"', false)
            ->assertDontSee('id="sasaran-f-rt"', false)
            ->assertSee('Kohort 2026 mencakup kelahiran 1 April 2025 s.d. 31 Maret 2026.')
            ->assertSee('Februari 2026')
            ->assertDontSee('Ini jumlah anak, bukan ukuran capaian')
            ->assertDontSee('potret umur');
    }
}
