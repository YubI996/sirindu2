<?php

namespace Tests\Feature\Imports;

use App\Imports\AnakImport;
use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AnakImport (jalur import Capil) punya resolveFaskes() dengan cacat yang
 * PERSIS SAMA dengan OtFinalRegistriImport sebelum diperbaiki: mencocokkan
 * nama apa adanya lalu jatuh ke LIKE '%nama%' GLOBAL.
 *
 * Akibatnya sama: master ditulis Romawi ("Sejahtera II"), berkas memakai Arab
 * ("SEJAHTERA 2") → id_posyandu NULL; dan nama yang cuma terkandung sebagian
 * ("EDELWEIS") menempel diam-diam ke posyandu lain ("Griya Edelweis").
 *
 * Lihat App\Services\FaskesMatcher dan
 * tests/Feature/Imports/OtImportPosyanduTest.php.
 */
class AnakImportPosyanduTest extends TestCase
{
    use RefreshDatabase;

    protected Puskesmas $barat;
    protected Puskesmas $utara;

    protected function setUp(): void
    {
        parent::setUp();

        $kec = Kecamatan::create(['name' => 'Bontang Barat']);
        Kelurahan::create(['name' => 'Gunung Telihan', 'id_kecamatan' => $kec->id]);
        $this->barat = Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $kec->id]);
        $this->utara = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
    }

    protected function header(): array
    {
        return [
            'nik', 'nama', 'jk', 'tgl_lahir',
            'nama_kecamatan', 'nama_kelurahan', 'nama_puskesmas', 'nama_posyandu',
        ];
    }

    protected function baris(string $posyandu, string $puskesmas = 'Bontang Barat'): array
    {
        return [
            '6474025209250001', 'FARAH NUR SEPTIANA PUTRI', 'P', '2025-09-12',
            'Bontang Barat', 'Gunung Telihan', $puskesmas, $posyandu,
        ];
    }

    protected function imporSatuBaris(string $posyandu, string $puskesmas = 'Bontang Barat'): ?Anak
    {
        $import = new AnakImport(userId: 1);
        $import->collection(collect([$this->header(), $this->baris($posyandu, $puskesmas)]));

        return Anak::where('nik', '6474025209250001')->first();
    }

    public function test_posyandu_romawi_di_master_cocok_dengan_angka_arab_di_berkas(): void
    {
        $pos = Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);

        $anak = $this->imporSatuBaris('SEJAHTERA 2');

        $this->assertNotNull($anak, 'Baris gagal diimpor.');
        $this->assertSame($pos->id, (int) $anak->id_posyandu);
    }

    public function test_puskesmas_romawi_di_berkas_cocok_dengan_angka_arab_di_master(): void
    {
        $anak = $this->imporSatuBaris('Apa Saja', 'BONTANG UTARA I');

        $this->assertNotNull($anak);
        $this->assertSame($this->utara->id, (int) $anak->id_puskesmas);
    }

    public function test_posyandu_kembar_dipilih_sesuai_puskesmas_baris(): void
    {
        $mawarBarat = Posyandu::create(['name' => 'Mawar', 'id_puskesmas' => $this->barat->id]);
        $mawarUtara = Posyandu::create(['name' => 'Mawar', 'id_puskesmas' => $this->utara->id]);

        $this->assertSame($mawarUtara->id, (int) $this->imporSatuBaris('MAWAR', 'Bontang Utara I')->id_posyandu);

        Anak::query()->delete();

        $this->assertSame($mawarBarat->id, (int) $this->imporSatuBaris('MAWAR', 'Bontang Barat')->id_posyandu);
    }

    public function test_nama_yang_hanya_terkandung_sebagian_tidak_lagi_dicomot(): void
    {
        Posyandu::create(['name' => 'Griya Edelweis', 'id_puskesmas' => $this->barat->id]);

        $anak = $this->imporSatuBaris('EDELWEIS');

        $this->assertNotNull($anak);
        $this->assertNull($anak->id_posyandu);
    }

    public function test_posyandu_tak_dikenal_tetap_null_dan_tidak_membuat_master_baru(): void
    {
        $anak = $this->imporSatuBaris('POSYANDU ANTAH BERANTAH');

        $this->assertNotNull($anak);
        $this->assertNull($anak->id_posyandu);
        $this->assertSame(0, Posyandu::count());
    }
}
