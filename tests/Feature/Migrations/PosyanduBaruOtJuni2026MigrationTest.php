<?php

namespace Tests\Feature\Migrations;

use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enam posyandu punya anak di berkas Operasi Timbang Juni 2026 tetapi tidak
 * pernah ada di master (PosyanduTableSeeder) — ~366 baris. Master di prod
 * berasal dari seeder yang hanya jalan sekali, jadi penambahannya lewat
 * migration, bukan lewat perubahan seeder.
 *
 * Migration harus IDEMPOTEN: dijalankan di server yang posyandunya sudah
 * ditambahkan manual oleh petugas tidak boleh menghasilkan baris kembar —
 * nama kembar dalam satu puskesmas justru bikin FaskesMatcher menyerah
 * ('ambigu') dan datanya kembali tak terlihat.
 */
class PosyanduBaruOtJuni2026MigrationTest extends TestCase
{
    use RefreshDatabase;

    /** Nama posyandu baru => nama puskesmas induknya. */
    private const BARU = [
        'Sejahtera Etam' => 'Bontang Utara 2',
        'Menur 1'        => 'Bontang Utara 1',
        'Nusa Indah 3'   => 'Bontang Selatan 1',
        'Sekatup'        => 'Bontang Utara 1',
        'Pasir Putih 11' => 'Bontang Lestari',
        'Mawar Merah'    => 'Bontang Utara 2',
    ];

    private function migration()
    {
        return require database_path('migrations/2026_09_06_000001_tambah_posyandu_baru_ot_juni_2026.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $kec = \App\Models\Kecamatan::create(['name' => 'Bontang Utara']);
        foreach (array_unique(array_values(self::BARU)) as $p) {
            Puskesmas::create(['name' => $p, 'id_kecamatan' => $kec->id]);
        }
    }

    public function test_menambahkan_enam_posyandu_ke_puskesmas_yang_benar(): void
    {
        $this->migration()->up();

        foreach (self::BARU as $nama => $puskesmas) {
            $pos = Posyandu::where('name', $nama)->first();
            $this->assertNotNull($pos, "Posyandu '{$nama}' tidak dibuat.");
            $this->assertSame(
                Puskesmas::where('name', $puskesmas)->value('id'),
                $pos->id_puskesmas,
                "Posyandu '{$nama}' salah puskesmas."
            );
        }
    }

    public function test_dijalankan_dua_kali_tidak_menghasilkan_baris_kembar(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        foreach (array_keys(self::BARU) as $nama) {
            $this->assertSame(1, Posyandu::where('name', $nama)->count(), "Posyandu '{$nama}' kembar.");
        }
    }

    public function test_tidak_menimpa_posyandu_senama_yang_sudah_ditambahkan_petugas(): void
    {
        $idPus   = Puskesmas::where('name', 'Bontang Utara 1')->value('id');
        $manual  = Posyandu::create(['name' => 'Sekatup', 'id_puskesmas' => $idPus]);

        $this->migration()->up();

        $this->assertSame(1, Posyandu::where('name', 'Sekatup')->count());
        $this->assertSame($manual->id, Posyandu::where('name', 'Sekatup')->value('id'));
    }

    public function test_down_menghapus_hanya_posyandu_yang_belum_dipakai(): void
    {
        $this->migration()->up();
        $dipakai = Posyandu::where('name', 'Sekatup')->first();

        \App\Models\Anak::create([
            'nik' => '6474025209250009', 'nama' => 'ANAK SEKATUP', 'jk' => 1,
            'tgl_lahir' => '2025-01-01', 'sumber' => 'operasi_timbang',
            'status' => 1, 'no' => 'OT-00009', 'id_posyandu' => $dipakai->id,
        ]);

        $this->migration()->down();

        $this->assertNotNull(Posyandu::find($dipakai->id), 'Posyandu yang masih dipakai anak tidak boleh dihapus.');
        $this->assertNull(Posyandu::where('name', 'Mawar Merah')->first());
    }
}
