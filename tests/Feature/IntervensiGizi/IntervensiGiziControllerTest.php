<?php

namespace Tests\Feature\IntervensiGizi;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\IntervensiGizi;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntervensiGiziControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // bb=12/tb=90/bln=24 → gizi buruk (prioritas 1) via observer snapshot.
        StatusGiziService::useRefs([
            '1_1_24_2' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            '2_1_24_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            '3_1_24_2' => (object) ['m3sd' => 80, 'm2sd' => 83, '3sd' => 97],
            '4_1_90_2' => (object) ['m3sd' => 15, 'm2sd' => 16, '1sd' => 20, '2sd' => 22, '3sd' => 24],
        ]);
    }

    protected function tearDown(): void
    {
        StatusGiziService::flushCache();
        parent::tearDown();
    }

    private function anak(string $nik = '3201000000009401'): Anak
    {
        return Anak::create([
            'nama' => 'Budi', 'nik' => $nik, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);
    }

    private function anakPrioritas(string $nik, ?int $idKel = null): Anak
    {
        $anak = Anak::create([
            'nama' => 'Prio ' . $nik, 'nik' => $nik, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'id_kel' => $idKel,
        ]);
        DataAnak::create(['id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1]);
        return $anak;
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

    public function test_index_render_dengan_rekap(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak = $this->anakPrioritas('3201000000009410');
        IntervensiGizi::create(['id_anak' => $anak->id, 'jenis' => 'PMT', 'status' => 'Selesai']);

        $resp = $this->actingAs($super)->get(route('admin.intervensi.index'));

        $resp->assertStatus(200);
        $this->assertSame(1, $resp->viewData('rekap')['total_prioritas']);
        $this->assertSame(1, $resp->viewData('rekap')['sudah']);
        $resp->assertSee('Prio 3201000000009410');
    }

    public function test_non_super_hanya_melihat_kelurahannya(): void
    {
        $kelA = Kelurahan::create(['name' => 'Api-Api', 'id_kecamatan' => 1]);
        $kelB = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        $this->anakPrioritas('3201000000009411', $kelA->id);
        $this->anakPrioritas('3201000000009412', $kelB->id);

        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $kelA->id]);
        $resp = $this->actingAs($faskes)->get(route('admin.intervensi.index'));

        $resp->assertStatus(200);
        $daftar = $resp->viewData('daftar');
        $this->assertCount(1, $daftar);
        $this->assertSame('Api-Api', $daftar[0]['kelurahan']);
    }

    public function test_non_super_tidak_bisa_store_anak_kelurahan_lain(): void
    {
        $kelA = Kelurahan::create(['name' => 'Api-Api', 'id_kecamatan' => 1]);
        $kelB = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        $anakB = $this->anakPrioritas('3201000000009420', $kelB->id);
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $kelA->id]);

        $this->actingAs($faskes)->post(route('admin.intervensi.store'), [
            'id_anak' => $anakB->id, 'jenis' => 'PMT', 'status' => 'Direncanakan',
        ])->assertForbidden();

        $this->assertDatabaseCount('intervensi_gizi', 0);
    }

    public function test_non_super_tidak_bisa_update_intervensi_kelurahan_lain(): void
    {
        $kelA = Kelurahan::create(['name' => 'Api-Api', 'id_kecamatan' => 1]);
        $kelB = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        $anakB = $this->anakPrioritas('3201000000009421', $kelB->id);
        $iv = IntervensiGizi::create(['id_anak' => $anakB->id, 'jenis' => 'PMT', 'status' => 'Direncanakan']);
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $kelA->id]);

        $this->actingAs($faskes)->put(route('admin.intervensi.update', $iv), [
            'jenis' => 'PMT', 'status' => 'Selesai',
        ])->assertForbidden();

        $this->assertSame('Direncanakan', $iv->fresh()->status);
    }

    public function test_non_super_tidak_bisa_destroy_intervensi_kelurahan_lain(): void
    {
        $kelA = Kelurahan::create(['name' => 'Api-Api', 'id_kecamatan' => 1]);
        $kelB = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        $anakB = $this->anakPrioritas('3201000000009422', $kelB->id);
        $iv = IntervensiGizi::create(['id_anak' => $anakB->id, 'jenis' => 'PMT', 'status' => 'Direncanakan']);
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $kelA->id]);

        $this->actingAs($faskes)->delete(route('admin.intervensi.destroy', $iv))->assertForbidden();

        $this->assertDatabaseHas('intervensi_gizi', ['id' => $iv->id]);
    }

    public function test_non_super_bisa_update_intervensi_kelurahannya(): void
    {
        $kelA = Kelurahan::create(['name' => 'Api-Api', 'id_kecamatan' => 1]);
        $anakA = $this->anakPrioritas('3201000000009423', $kelA->id);
        $iv = IntervensiGizi::create(['id_anak' => $anakA->id, 'jenis' => 'PMT', 'status' => 'Direncanakan']);
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $kelA->id]);

        $this->actingAs($faskes)->put(route('admin.intervensi.update', $iv), [
            'jenis' => 'PMT', 'status' => 'Selesai',
        ])->assertRedirect(route('admin.intervensi.index'));

        $this->assertSame('Selesai', $iv->fresh()->status);
    }
}
