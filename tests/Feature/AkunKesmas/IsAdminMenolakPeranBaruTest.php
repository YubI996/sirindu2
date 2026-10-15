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

    public function test_admin_lama_tetap_lolos(): void
    {
        foreach ([['type' => 1, 'role' => null], ['type' => 0, 'role' => 'superadmin'], ['type' => 1, 'role' => 'imunisasi_faskes']] as $attr) {
            $this->actingAs(User::factory()->create($attr))->get(route('admin.home'))->assertOk();
        }
    }
}
