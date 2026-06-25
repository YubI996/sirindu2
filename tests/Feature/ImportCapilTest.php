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

    public function test_sel_capil_kosong_tidak_menimpa_sigizi(): void
    {
        $existing = Anak::create([
            'nik' => '9999999999999999', 'nama' => 'ANI WIJAYA', 'jk' => 2,
            'tgl_lahir' => '2021-03-10', 'no' => 'REG-2', 'status' => 1,
            'nama_ayah' => 'AYAH SIGIZI',
        ]);

        // NAMA LENGKAP AYAH dikosongkan
        $row = [
            '3274010101210002', 'ANI WIJAYA', 'PEREMPUAN', '2021-03-10',
            '62', '3274KK', 'IBU CAPIL', '', 'JL ANGGREK', '004', '01',
            'GUNUNG ELAI', '01', 'BONTANG UTARA',
        ];

        (new CapilImport(1, '2026-06-25'))->collection(collect([$this->header(), $row]));

        $existing->refresh();
        $this->assertEquals('AYAH SIGIZI', $existing->nama_ayah);  // tidak ditimpa kosong
        $this->assertEquals('IBU CAPIL', $existing->nama_ibu);     // ditimpa karena terisi
    }

    public function test_dua_anak_nama_tgl_sama_dilewati_sebagai_ambigu(): void
    {
        Anak::create(['nik' => '1111111111111111', 'nama' => 'RANI PUTRI', 'jk' => 2, 'tgl_lahir' => '2022-02-02', 'no' => 'R1', 'status' => 1]);
        Anak::create(['nik' => '2222222222222222', 'nama' => 'RANI PUTRI', 'jk' => 2, 'tgl_lahir' => '2022-02-02', 'no' => 'R2', 'status' => 1]);

        $row = [
            '3274010101220003', 'RANI PUTRI', 'PEREMPUAN', '2022-02-02',
            '52', '3274KK', 'IBU', 'AYAH', 'JL X', '001', '01', 'KEL', '01', 'KEC',
        ];

        $import = new CapilImport(1, '2026-06-25');
        $import->collection(collect([$this->header(), $row]));

        $this->assertEquals(2, Anak::count());                          // tidak buat baris baru
        $this->assertEquals(0, $import->getResults()['created']);
        $this->assertEquals(1, $import->getResults()['error_count']);

        $hasWarning = collect($import->getResults()['failures'])
            ->contains(fn ($f) => str_contains($f, '[PERINGATAN]') && str_contains($f, 'RANI PUTRI'));
        $this->assertTrue($hasWarning);
    }

    public function test_tgl_lahir_kosong_diestimasi_dari_usia(): void
    {
        // USIA BLN PER 31-05-2026 = 12 → lahir Mei 2025 (awal bulan)
        $row = [
            '3274010101250004', 'DEWI LESTARI', 'PEREMPUAN', '',
            '12', '3274KK', 'IBU', 'AYAH', 'JL Y', '002', '01', 'KEL', '01', 'KEC',
        ];

        (new CapilImport(1, '2026-06-25'))->collection(collect([$this->header(), $row]));

        $anak = Anak::where('nik', '3274010101250004')->first();
        $this->assertNotNull($anak);
        $this->assertEquals('2025-05-01', (string) $anak->tgl_lahir);
    }

    public function test_nik_capil_otoritatif_memperbaiki_nama_via_nik_exact(): void
    {
        // Anak existing sudah punya NIK Capil-benar tapi nama salah ketik
        $existing = Anak::create([
            'nik' => '3274010101200001', 'nama' => 'BUDl SANTOSA', 'jk' => 1,
            'tgl_lahir' => '2020-01-15', 'no' => 'REG-9', 'status' => 1,
        ]);

        $row = [
            '3274010101200001', 'BUDI SANTOSO', 'LAKI-LAKI', '2020-01-15',
            '77', '3274KK', 'SITI', 'JOKO', 'JL Z', '001', '01', 'KEL', '01', 'KEC',
        ];

        $import = new CapilImport(1, '2026-06-25');
        $import->collection(collect([$this->header(), $row]));

        $existing->refresh();
        $this->assertEquals('BUDI SANTOSO', $existing->nama);   // diperbaiki dari Capil
        $this->assertEquals(1, Anak::count());                  // tidak duplikat
        $this->assertEquals(1, $import->getResults()['updated']);
    }
}
