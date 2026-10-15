<?php

namespace Tests\Feature\ImportKesmas;

use App\Models\Anak;
use App\Services\PenautanAnakKesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenautanAnakKesmasTest extends TestCase
{
    use RefreshDatabase;

    private function anak(array $isi): Anak
    {
        return Anak::create(array_merge(['jk' => 1, 'status' => 1], $isi));
    }

    private function tingkat(array $hasil): array
    {
        return array_column($hasil['pesan'], 0);
    }

    public function test_cocok_lewat_nik_tanpa_pesan(): void
    {
        $budi = $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti']);

        $hasil = (new PenautanAnakKesmas())->cari('BUDI SANTOSO', 'L', '2023-01-01', 'Siti', '6474010101230001');

        $this->assertSame($budi->id, $hasil['anak']->id);
        $this->assertSame([], $hasil['pesan']);
    }

    public function test_nik_milik_anak_lain_jatuh_ke_nama(): void
    {
        $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti']);
        $citra = $this->anak(['nama' => 'Citra Lestari', 'nik' => '6474014505220002', 'jk' => 2, 'tgl_lahir' => '2022-05-05', 'nama_ibu' => 'Rina']);

        $hasil = (new PenautanAnakKesmas())->cari('Citra Lestari', 'P', '2022-05-05', 'Rina', '6474010101230001');

        $this->assertSame($citra->id, $hasil['anak']->id);
        $this->assertContains('PERINGATAN', $this->tingkat($hasil));
        $this->assertStringContainsString('tercatat untuk anak lain', $hasil['pesan'][0][1]);
    }

    public function test_cocok_lewat_nama_tidak_mengubah_nik(): void
    {
        $budi = $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101239001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti']);

        $hasil = (new PenautanAnakKesmas())->cari('Budi Santoso', 'L', '2023-01-01', 'Siti', '6474010101230005');

        $this->assertSame($budi->id, $hasil['anak']->id);
        $this->assertSame('6474010101239001', $budi->fresh()->nik);
        $this->assertSame(['INFO'], $this->tingkat($hasil));
    }

    public function test_tidak_ditemukan_error_dan_tidak_membuat_anak(): void
    {
        $hasil = (new PenautanAnakKesmas())->cari('Anak Baru', 'L', '2023-01-01', 'Ibu', '');

        $this->assertNull($hasil['anak']);
        $this->assertSame(['ERROR'], $this->tingkat($hasil));
        $this->assertStringContainsString('tidak ditemukan', $hasil['pesan'][0][1]);
        $this->assertSame(0, Anak::count());
    }

    public function test_nama_ortu_berbeda_menyingkirkan_kandidat(): void
    {
        $this->anak(['nama' => 'MUHAMMAD ALFIAN RAMADHAN', 'nik' => '6474011903240001', 'tgl_lahir' => '2024-03-19', 'nama_ibu' => 'ARISDA']);

        $hasil = (new PenautanAnakKesmas())->cari('Muhammad Aydan Ramadhan', 'L', '2024-03-19', 'BUNGA MELATI', '');

        $this->assertNull($hasil['anak']);
        $this->assertSame(['ERROR'], $this->tingkat($hasil));
    }

    public function test_kembar_tanpa_nik_ambigu_bukan_ditebak(): void
    {
        $this->anak(['nama' => 'Muhammad Zayyan Al Fatih', 'nik' => '6474011204260002', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']);
        $this->anak(['nama' => 'Muhammad Rayyan Al Fatih', 'nik' => '6474011204260001', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']);

        $hasil = (new PenautanAnakKesmas())->cari('Muhammad Rayyan Al Fatih', 'L', '2026-04-12', 'ALFIANA', '');

        $this->assertNull($hasil['anak']);
        $this->assertStringContainsString('isi nik_anak', $hasil['pesan'][0][1]);
    }

    public function test_kembar_dengan_nik_tepat_sasaran(): void
    {
        $this->anak(['nama' => 'Muhammad Zayyan Al Fatih', 'nik' => '6474011204260002', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']);
        $rayyan = $this->anak(['nama' => 'Muhammad Rayyan Al Fatih', 'nik' => '6474011204260001', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']);

        $hasil = (new PenautanAnakKesmas())->cari('Muhammad Rayyan Al Fatih', 'L', '2026-04-12', 'ALFIANA', '6474011204260001');

        $this->assertSame($rayyan->id, $hasil['anak']->id);
    }
}
