<?php

namespace Tests\Feature;

use App\Imports\AnakImport;
use App\Models\Anak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AnakImport, jalur "NIK valid yang SUDAH ada" (updateOrCreate by NIK).
 *
 * Dulu seluruh $data ditulis apa adanya: sel kosong menimpa data lama dengan NULL, `no` anak lama diganti
 * nomor IMP- otomatis, dan `status` direset ke 1. Di prod ini terbaca dari awalan IMP-202610 yang jumlahnya
 * jauh melebihi anak yang benar-benar dibuat import (±3.000 anak Operasi Timbang ikut berawalan IMP-).
 * Jalur tautan nama+tgl lahir sudah memakai aturan "isi yang diberikan" (MenautkanAnakImport); jalur NIK
 * kini sama, kecuali identitas (nama/tgl lahir/jk) yang tetap boleh dikoreksi berkas karena NIK adalah kunci kuat.
 *
 * Anak yang dibuat import bertanda sumber `import_anak`, supaya bisa dibedakan dari input tangan ('manual')
 * — sebelumnya keduanya 'manual'.
 */
class ImportAnakNikAdaTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = '6474011005200001';

    private function anakLama(array $override = []): Anak
    {
        return Anak::create(array_merge([
            'nik' => self::NIK, 'nama' => 'Ani Wijaya', 'jk' => 2, 'tgl_lahir' => '2021-03-10',
            'no' => 'REG-1', 'status' => 1, 'sumber' => 'operasi_timbang',
            'nik_ibu' => 'IBU ASLI', 'alamat' => 'JL LAMA', 'id_kec' => 5, 'id_kel' => 9,
        ], $override));
    }

    private function impor(array $rows): array
    {
        $import = new AnakImport(1);
        $import->collection(collect($rows));

        return $import->getResults();
    }

    public function test_nik_yang_sudah_ada_tidak_dikosongkan_oleh_sel_kosong(): void
    {
        $this->anakLama();

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'alamat'],
            [self::NIK, 'Ani Wijaya', '2021-03-10', 'P', 'JL BARU'],
        ]);

        $anak = Anak::firstOrFail();
        $this->assertSame('JL BARU', $anak->alamat, 'kolom yang diberikan berkas ikut terisi');
        $this->assertSame('IBU ASLI', $anak->nik_ibu, 'kolom yang tak ada di berkas tidak boleh jadi NULL');
        $this->assertSame(5, (int) $anak->id_kec, 'wilayah yang tak diberikan tidak boleh dikosongkan');
        $this->assertSame(9, (int) $anak->id_kel);
    }

    public function test_sel_kosong_pada_kolom_yang_ada_di_berkas_juga_tidak_mengosongkan(): void
    {
        $this->anakLama();

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'alamat', 'nik_ibu'],
            [self::NIK, 'Ani Wijaya', '2021-03-10', 'P', '', ''],
        ]);

        $anak = Anak::firstOrFail();
        $this->assertSame('JL LAMA', $anak->alamat);
        $this->assertSame('IBU ASLI', $anak->nik_ibu);
    }

    public function test_nomor_registrasi_status_dan_sumber_anak_lama_tidak_berubah(): void
    {
        $this->anakLama(['status' => 0]);

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            [self::NIK, 'Ani Wijaya', '2021-03-10', 'P'],
        ]);

        $anak = Anak::firstOrFail();
        $this->assertSame('REG-1', $anak->no, 'nomor registrasi lama tidak boleh diganti nomor IMP- otomatis');
        $this->assertSame(0, (int) $anak->status, 'status tidak boleh direset ke default 1');
        $this->assertSame('operasi_timbang', $anak->sumber, 'anak lama bukan anak import, sumbernya tak berubah');
    }

    public function test_nomor_registrasi_dan_status_yang_DIISI_berkas_tetap_dipakai(): void
    {
        $this->anakLama();

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk', 'no_registrasi', 'status'],
            [self::NIK, 'Ani Wijaya', '2021-03-10', 'P', 'REG-9', '0'],
        ]);

        $anak = Anak::firstOrFail();
        $this->assertSame('REG-9', $anak->no);
        $this->assertSame(0, (int) $anak->status);
    }

    public function test_identitas_anak_ber_nik_yang_sama_tetap_dikoreksi_berkas(): void
    {
        $this->anakLama();

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            [self::NIK, 'ANI WIJAYA PUTRI', '2021-03-11', 'P'],
        ]);

        $anak = Anak::firstOrFail();
        $this->assertSame('ANI WIJAYA PUTRI', $anak->nama);
        $this->assertSame('2021-03-11', substr((string) $anak->tgl_lahir, 0, 10));
    }

    public function test_anak_baru_dari_import_bersumber_import_anak(): void
    {
        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            ['6474011005200009', 'BUDI BARU', '2022-01-01', 'L'],
            ['', 'CICI TANPA NIK', '2022-02-02', 'P'],
        ]);

        $this->assertSame(2, Anak::count());
        $this->assertSame(['import_anak', 'import_anak'], Anak::orderBy('id')->pluck('sumber')->all());
    }

    public function test_anak_lama_yang_ditautkan_lewat_nama_tidak_berubah_sumber(): void
    {
        $this->anakLama();

        $this->impor([
            ['nik', 'nama', 'tgl_lahir', 'jk'],
            ['', 'ANI WIJAYA', '2021-03-10', 'P'],
        ]);

        $this->assertSame(1, Anak::count());
        $this->assertSame('operasi_timbang', Anak::firstOrFail()->sumber);
    }

    public function test_import_ulang_berkas_yang_sama_tidak_mengubah_apa_pun(): void
    {
        $rows = [
            ['nik', 'nama', 'tgl_lahir', 'jk', 'alamat'],
            ['6474011005200009', 'BUDI BARU', '2022-01-01', 'L', 'JL A'],
        ];
        $this->impor($rows);
        $awal = Anak::firstOrFail()->only(['id', 'no', 'status', 'sumber', 'alamat']);

        $this->impor($rows);

        $this->assertSame(1, Anak::count());
        $this->assertSame($awal, Anak::firstOrFail()->only(['id', 'no', 'status', 'sumber', 'alamat']));
    }
}
