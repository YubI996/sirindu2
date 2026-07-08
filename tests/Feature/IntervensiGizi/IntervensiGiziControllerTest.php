<?php

namespace Tests\Feature\IntervensiGizi;

use App\Models\Anak;
use App\Models\IntervensiGizi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntervensiGiziControllerTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik = '3201000000009401'): Anak
    {
        return Anak::create([
            'nama' => 'Budi', 'nik' => $nik, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
    }

    public function test_store_menambah_intervensi(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak = $this->anak();

        $this->actingAs($super)->post(route('admin.intervensi.store'), [
            'id_anak' => $anak->id, 'jenis' => 'PMT', 'status' => 'Direncanakan',
            'tanggal' => '2026-07-01', 'pelaksana' => 'Dinkes', 'catatan' => 'PMT 30 hari',
        ])->assertRedirect(route('admin.intervensi.index'));

        $this->assertDatabaseHas('intervensi_gizi', [
            'id_anak' => $anak->id, 'jenis' => 'PMT', 'created_by' => $super->id,
        ]);
    }

    public function test_store_menolak_jenis_invalid(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak = $this->anak();

        $this->actingAs($super)->from(route('admin.intervensi.index'))
            ->post(route('admin.intervensi.store'), [
                'id_anak' => $anak->id, 'jenis' => 'TIDAK-ADA', 'status' => 'Direncanakan',
            ])->assertSessionHasErrors('jenis');

        $this->assertDatabaseCount('intervensi_gizi', 0);
    }

    public function test_update_mengubah_status(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak = $this->anak();
        $iv = IntervensiGizi::create(['id_anak' => $anak->id, 'jenis' => 'PMT', 'status' => 'Direncanakan']);

        $this->actingAs($super)->put(route('admin.intervensi.update', $iv), [
            'jenis' => 'PMT', 'status' => 'Selesai',
        ])->assertRedirect(route('admin.intervensi.index'));

        $this->assertSame('Selesai', $iv->fresh()->status);
    }

    public function test_destroy_menghapus_intervensi(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak = $this->anak();
        $iv = IntervensiGizi::create(['id_anak' => $anak->id, 'jenis' => 'PMT', 'status' => 'Direncanakan']);

        $this->actingAs($super)->delete(route('admin.intervensi.destroy', $iv))
            ->assertRedirect(route('admin.intervensi.index'));

        $this->assertDatabaseMissing('intervensi_gizi', ['id' => $iv->id]);
    }
}
