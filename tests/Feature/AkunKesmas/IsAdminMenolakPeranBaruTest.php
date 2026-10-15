<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\RumahSakit;
use App\Models\User;
use App\Support\AksesKesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Peran Kesmas tak boleh lolos ke /admin umum, termasuk bila `type` di DB terisi 0/1. */
class IsAdminMenolakPeranBaruTest extends TestCase
{
    use RefreshDatabase;

    public static function peranDanType(): array
    {
        $kasus = [];
        foreach (AksesKesmas::PERAN_TERBATAS as $role) {
            foreach ([0, 1, 2] as $type) {
                $kasus["$role type $type"] = [$role, $type];
            }
        }

        return $kasus;
    }

    /** @dataProvider peranDanType */
    public function test_rute_admin_umum_403(string $role, int $type): void
    {
        // users.id_rs punya foreign key ke rumah_sakits, jadi barisnya harus nyata.
        $rs = RumahSakit::create(['name' => 'RS Uji', 'jenis_rs' => 'RSUD', 'is_active' => true]);
        $user = User::factory()->create(['type' => $type, 'role' => $role, 'id_kel' => 1, 'id_posyandu' => 1, 'id_puskesmas' => 1, 'id_rs' => $rs->id]);

        foreach (['admin.home', 'admin.anak', 'admin.importCsv.index', 'admin.masterdata.spm.index'] as $rute) {
            $this->actingAs($user)->get(route($rute))->assertForbidden();
        }
    }

    /** Cabang `type` lama di UserAccess: peran terbatas ber-type 0 tak boleh sampai ke super-admin. */
    public function test_peran_terbatas_type_0_ditolak_dari_super_admin(): void
    {
        $rs = RumahSakit::create(['name' => 'RS Uji', 'jenis_rs' => 'RSUD', 'is_active' => true]);
        foreach (AksesKesmas::PERAN_TERBATAS as $role) {
            $user = User::factory()->create(['type' => 0, 'role' => $role, 'id_kel' => 1, 'id_posyandu' => 1, 'id_puskesmas' => 1, 'id_rs' => $rs->id]);
            $this->actingAs($user)->get(route('super.admin.home'))->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['type' => 0, 'role' => 'superadmin']))
            ->get(route('super.admin.home'))->assertOk();
    }

    public function test_admin_lama_tetap_lolos(): void
    {
        foreach ([['type' => 1, 'role' => null], ['type' => 0, 'role' => 'superadmin'], ['type' => 1, 'role' => 'imunisasi_faskes']] as $attr) {
            $this->actingAs(User::factory()->create($attr))->get(route('admin.home'))->assertOk();
        }
    }
}
