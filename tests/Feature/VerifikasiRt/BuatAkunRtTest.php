<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuatAkunRtTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_membuat_akun_rt_dengan_wilayah_turunan(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $rt    = Rt::factory()->create();
        $kel   = Kelurahan::find($rt->id_kelurahan);

        $this->actingAs($super)->post(route('super.admin.storeUser'), [
            'name' => 'Ketua RT 05', 'email' => 'rt05@sirindu.go.id', 'role' => 'rt', 'id_rt' => $rt->id,
        ])->assertRedirect(route('super.admin.user'));

        $user = User::where('email', 'rt05@sirindu.go.id')->firstOrFail();
        $this->assertSame('rt', $user->role);
        $this->assertSame(2, (int) $user->type);
        $this->assertSame($rt->id, (int) $user->id_rt);
        $this->assertSame($kel->id, (int) $user->id_kel);
        $this->assertSame($kel->id_kecamatan, (int) $user->id_kec);
        $this->assertNull($user->faskes_type);
    }

    public function test_role_rt_tanpa_id_rt_ditolak(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);

        $this->actingAs($super)->from(route('super.admin.user'))->post(route('super.admin.storeUser'), [
            'name' => 'Ketua RT', 'email' => 'rt@sirindu.go.id', 'role' => 'rt',
        ])->assertSessionHasErrors('id_rt');
    }

    public function test_update_akun_rt_memindahkan_rt_dan_wilayah(): void
    {
        $super = User::factory()->create(['type' => 0, 'role' => 'superadmin']);
        $rtA   = Rt::factory()->create();
        $rtB   = Rt::factory()->create();
        $user  = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $rtA->id, 'id_kel' => $rtA->id_kelurahan]);

        $this->actingAs($super)->put(route('super.admin.updateUser', $user->id), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'rt', 'id_rt' => $rtB->id,
        ])->assertRedirect(route('super.admin.user'));

        $user->refresh();
        $this->assertSame($rtB->id, (int) $user->id_rt);
        $this->assertSame($rtB->id_kelurahan, (int) $user->id_kel);
    }
}
