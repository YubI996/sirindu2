<?php

namespace Tests\Feature;

use App\Imports\AnakImport;
use App\Models\Anak;
use App\Services\NikDummyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportAnakDedupTest extends TestCase
{
    use RefreshDatabase;

    /** Header minimal yang dikenali AnakImport. */
    private function header(): array
    {
        return ['nik', 'nama', 'tgl_lahir', 'jk'];
    }

    public function test_duplikat_perempuan_nik_kosong_tergabung_jadi_satu(): void
    {
        $rows = collect([
            $this->header(),
            ['', 'ANI WIJAYA', '2021-03-10', 'P'],
            ['', 'ANI WIJAYA', '2021-03-10', 'P'],
        ]);

        (new AnakImport(1))->collection($rows);

        $this->assertEquals(1, Anak::count());
        $anak = Anak::first();
        $this->assertEquals(2, $anak->jk);
    }

    public function test_duplikat_laki_nik_kosong_tetap_tergabung_jadi_satu(): void
    {
        $rows = collect([
            $this->header(),
            ['', 'BUDI SANTOSO', '2020-01-15', 'L'],
            ['', 'BUDI SANTOSO', '2020-01-15', 'L'],
        ]);

        (new AnakImport(1))->collection($rows);

        $this->assertEquals(1, Anak::count());
        $this->assertEquals(1, Anak::first()->jk);
    }

    public function test_nama_tgl_jk_sama_tapi_kk_beda_tetap_dua_record(): void
    {
        $rows = collect([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'no_kk'],
            ['', 'AGUS RIZKI', '2020-05-05', 'L', '3274000000000001'],
            ['', 'AGUS RIZKI', '2020-05-05', 'L', '3274000000000002'],
        ]);

        (new AnakImport(1))->collection($rows);

        // KK berbeda → dianggap anak berbeda, tidak digabung.
        $this->assertEquals(2, Anak::count());
    }

    public function test_nama_tgl_jk_sama_dan_kk_sama_tetap_satu_record(): void
    {
        $rows = collect([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'no_kk'],
            ['', 'AGUS RIZKI', '2020-05-05', 'L', '3274000000000001'],
            ['', 'AGUS RIZKI', '2020-05-05', 'L', '3274000000000001'],
        ]);

        (new AnakImport(1))->collection($rows);

        // KK sama → keluarga sama, tetap digabung.
        $this->assertEquals(1, Anak::count());
    }

    public function test_nama_tgl_jk_sama_kk_salah_satu_kosong_tetap_digabung(): void
    {
        $rows = collect([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'no_kk'],
            ['', 'AGUS RIZKI', '2020-05-05', 'L', ''],
            ['', 'AGUS RIZKI', '2020-05-05', 'L', '3274000000000002'],
        ]);

        (new AnakImport(1))->collection($rows);

        // KK tak bisa dibandingkan (satu kosong) → jatuh ke perilaku lama (gabung).
        $this->assertEquals(1, Anak::count());
    }

    public function test_nik_dummy_perempuan_diencode_dengan_dd_plus_40(): void
    {
        $rows = collect([
            $this->header(),
            ['', 'DEWI LESTARI', '2021-03-10', 'P'],
        ]);

        (new AnakImport(1))->collection($rows);

        $nik = Anak::first()->nik;
        // Perempuan: DD+40 → tanggal 10 jadi 50; format prefix wilayah(6) + 50 03 21
        $this->assertTrue(NikDummyService::isDummy($nik));
        $this->assertEquals('500321', substr($nik, 6, 6));
    }
}
