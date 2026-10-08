<?php

namespace Tests\Feature;

use App\Imports\AnakImport;
use App\Models\Anak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencocok anak (nama ≥87% + tgl lahir + jk) dulu menggabungkan anak kembar yang namanya mirip
 * (Zayyan/Rayyan Al Fatih) dan anak tak bersaudara yang kebetulan lahir di hari yang sama
 * (Alfian/Aydan Ramadhan, ibu beda) — 5 anak hilang dari satu berkas import Okt 2026.
 *
 * Bukan anak yang sama bila:
 *  - NIK berkas & NIK di DB sama-sama 16 digit asli, 12 digit awal (wilayah + tgl lahir) sama, nomor urut
 *    beda, dan nama berbeda -> pola NIK kembar dari Dukcapil; atau
 *  - nama ibu di berkas & di DB sama-sama terisi dan berbeda.
 * Nama sama persis dengan nomor urut beda tetap dianggap salah ketik (digabung).
 */
class ImportAnakKembarTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['nik', 'nama', 'tgl_lahir', 'jk', 'nama_ibu'];

    private function impor(array $baris): array
    {
        $i = new AnakImport(1);
        $i->collection(collect(array_merge([self::HEADER], $baris)));

        return $i->getResults();
    }

    public function test_anak_kembar_dalam_satu_berkas_jadi_dua_anak(): void
    {
        $hasil = $this->impor([
            ['6474011204260002', 'Muhammad zayyan Al Fatih', '2026-04-12', 'L', 'ALFIANA'],
            ['6474011204260001', 'Muhammad Rayyan Al Fatih', '2026-04-12', 'L', 'ALFIANA'],
        ]);

        $this->assertSame(2, Anak::count(), implode("\n", $hasil['failures']));
        $this->assertTrue(Anak::where('nik', '6474011204260001')->where('nama', 'Muhammad Rayyan Al Fatih')->exists());
        $this->assertStringNotContainsString('periksa salah ketik', implode("\n", $hasil['failures']));
    }

    public function test_anak_kembar_tanpa_nama_ibu_tetap_dua_anak(): void
    {
        $this->impor([
            ['6474012010230001', 'Al Ghazali', '2023-10-20', 'L', null],
            ['6474012010230002', 'El Ghazali', '2023-10-20', 'L', null],
        ]);

        $this->assertSame(2, Anak::count());
    }

    public function test_nama_ibu_berbeda_bukan_anak_yang_sama(): void
    {
        $hasil = $this->impor([
            ['6474011903240001', 'MUHAMMAD ALFIAN RAMADHAN', '2024-03-19', 'L', 'ARISDA'],
            ['', 'Muhammad Aydan Ramadhan', '2024-03-19', 'L', 'BUNGA MELATI'],
        ]);

        $this->assertSame(2, Anak::count(), implode("\n", $hasil['failures']));
    }

    public function test_nomor_urut_beda_tapi_nama_sama_tetap_digabung_sebagai_salah_ketik(): void
    {
        $hasil = $this->impor([
            ['6474011204260001', 'MUHAMMAD RAYYAN', '2026-04-12', 'L', 'ALFIANA'],
            ['6474011204260002', 'Muhammad Rayyan', '2026-04-12', 'L', 'ALFIANA'],
        ]);

        $this->assertSame(1, Anak::count());
        $this->assertStringContainsString('periksa salah ketik', implode("\n", $hasil['failures']));
    }

    public function test_ejaan_beda_ibu_sama_tanpa_nik_tetap_digabung(): void
    {
        $this->impor([
            ['7315125606220001', 'PUTRI NAFISAH MUTMAINNAH', '2022-06-16', 'P', 'Siti Aminah'],
            ['', 'PUTRI NAFISAH MUTHMAINNAH', '2022-06-16', 'P', 'SITI AMINAH'],
        ]);

        $this->assertSame(1, Anak::count());
    }

    public function test_nama_ibu_kosong_di_salah_satu_sisi_tidak_memisahkan(): void
    {
        $this->impor([
            ['7315125606220001', 'PUTRI NAFISAH MUTMAINNAH', '2022-06-16', 'P', null],
            ['', 'PUTRI NAFISAH MUTHMAINNAH', '2022-06-16', 'P', 'SITI AMINAH'],
        ]);

        $this->assertSame(1, Anak::count());
    }

    public function test_nama_ibu_yang_memuat_nama_ayah_tidak_memisahkan(): void
    {
        // Berkas asli baris 2348/2349: RT & posyandu sama, satu baris menulis "ayah / ibu".
        $this->impor([
            ['6474200103203723', 'MUHAMMAD HENRIANSYA', '2020-03-01', 'L', 'AISYAH'],
            ['6474200103206107', 'MUHAMMAD HERIANSYAH', '2020-03-01', 'L', 'MUHERMI / AISYA'],
        ]);

        $this->assertSame(1, Anak::count());
    }

    public function test_nama_ibu_yang_ditulis_terpotong_tidak_memisahkan(): void
    {
        $this->impor([
            ['6474013003200002', 'MUHAMMAD AFIF FARZAN', '2020-03-30', 'L', 'FEBRIYANTI'],
            ['', 'MUHAMMAD AFIF FARZAN', '2020-03-30', 'L', 'FEBRI / ANTI'],
        ]);

        $this->assertSame(1, Anak::count());
    }

    public function test_nama_ibu_isian_asal_dianggap_kosong(): void
    {
        $this->impor([
            ['6474011115200001', 'Al Ghifari Ramadhan', '2020-05-11', 'L', 'Martina'],
            ['6474011105200001', 'AL GHIFARI RAMADHAN', '2020-05-11', 'L', 'ADA'],
        ]);

        $this->assertSame(1, Anak::count());
    }

    public function test_kembar_tidak_dihitung_sebagai_kandidat_ambigu(): void
    {
        // Kembar tiga: baris ketiga dulu melihat 2 kandidat -> ERROR "Ditemukan 2 anak", tidak tersimpan.
        $hasil = $this->impor([
            ['6474011204260001', 'Muhammad Rayyan Al Fatih', '2026-04-12', 'L', 'ALFIANA'],
            ['6474011204260002', 'Muhammad Zayyan Al Fatih', '2026-04-12', 'L', 'ALFIANA'],
            ['6474011204260003', 'Muhammad Fayyan Al Fatih', '2026-04-12', 'L', 'ALFIANA'],
        ]);

        $this->assertSame(3, Anak::count(), implode("\n", $hasil['failures']));
    }

    public function test_penautan_dipakai_kohort_dengan_nama_ibu(): void
    {
        Anak::create([
            'nik' => '6474011903240001', 'nama' => 'MUHAMMAD ALFIAN RAMADHAN', 'jk' => 1,
            'tgl_lahir' => '2024-03-19', 'nama_ibu' => 'ARISDA', 'no' => 'REG-1', 'status' => 1, 'sumber' => 'manual',
        ]);

        $tautan = app(\App\Services\PenautanAnakImport::class)
            ->tautkan('Muhammad Aydan Ramadhan', '2024-03-19', 'L', '', null, 'BUNGA MELATI');

        $this->assertSame(\App\Services\PenautanAnakImport::BARU, $tautan['hasil']);
    }
}
