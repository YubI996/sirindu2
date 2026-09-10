<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar posyandu untuk satu kelurahan — dipakai dropdown dasbor Operasi
 * Timbang dan form tambah/ubah anak.
 *
 * Dulu daftar ini dibaca HANYA dari `rt.id_posyandu`: peta ketiga yang tidak
 * pernah dicocokkan dengan data anak maupun berkas OT. Akibatnya di server:
 *
 *   - 742 anak posyandunya tak pernah muncul di dropdown kelurahannya —
 *     termasuk keenam posyandu yang baru ditambahkan September 2026, yang
 *     sampai sekarang tidak ditunjuk satu RT pun;
 *   - sebaliknya muncul posyandu yang tak punya anak di sana sama sekali, dan
 *     memilihnya menghasilkan halaman kosong tanpa penjelasan. Itulah keluhan
 *     "Kanaan -> Sejahtera IV, datanya kosong".
 *
 * Maka daftarnya kini GABUNGAN dua sumber: yang ditunjuk peta RT, DAN yang
 * benar-benar punya anak di kelurahan itu. Peta RT tetap dipakai karena posyandu
 * yang sah tapi belum berisi anak harus tetap bisa dipilih di form — kalau
 * daftarnya murni dari data anak, posyandu baru mustahil diisi yang pertama kali.
 */
class PosyanduPerKelurahanTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Kelurahan $kanaan;
    protected Kelurahan $belimbing;
    protected Puskesmas $barat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['type' => 1]);

        $kec = Kecamatan::create(['name' => 'Bontang Barat']);
        $this->barat     = Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $kec->id]);
        $this->kanaan    = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => $kec->id]);
        $this->belimbing = Kelurahan::create(['name' => 'Belimbing', 'id_kecamatan' => $kec->id]);
    }

    private function posyandu(string $nama): Posyandu
    {
        return Posyandu::create(['name' => $nama, 'id_puskesmas' => $this->barat->id]);
    }

    private function rt(string $nama, Kelurahan $kel, ?Posyandu $pos): Rt
    {
        return Rt::create([
            'name'         => $nama,
            'id_kelurahan' => $kel->id,
            'id_posyandu'  => $pos?->id,
        ]);
    }

    private function anak(Kelurahan $kel, ?Posyandu $pos): Anak
    {
        return Anak::create([
            'nik' => (string) random_int(6474000000000000, 6474999999999999),
            'nama' => 'ANAK UJI', 'jk' => 2, 'tgl_lahir' => '2025-09-12',
            'sumber' => 'operasi_timbang', 'status' => 1,
            'id_kel' => $kel->id, 'id_posyandu' => $pos?->id,
        ]);
    }

    /** @return array<int,string> nama posyandu pada respons */
    private function daftar(Kelurahan $kel): array
    {
        $res = $this->actingAs($this->admin)
            ->getJson(route('admin.getPosyanduByKelAnak', $kel->id));

        $res->assertStatus(200);

        return array_values((array) $res->json());
    }

    public function test_posyandu_yang_punya_anak_di_kelurahan_ikut_muncul_walau_tak_ada_di_peta_rt(): void
    {
        // Kasus nyata keenam posyandu baru: ada di data induk, punya ratusan
        // anak, tapi nol RT menunjuknya — jadi tak pernah bisa dipilih.
        $sekatup = $this->posyandu('Sekatup');
        $this->anak($this->kanaan, $sekatup);

        $this->assertContains('Sekatup', $this->daftar($this->kanaan));
    }

    public function test_posyandu_dari_peta_rt_tetap_muncul_walau_belum_punya_anak(): void
    {
        // Form tambah anak butuh pilihan yang sah, bukan cuma yang sudah terisi.
        // Kalau tidak, posyandu baru mustahil diisi anak yang pertama.
        $baru = $this->posyandu('Posyandu Baru');
        $this->rt('RT 01', $this->kanaan, $baru);

        $this->assertContains('Posyandu Baru', $this->daftar($this->kanaan));
    }

    public function test_posyandu_kelurahan_lain_tidak_ikut(): void
    {
        $milikBelimbing = $this->posyandu('Srikandi');
        $this->rt('RT 09', $this->belimbing, $milikBelimbing);
        $this->anak($this->belimbing, $milikBelimbing);

        $this->assertNotContains('Srikandi', $this->daftar($this->kanaan));
    }

    public function test_posyandu_yang_ada_di_kedua_sumber_hanya_muncul_sekali(): void
    {
        $sejahtera = $this->posyandu('Sejahtera I');
        $this->rt('RT 01', $this->kanaan, $sejahtera);
        $this->anak($this->kanaan, $sejahtera);
        $this->anak($this->kanaan, $sejahtera);

        $this->assertSame(['Sejahtera I'], $this->daftar($this->kanaan));
    }

    public function test_anak_tanpa_posyandu_tidak_bikin_entri_kosong(): void
    {
        // id_posyandu NULL adalah keadaan yang justru sedang diperbaiki backfill;
        // ia tidak boleh menyelinap jadi baris hantu di dropdown.
        // (Tanpa baris rt: kolom `rt.id_posyandu` NOT NULL, jadi RT tanpa
        // posyandu memang tak bisa ada — hanya `anak` yang bisa kosong.)
        $this->anak($this->kanaan, null);

        $this->assertSame([], $this->daftar($this->kanaan));
    }
}
