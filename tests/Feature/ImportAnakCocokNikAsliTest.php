<?php

namespace Tests\Feature;

use App\Imports\AnakImport;
use App\Models\Anak;
use App\Services\NikDummyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Import Anak tak boleh membuat anak GANDA untuk orang yang sudah ada.
 *
 * Dua arah yang dulu lolos:
 *  (a) baris tanpa NIK -> findExisting() hanya membandingkan anak ber-NIK DUMMY, jadi anak
 *      ber-NIK asli yang sudah ada tak pernah ketemu dan dibuatkan NIK dummy baru;
 *  (b) baris ber-NIK valid yang belum ada di DB -> langsung updateOrCreate by NIK tanpa
 *      mencari anak yang sama bernama dummy (berkas dikoreksi: NIK baru diisi belakangan).
 *
 * Nomor yang sama-sama ambigu DILAPORKAN, tidak ditebak (pola ImunisasiImport).
 */
class ImportAnakCocokNikAsliTest extends TestCase
{
    use RefreshDatabase;

    private const NIK_ASLI = '6474011005200001';   // digit ke-13 = '0' -> bukan dummy
    private const NIK_ASLI_2 = '6474011005200002';
    private const NIK_DUMMY = '6474001005209001';  // digit ke-13 = '9' -> dummy

    private function anak(array $override = []): Anak
    {
        return Anak::create(array_merge([
            'nik' => self::NIK_ASLI, 'nama' => 'ANI WIJAYA', 'jk' => 2,
            'tgl_lahir' => '2021-03-10', 'no' => 'REG-1', 'status' => 1,
        ], $override));
    }

    private function impor(array $rows): array
    {
        $import = new AnakImport(1);
        $import->collection(collect($rows));

        return $import->getResults();
    }

    // ---- (a) baris tanpa NIK vs anak ber-NIK asli --------------------------------

    public function test_nik_kosong_memakai_anak_ber_nik_asli_bukan_membuat_dummy_baru(): void
    {
        $asli = $this->anak();

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            ['', 'ANI WIJAYA', '2021-03-10', 'P'],
        ]);

        $this->assertSame(1, Anak::count(), 'anak ber-NIK asli yang sama tidak boleh digandakan');
        $this->assertSame(self::NIK_ASLI, $asli->fresh()->nik);
    }

    public function test_nik_kosong_mengisi_kolom_yang_diberikan_tanpa_menimpa_yang_tidak(): void
    {
        $this->anak([
            'nama' => 'Ani Wijaya', 'nik_ibu' => 'IBU ASLI', 'alamat' => 'JL LAMA', 'id_kec' => 5,
        ]);

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'alamat'],
            ['', 'ANI WIJAYA', '2021-03-10', 'P', 'JL BARU'],
        ]);

        $anak = Anak::firstOrFail();
        $this->assertSame('JL BARU', $anak->alamat, 'kolom yang diberikan berkas ikut terisi');
        $this->assertSame('IBU ASLI', $anak->nik_ibu, 'kolom yang tak ada di berkas tidak dikosongkan');
        $this->assertSame(5, (int) $anak->id_kec, 'wilayah yang tak diberikan tidak dikosongkan');
        $this->assertSame('Ani Wijaya', $anak->nama, 'identitas anak ber-NIK asli tidak ditimpa ejaan berkas');
        $this->assertSame('REG-1', $anak->no, 'no registrasi lama tidak diganti nomor IMP- otomatis');
    }

    public function test_nik_kosong_dengan_dua_kandidat_asli_dilaporkan_bukan_ditebak(): void
    {
        $this->anak();
        $this->anak(['nik' => self::NIK_ASLI_2, 'no' => 'REG-2']);

        $hasil = $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            ['', 'ANI WIJAYA', '2021-03-10', 'P'],
        ]);

        $this->assertSame(2, Anak::count(), 'ambigu: tidak boleh membuat anak ketiga');
        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, $hasil['success']);
        $this->assertStringContainsString('[ERROR]', implode("\n", $hasil['failures']));
        $this->assertStringContainsString('Ditemukan 2 anak', implode("\n", $hasil['failures']));
    }

    public function test_nik_kosong_kk_berbeda_tetap_anak_berbeda(): void
    {
        $this->anak(['no_kk' => '3274000000000001']);

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'no_kk'],
            ['', 'ANI WIJAYA', '2021-03-10', 'P', '3274000000000002'],
        ]);

        $this->assertSame(2, Anak::count(), 'KK berbeda = keluarga berbeda, bukan anak yang sama');
    }

    public function test_nik_kosong_dummy_lama_dicocokkan_tanpa_membedakan_huruf_besar_kecil(): void
    {
        $this->anak(['nik' => self::NIK_DUMMY, 'nama' => 'ANI WIJAYA']);

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            ['', 'ani wijaya', '2021-03-10', 'P'],
        ]);

        $this->assertSame(1, Anak::count(), 'Capil kapital vs posyandu huruf kecil = orang yang sama');
    }

    public function test_nik_tidak_valid_diperlakukan_seperti_nik_kosong(): void
    {
        $this->anak();

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            ['12345', 'ANI WIJAYA', '2021-03-10', 'P'],
        ]);

        $this->assertSame(1, Anak::count());
    }

    // ---- (b) baris ber-NIK valid vs anak yang sama bernama dummy -----------------

    public function test_nik_valid_menaikkan_nik_dummy_anak_yang_sama_bukan_membuat_anak_baru(): void
    {
        $dummy = $this->anak(['nik' => self::NIK_DUMMY]);

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            [self::NIK_ASLI, 'ANI WIJAYA', '2021-03-10', 'P'],
        ]);

        $this->assertSame(1, Anak::count(), 'NIK asli yang baru diisi harus menggantikan NIK dummy, bukan menggandakan');
        $anak = Anak::firstOrFail();
        $this->assertSame($dummy->id, $anak->id, 'baris yang sama (id sama) supaya data kesehatan ikut');
        $this->assertSame(self::NIK_ASLI, $anak->nik);
        $this->assertFalse(NikDummyService::isDummy($anak->nik));
    }

    public function test_nik_valid_yang_sudah_ada_tidak_menyentuh_kembaran_dummy(): void
    {
        $this->anak();
        $this->anak(['nik' => self::NIK_DUMMY, 'no' => 'REG-D']);

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            [self::NIK_ASLI, 'ANI WIJAYA', '2021-03-10', 'P'],
        ]);

        // NIK ketemu persis -> jalur lama. Pasangan asli+dummy yang SUDAH ganda adalah
        // urusan alat gabung Verifikasi RT, bukan diputuskan diam-diam oleh import.
        $this->assertSame(2, Anak::count());
        $this->assertNotNull(Anak::where('nik', self::NIK_DUMMY)->first());
    }

    public function test_nik_valid_dengan_dua_kandidat_dummy_dilaporkan_bukan_ditebak(): void
    {
        $this->anak(['nik' => self::NIK_DUMMY, 'no' => 'REG-D1']);
        $this->anak(['nik' => '6474001005209002', 'no' => 'REG-D2']);

        $hasil = $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            [self::NIK_ASLI, 'ANI WIJAYA', '2021-03-10', 'P'],
        ]);

        $this->assertSame(2, Anak::count(), 'ambigu: tidak boleh membuat anak ketiga');
        $this->assertSame(1, $hasil['error_count']);
        $this->assertStringContainsString('Ditemukan 2 anak', implode("\n", $hasil['failures']));
    }

    public function test_nik_valid_kk_berbeda_dengan_dummy_tetap_anak_baru(): void
    {
        $this->anak(['nik' => self::NIK_DUMMY, 'no_kk' => '3274000000000001']);

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'no_kk'],
            [self::NIK_ASLI, 'ANI WIJAYA', '2021-03-10', 'P', '3274000000000002'],
        ]);

        $this->assertSame(2, Anak::count(), 'KK berbeda = keluarga berbeda, bukan anak yang sama');
    }
}
