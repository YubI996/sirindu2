<?php

namespace Tests\Feature\Imports;

use App\Imports\Pd3iImport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\SurveillanceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Perilaku No. Epid (no_registrasi) duplikat saat import.
 *
 * Upsert `updateOrCreate(['no_registrasi' => …])` SENGAJA dipertahankan: re-import
 * file yang sama harus idempoten (lihat perlindungan status confirmed dari
 * HasilLabImport). Yang berbahaya adalah kasus DIAM-DIAM tertimpa ketika nomor
 * epid yang sama ternyata milik pasien BERBEDA — itu harus dilaporkan ke petugas.
 */
class Pd3iImportEpidTest extends TestCase
{
    use DatabaseTransactions;

    private User $petugas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->petugas = User::factory()->create(['type' => 0]);
        $kecamatan     = Kecamatan::factory()->create(['name' => 'Bontang Utara']);
        Kelurahan::factory()->create([
            'name'         => 'Api-Api',
            'id_kecamatan' => $kecamatan->id,
        ]);
        JenisKasusEpidemiologi::factory()->create(['nama_penyakit' => 'Campak-Rubella']);
    }

    private function row(string $noReg, string $nama, string $nik): array
    {
        $row = array_fill(0, 200, null);

        $row[0]  = $noReg;
        $row[2]  = '2026-05-10';
        $row[3]  = 'Petugas Uji';
        $row[4]  = 'Bontang Utara 2';
        $row[8]  = $nik;
        $row[9]  = $nama;
        $row[10] = 'L';
        $row[11] = '2020-03-01';
        $row[15] = 'Jl. Uji No. 1';
        $row[18] = 'Bontang Utara';
        $row[19] = 'Api-Api';
        $row[22] = '2026-05-01';
        $row[23] = 'Campak-Rubella';

        return $row;
    }

    /** @return string[] pesan hasil import */
    private function import(array $rows): array
    {
        $import = new Pd3iImport($this->petugas->id);
        $import->collection(collect($rows));

        return $import->getResults()['failures'];
    }

    public function test_epid_sama_pasien_berbeda_memicu_peringatan(): void
    {
        $this->import([$this->row('C-171026900', 'Budi Santoso', '6474011234560001')]);

        $pesan = $this->import([$this->row('C-171026900', 'Siti Aminah', '6474011234560002')]);

        $peringatan = array_filter(
            $pesan,
            fn($m) => str_contains($m, 'C-171026900') && str_contains($m, 'pasien lain')
        );

        $this->assertNotEmpty(
            $peringatan,
            'EPID yang dipakai pasien berbeda harus memicu peringatan, bukan menimpa diam-diam.'
        );
    }

    public function test_reimport_pasien_sama_tidak_memicu_peringatan(): void
    {
        // Re-import file yang sama = alur normal & idempoten → jangan spam peringatan.
        $this->import([$this->row('C-171026901', 'Budi Santoso', '6474011234560001')]);

        $pesan = $this->import([$this->row('C-171026901', 'Budi Santoso', '6474011234560001')]);

        $peringatan = array_filter($pesan, fn($m) => str_contains($m, 'pasien lain'));

        $this->assertEmpty($peringatan, 'Re-import pasien yang sama tidak boleh dianggap tabrakan.');
        $this->assertSame(1, SurveillanceCase::where('no_registrasi', 'C-171026901')->count());
    }

    public function test_upsert_tetap_idempoten(): void
    {
        // Perilaku upsert dipertahankan: satu no_registrasi = satu baris.
        $this->import([$this->row('C-171026902', 'Budi Santoso', '6474011234560001')]);
        $this->import([$this->row('C-171026902', 'Budi Santoso', '6474011234560001')]);

        $this->assertSame(1, SurveillanceCase::where('no_registrasi', 'C-171026902')->count());
    }
}
