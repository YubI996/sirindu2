<?php

namespace Tests\Feature\Imports;

use App\Imports\Pd3iImport;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\RumahSakit;
use App\Models\SurveillanceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression: Pd3iImport WAJIB mengisi faskes_type='rs' + id_faskes dari
 * instansi_pelapor.
 *
 * Tanpa itu, kasus hasil import punya faskes_type NULL dan user surveilans_rs
 * tak pernah melihatnya — SurveillanceCase::scopeVisibleTo menyaring RS murni
 * lewat faskes_type='rs' + id_faskes, tanpa fallback wilayah seperti puskesmas.
 */
class Pd3iImportFaskesRsTest extends TestCase
{
    use DatabaseTransactions;

    private User $petugas;
    private Kecamatan $kecamatan;
    private Kelurahan $kelurahan;
    private RumahSakit $rs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->petugas   = User::factory()->create(['type' => 0]);
        $this->kecamatan = Kecamatan::factory()->create(['name' => 'Bontang Utara']);
        $this->kelurahan = Kelurahan::factory()->create([
            'name'         => 'Api-Api',
            'id_kecamatan' => $this->kecamatan->id,
        ]);
        JenisKasusEpidemiologi::factory()->create(['nama_penyakit' => 'Campak-Rubella']);

        $this->rs = RumahSakit::create([
            'id_kecamatan' => $this->kecamatan->id,
            'name'         => 'RS Badak LNG',
            'kode_rs'      => 'RS-TEST-01',
            'jenis_rs'     => 'RS Swasta',
            'is_active'    => true,
        ]);
    }

    /**
     * Bangun satu baris Excel PD3I; hanya kolom yang relevan yang diisi.
     */
    private function row(string $instansiPelapor, string $noReg): array
    {
        $row = array_fill(0, 200, null);

        $row[0]  = $noReg;                 // No. Registrasi (kunci upsert)
        $row[2]  = '2026-05-10';           // Timestamp → tanggal_lapor
        $row[3]  = 'Petugas Uji';          // nama_pelapor
        $row[4]  = $instansiPelapor;       // instansi_pelapor ← kolom yang diuji
        $row[8]  = '6474011234560001';     // NIK
        $row[9]  = 'Pasien Uji';           // nama_lengkap
        $row[10] = 'L';                    // jenis_kelamin
        $row[11] = '2020-03-01';           // tanggal_lahir
        $row[15] = 'Jl. Uji No. 1';        // alamat_lengkap
        $row[18] = 'Bontang Utara';        // kecamatan
        $row[19] = 'Api-Api';              // kelurahan
        $row[22] = '2026-05-01';           // tanggal_onset
        $row[23] = 'Campak-Rubella';       // jenis kasus

        return $row;
    }

    private function import(array $rows): void
    {
        (new Pd3iImport($this->petugas->id))->collection(collect($rows));
    }

    public function test_pelapor_rs_diatribusikan_ke_faskes_rs(): void
    {
        $this->import([$this->row('RS Badak LNG', 'UJI-RS-001')]);

        $case = SurveillanceCase::where('no_registrasi', 'UJI-RS-001')->first();

        $this->assertNotNull($case, 'Baris import seharusnya tersimpan.');
        $this->assertSame('rs', $case->faskes_type);
        $this->assertSame($this->rs->id, $case->id_faskes);
    }

    public function test_urutan_token_nama_rs_tetap_cocok(): void
    {
        // Data lapangan menulis "RS LNG Badak", master "RS Badak LNG"
        $this->import([$this->row('RS LNG Badak', 'UJI-RS-002')]);

        $case = SurveillanceCase::where('no_registrasi', 'UJI-RS-002')->first();

        $this->assertSame('rs', $case->faskes_type);
        $this->assertSame($this->rs->id, $case->id_faskes);
    }

    public function test_user_rs_bisa_melihat_kasus_hasil_import(): void
    {
        $this->import([$this->row('RS Badak LNG', 'UJI-RS-003')]);

        $userRs = User::factory()->create([
            'type'        => 1,
            'role'        => 'surveilans_rs',
            'faskes_type' => 'rs',
            'id_rs'       => $this->rs->id,
        ]);

        $terlihat = SurveillanceCase::query()
            ->visibleTo($userRs)
            ->where('no_registrasi', 'UJI-RS-003')
            ->exists();

        $this->assertTrue($terlihat, 'User RS harus bisa melihat kasus hasil import dari RS-nya.');
    }

    public function test_pelapor_puskesmas_tidak_diatribusikan_ke_rs(): void
    {
        // Pelapor puskesmas bukan RS → faskes_type tetap null,
        // kasus terlihat Dinkes & puskesmas via wilayah (id_kel).
        $this->import([$this->row('Bontang Utara 2', 'UJI-PKM-001')]);

        $case = SurveillanceCase::where('no_registrasi', 'UJI-PKM-001')->first();

        $this->assertNotNull($case);
        $this->assertNull($case->faskes_type);
        $this->assertNull($case->id_faskes);
    }

    public function test_rs_tak_dikenal_tidak_tertaut_ke_rs_lain(): void
    {
        // False-positive di sini = satu RS melihat data RS lain. Harus null.
        $this->import([$this->row('RS Entah Apa', 'UJI-RS-004')]);

        $case = SurveillanceCase::where('no_registrasi', 'UJI-RS-004')->first();

        $this->assertNotNull($case);
        $this->assertNull($case->faskes_type);
        $this->assertNull($case->id_faskes);
    }
}
