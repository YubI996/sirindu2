<?php

namespace Tests\Feature\Imports;

use App\Imports\OtFinalRegistriImport;
use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Import OT harus memakai FaskesMatcher (normalisasi + scoping puskesmas),
 * bukan lagi exact-nama + LIKE '%nama%' global.
 *
 * Lihat App\Services\FaskesMatcher untuk latar temuannya.
 */
class OtImportPosyanduTest extends TestCase
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
            'Nama Anak', 'Jenis Kelamin', 'Tanggal Lahir', 'NIK',
            'Kecamatan', 'Puskesmas', 'Kelurahan', 'Posyandu',
            'Tanggal Pengukuran', 'Berat (kg)', 'Tinggi (cm)',
        ];
    }

    protected function baris(string $posyandu, string $puskesmas = 'Bontang Barat'): array
    {
        return [
            'FARAH NUR SEPTIANA PUTRI', 'P', '2025-09-12', '6474025209250001',
            'Bontang Barat', $puskesmas, 'Gunung Telihan', $posyandu,
            '2026-06-11', '7.02', '68',
        ];
    }

    protected function imporSatuBaris(string $posyandu, string $puskesmas = 'Bontang Barat'): ?Anak
    {
        $import = new OtFinalRegistriImport(userId: 1, commit: true);
        $import->collection(collect([$this->header(), $this->baris($posyandu, $puskesmas)]));

        return Anak::where('nik', '6474025209250001')->first();
    }

    public function test_posyandu_romawi_di_master_cocok_dengan_angka_arab_di_berkas(): void
    {
        $pos = Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);

        $anak = $this->imporSatuBaris('SEJAHTERA 2');

        $this->assertSame($pos->id, (int) $anak->id_posyandu);
    }

    public function test_posyandu_bernama_kembar_dipilih_sesuai_puskesmas_baris(): void
    {
        $mawarBarat = Posyandu::create(['name' => 'Mawar', 'id_puskesmas' => $this->barat->id]);
        $mawarUtara = Posyandu::create(['name' => 'Mawar', 'id_puskesmas' => $this->utara->id]);

        $this->assertSame($mawarUtara->id, (int) $this->imporSatuBaris('MAWAR', 'Bontang Utara I')->id_posyandu);

        Anak::query()->delete();

        $this->assertSame($mawarBarat->id, (int) $this->imporSatuBaris('MAWAR', 'Bontang Barat')->id_posyandu);
    }

    public function test_nama_yang_hanya_terkandung_sebagian_tidak_lagi_dicomot(): void
    {
        // Perilaku LAMA: LIKE '%EDELWEIS%' menempelkan anak ke "Griya Edelweis".
        Posyandu::create(['name' => 'Griya Edelweis', 'id_puskesmas' => $this->barat->id]);

        $anak = $this->imporSatuBaris('EDELWEIS');

        $this->assertNull($anak->id_posyandu);
    }

    public function test_posyandu_tak_dikenal_tetap_null_dan_tidak_membuat_master_baru(): void
    {
        $anak = $this->imporSatuBaris('POSYANDU ANTAH BERANTAH');

        $this->assertNull($anak->id_posyandu);
        $this->assertSame(0, Posyandu::count());
    }
}
