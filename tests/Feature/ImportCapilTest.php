<?php

namespace Tests\Feature;

use App\Imports\CapilImport;
use App\Models\Anak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportCapilTest extends TestCase
{
    use RefreshDatabase;

    /** Header Capil standar untuk fixture in-memory. */
    private function header(): array
    {
        return [
            'NIK', 'NAMA LENGKAP', 'JENIS KLMIN', 'TGL LHR',
            'USIA BLN PER 31-05-2026', 'NO KK', 'NAMA LENGKAP IBU',
            'NAMA LENGKAP AYAH', 'ALAMAT', 'NO RT', 'NO KEL',
            'NAMA KELURAHAN', 'NO KEC', 'NAMA KECAMATAN',
        ];
    }

    public function test_baris_tanpa_padanan_membuat_anak_baru(): void
    {
        $row = [
            '3274010101200001', 'BUDI SANTOSO', 'LAKI-LAKI', '2020-01-15',
            '77', '3274999999999999', 'SITI AMINAH', 'JOKO WIDODO',
            'JL MAWAR NO 5', '003', '01', 'BERBAS PANTAI', '02', 'BONTANG SELATAN',
        ];

        $import = new CapilImport(1, '2026-06-25');
        $import->collection(collect([$this->header(), $row]));

        $anak = Anak::where('nik', '3274010101200001')->first();
        $this->assertNotNull($anak);
        $this->assertEquals('BUDI SANTOSO', $anak->nama);
        $this->assertEquals(1, $anak->jk);
        $this->assertEquals('2020-01-15', (string) $anak->tgl_lahir);
        $this->assertEquals('SITI AMINAH', $anak->nama_ibu);
        $this->assertEquals(
            'JL MAWAR NO 5, RT 003, Kel. BERBAS PANTAI, Kec. BONTANG SELATAN',
            $anak->alamat_ktp
        );
        $this->assertNull($anak->id_kec);
        $this->assertNull($anak->alamat);
        $this->assertStringContainsString('Impor Capil 2026-06-25', $anak->catatan);

        $results = $import->getResults();
        $this->assertEquals(1, $results['created']);
        $this->assertEquals(0, $results['updated']);
    }

    public function test_match_memperbarui_kependudukan_tanpa_menyentuh_kesehatan(): void
    {
        $existing = Anak::create([
            'nik'         => '9999999999999999', // NIK dummy sigizi
            'nama'        => 'BUDI SANTOSO',
            'jk'          => 1,
            'tgl_lahir'   => '2020-01-15',
            'no'          => 'REG-1',
            'status'      => 1,
            'alamat'      => 'Jl Domisili Sigizi',
            'id_kec'      => 5,
            'id_posyandu' => 3,
            'catatan'     => 'Catatan manual petugas',
            'bbl'         => 3.2,
        ]);

        $row = [
            '3274010101200001', 'BUDI SANTOSO', 'LAKI-LAKI', '2020-01-15',
            '77', '3274KK', 'SITI AMINAH', 'JOKO WIDODO',
            'JL MAWAR', '003', '01', 'BERBAS PANTAI', '02', 'BONTANG SELATAN',
        ];

        $import = new CapilImport(1, '2026-06-25');
        $import->collection(collect([$this->header(), $row]));

        $existing->refresh();
        // Kependudukan ikut Capil:
        $this->assertEquals('3274010101200001', $existing->nik);
        $this->assertEquals('SITI AMINAH', $existing->nama_ibu);
        $this->assertStringContainsString('JL MAWAR', $existing->alamat_ktp);
        // Kesehatan & domisili & catatan TIDAK berubah:
        $this->assertEquals('Jl Domisili Sigizi', $existing->alamat);
        $this->assertEquals(5, $existing->id_kec);
        $this->assertEquals(3, $existing->id_posyandu);
        $this->assertEquals('Catatan manual petugas', $existing->catatan);
        $this->assertEquals(3.2, (float) $existing->bbl);
        // Tidak ada baris baru:
        $this->assertEquals(1, Anak::count());

        $this->assertEquals(1, $import->getResults()['updated']);
    }
}
