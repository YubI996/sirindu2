<?php

namespace Tests\Feature;

use App\Imports\AnakImport;
use App\Imports\CapilImport;
use App\Imports\ImunisasiImport;
use App\Imports\PengukuranImport;
use App\Imports\UkurImport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Imunisasi;
use App\Models\User;
use Database\Seeders\JenisVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Nama anak WAJIB di setiap importer yang mengenali anak dari berkas. Dulu aturannya "2 dari 3"
 * (NIK, nama, tgl lahir), jadi baris ber-NIK + tgl lahir tanpa nama lolos dan vaksin/ukuran menempel ke
 * anak yang cocok NIK-nya tanpa pemeriksa silang nama. Penanda kosong ("0", "-", "#N/A") ikut ditolak.
 *
 * Import Operasi Timbang sengaja tak diubah (jumlah anak OT harus sama dengan baris berkas).
 */
class NamaAnakWajibImportTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = '3201011501200001';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenisVaksinSeeder::class);
        $this->admin = User::factory()->create(['type' => 1]);
    }

    private function anak(array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Budi Santoso', 'nik' => self::NIK, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2020-01-15', 'status' => 1, 'no' => 'REG-1',
        ], $o));
    }

    private function teks(array $hasil): string
    {
        return implode("\n", $hasil['failures']);
    }

    // ---- Imunisasi ---------------------------------------------------------------

    private function imunisasiWide(string $nama): array
    {
        $i = new ImunisasiImport($this->admin->id);
        $i->collection(collect([
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0'],
            [self::NIK, $nama, '2020-01-15', '2020-01-15'],
        ]));

        return $i->getResults();
    }

    #[DataProvider('namaTakSah')]
    public function test_imunisasi_wide_menolak_nama_tak_sah_meski_nik_dan_tgl_lahir_ada(string $nama): void
    {
        $this->anak();

        $hasil = $this->imunisasiWide($nama);

        $this->assertSame(0, Imunisasi::count(), 'vaksin tak boleh menempel ke anak tanpa nama yang sah');
        $this->assertSame(1, $hasil['error_count']);
        $this->assertStringContainsString('[ERROR] Baris 2', $this->teks($hasil));
        $this->assertStringContainsString('nama_anak', $this->teks($hasil));
    }

    public static function namaTakSah(): array
    {
        return ['kosong' => [''], 'nol' => ['0'], 'strip' => ['-'], 'galat excel' => ['#N/A']];
    }

    public function test_imunisasi_wide_nama_sah_tetap_masuk(): void
    {
        $this->anak();

        $hasil = $this->imunisasiWide('Budi Santoso');

        $this->assertSame(1, Imunisasi::count());
        $this->assertSame(0, $hasil['error_count']);
    }

    public function test_imunisasi_long_menolak_nama_kosong(): void
    {
        $this->anak();
        $i = new ImunisasiImport($this->admin->id);
        $i->collection(collect([
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'kode_vaksin', 'tanggal_pemberian'],
            [self::NIK, '', '2020-01-15', 'HB0', '2020-01-15'],
        ]));

        $this->assertSame(0, Imunisasi::count());
        $this->assertSame(1, $i->getResults()['error_count']);
    }

    // ---- Pengukuran & Ukur -------------------------------------------------------

    public function test_pengukuran_menolak_nama_kosong(): void
    {
        $anak = $this->anak();
        $i = new PengukuranImport($this->admin->id);
        $i->collection(collect([
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'tgl_kunjungan', 'bb', 'tb'],
            [self::NIK, '', '2020-01-15', '2021-01-15', '10', '80'],
        ]));

        $hasil = $i->getResults();
        $this->assertSame(0, DataAnak::where('id_anak', $anak->id)->count());
        $this->assertSame(1, $hasil['error_count']);
        $this->assertStringContainsString('nama_anak', $this->teks($hasil));
    }

    public function test_ukur_menolak_nama_kosong_walau_nik_ada(): void
    {
        $anak = $this->anak();
        $i = new UkurImport($this->admin->id);
        $i->collection(collect([
            ['nik', 'nama_anak', 'tanggalukur', 'bb', 'tb'],
            [self::NIK, '', '2021-01-15', '10', '80'],
        ]));

        $hasil = $i->getResults();
        $this->assertSame(0, DataAnak::where('id_anak', $anak->id)->count());
        $this->assertSame(1, $hasil['error_count']);
    }

    // ---- Importer yang membuat anak ----------------------------------------------

    public function test_import_anak_nama_kosong_dengan_nik_dilaporkan_bukan_diam_diam(): void
    {
        $i = new AnakImport($this->admin->id);
        $i->collection(collect([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            [self::NIK, '', '2020-01-15', 'L'],
        ]));

        $hasil = $i->getResults();
        $this->assertSame(0, Anak::count());
        $this->assertSame(1, $hasil['error_count']);
        $this->assertStringContainsString('nama', $this->teks($hasil));
    }

    public function test_import_anak_baris_benar_benar_kosong_tetap_diam(): void
    {
        $i = new AnakImport($this->admin->id);
        $i->collection(collect([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            ['', '', '', ''],
        ]));

        $this->assertSame(0, $i->getResults()['error_count'], 'baris kosong (mis. trailing) bukan kesalahan');
        $this->assertSame(0, Anak::count());
    }

    public function test_capil_nama_nol_tidak_membuat_anak_bernama_nol(): void
    {
        $header = ['NIK', 'NAMA LENGKAP', 'JENIS KLMIN', 'TGL LHR', 'NO KK', 'NAMA LENGKAP IBU', 'NAMA LENGKAP AYAH'];
        $i = new CapilImport(1, '2026-06-25');
        $i->collection(collect([
            $header,
            ['3274010101200001', '0', 'LAKI-LAKI', '2020-01-15', '3274999999999999', 'SITI', 'JOKO'],
        ]));

        $hasil = $i->getResults();
        $this->assertSame(0, Anak::count());
        $this->assertSame(1, $hasil['error_count']);
    }
}
