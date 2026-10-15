<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\RumahSakit;
use App\Models\User;
use App\Support\AksesKesmas;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks izin per peran (spec akun Kesmas §2–§3). Peran lama wajib identik dengan
 * BatasDataPribadiKesmas yang digantikan (SEC-001).
 */
class AksesKesmasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        WilkerPuskesmas::flushCache();
    }

    private function user(array $attr): User
    {
        return User::factory()->create($attr);
    }

    public function test_admin_setara_bebas_tanpa_kunci(): void
    {
        foreach ([['type' => 0, 'role' => 'superadmin'], ['type' => 1, 'role' => null], ['type' => 0, 'role' => null]] as $attr) {
            $a = AksesKesmas::untuk($this->user($attr));
            $this->assertFalse($a->terbatas());
            foreach (AksesKesmas::DASBOR as $d) {
                $this->assertTrue($a->bolehDasbor($d), $d);
            }
            $this->assertTrue($a->bolehNama());
            $this->assertTrue($a->bolehExport());
            $this->assertSame([], $a->kunciFilter());
            $this->assertNull($a->batasKelurahanExport());
        }
    }

    public function test_imunisasi_faskes_mengikuti_aturan_lama(): void
    {
        $pkm = Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $pusk = AksesKesmas::untuk($this->user(['type' => 1, 'role' => 'imunisasi_faskes', 'faskes_type' => 'puskesmas', 'id_puskesmas' => $pkm->id]));
        $this->assertTrue($pusk->bolehNama());
        $this->assertTrue($pusk->bolehExport());
        $this->assertSame([], $pusk->kunciFilter(), 'angka dasbor tetap se-kota');
        $this->assertSame(['id_puskesmas' => $pkm->id], $pusk->kunciDataPribadi());

        $rs = AksesKesmas::untuk($this->user(['type' => 1, 'role' => 'imunisasi_faskes', 'faskes_type' => 'rs']));
        $this->assertFalse($rs->bolehNama());
        $this->assertFalse($rs->bolehExport());
        $this->assertTrue($rs->bolehDasbor('kesmas'));
    }

    public function test_rt_dan_user_biasa_tanpa_akses(): void
    {
        foreach ([['type' => 2, 'role' => 'rt'], ['type' => 2, 'role' => null]] as $attr) {
            $a = AksesKesmas::untuk($this->user($attr));
            $this->assertFalse($a->bolehDasbor('kesmas'));
            $this->assertFalse($a->bolehNama());
        }
        $this->assertFalse(AksesKesmas::untuk(null)->bolehDasbor('kesmas'));
    }

    public function test_pimpinan_semua_dasbor_nama_tanpa_export(): void
    {
        $a = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'pimpinan']));
        $this->assertTrue($a->terbatas());
        foreach (AksesKesmas::DASBOR as $d) {
            $this->assertTrue($a->bolehDasbor($d), $d);
        }
        $this->assertTrue($a->bolehNama());
        $this->assertFalse($a->bolehExport());
        $this->assertSame([], $a->kunciFilter());
    }

    public function test_kelurahan_dikunci_dan_boleh_export(): void
    {
        $kel = Kelurahan::factory()->create();
        $a = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $kel->id]));
        $this->assertTrue($a->bolehDasbor('kesmas'));
        $this->assertFalse($a->bolehDasbor('imunisasi'));
        $this->assertTrue($a->bolehNama());
        $this->assertTrue($a->bolehExport());
        $this->assertSame(['id_kelurahan' => $kel->id], $a->kunciFilter());
        $this->assertSame([$kel->id], $a->batasKelurahanExport());
    }

    public function test_kader_dan_puskesmas_dikunci_tanpa_export(): void
    {
        $pos = Posyandu::factory()->create();
        $kader = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'kader', 'id_posyandu' => $pos->id]));
        $this->assertSame(['id_posyandu' => $pos->id], $kader->kunciFilter());
        $this->assertTrue($kader->bolehNama());
        $this->assertFalse($kader->bolehExport());

        $pkm = Puskesmas::factory()->create();
        $p = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'kesmas_puskesmas', 'id_puskesmas' => $pkm->id]));
        $this->assertSame(['id_puskesmas' => $pkm->id], $p->kunciFilter());
        $this->assertFalse($p->bolehExport());
    }

    public function test_rs_kesmas_angka_saja(): void
    {
        $rs = RumahSakit::create(['name' => 'RS Uji Kesmas', 'kode_rs' => 'RS-KESMAS-1', 'is_active' => true]);
        $a = AksesKesmas::untuk($this->user(['type' => 2, 'role' => 'kesmas_rs', 'id_rs' => $rs->id]));
        $this->assertTrue($a->bolehDasbor('kesmas'));
        $this->assertFalse($a->bolehDasbor('timbang'));
        $this->assertFalse($a->bolehNama());
        $this->assertFalse($a->bolehExport());
    }

    public function test_lingkup_kosong_dan_sarkes_tanpa_akses(): void
    {
        foreach (['kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs', 'sarkes'] as $role) {
            $a = AksesKesmas::untuk($this->user(['type' => 2, 'role' => $role]));
            $this->assertTrue($a->terbatas(), $role);
            $this->assertFalse($a->bolehDasbor('kesmas'), $role);
            $this->assertFalse($a->bolehNama(), $role);
        }
    }

    public function test_admin_setara_tidak_pernah_untuk_peran_terbatas_walau_type_admin(): void
    {
        foreach (AksesKesmas::PERAN_TERBATAS as $role) {
            $this->assertFalse(AksesKesmas::adminSetara($this->user(['type' => 1, 'role' => $role])), $role);
            $this->assertFalse(AksesKesmas::adminSetara($this->user(['type' => 0, 'role' => $role])), $role);
        }
    }

    public function test_catchment_puskesmas_tak_dikenal_tidak_berarti_tanpa_batas(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $dikenal = Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $asing = Puskesmas::factory()->create(['name' => 'Puskesmas Antah Berantah']);

        $this->assertCount(1, AksesKesmas::kelurahanCatchment($dikenal->id));
        $this->assertSame([0], AksesKesmas::kelurahanCatchment($asing->id));
    }
}
