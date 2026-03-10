<?php

namespace Tests\Feature;

use App\Models\JenisVaksin;
use App\Models\Imunisasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataVaksinTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $regularAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['type' => 0]);
        $this->regularAdmin = User::factory()->create(['type' => 1]);
    }

    public function test_superadmin_can_view_vaksin_index()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.vaksin.index'));

        $response->assertStatus(200);
    }

    public function test_regular_admin_cannot_view_vaksin_index()
    {
        $response = $this->actingAs($this->regularAdmin)
            ->get(route('admin.masterdata.vaksin.index'));

        $response->assertStatus(403);
    }

    public function test_superadmin_can_store_vaksin()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.vaksin.store'), [
                'kode' => 'BCG',
                'nama' => 'Bacillus Calmette-Guerin',
                'kategori' => 'Wajib',
                'usia_pemberian_min' => 0,
                'usia_pemberian_max' => 30,
                'interval_hari' => 0,
                'keterangan' => 'Vaksin BCG untuk TBC',
                'aktif' => 1,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('jenis_vaksin', ['kode' => 'BCG']);
    }

    public function test_store_fails_with_duplicate_kode()
    {
        JenisVaksin::create([
            'kode' => 'BCG',
            'nama' => 'BCG Existing',
            'kategori' => 'Wajib',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.vaksin.store'), [
                'kode' => 'BCG',
                'nama' => 'BCG Duplicate',
                'kategori' => 'Wajib',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('kode');
    }

    public function test_store_fails_with_special_chars_in_kode()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.vaksin.store'), [
                'kode' => 'BCG@#!',
                'nama' => 'Invalid Code',
                'kategori' => 'Wajib',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('kode');
    }

    public function test_superadmin_can_update_vaksin()
    {
        $vaksin = JenisVaksin::create([
            'kode' => 'BCG',
            'nama' => 'BCG Original',
            'kategori' => 'Wajib',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.vaksin.update', $vaksin->id), [
                'kode' => 'BCG',
                'nama' => 'BCG Updated',
                'kategori' => 'Wajib',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('jenis_vaksin', ['nama' => 'BCG Updated']);
    }

    public function test_update_unique_kode_ignores_self()
    {
        $vaksin = JenisVaksin::create([
            'kode' => 'BCG',
            'nama' => 'BCG',
            'kategori' => 'Wajib',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.vaksin.update', $vaksin->id), [
                'kode' => 'BCG',
                'nama' => 'BCG Renamed',
                'kategori' => 'Wajib',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_superadmin_can_toggle_status()
    {
        $vaksin = JenisVaksin::create([
            'kode' => 'BCG',
            'nama' => 'BCG',
            'kategori' => 'Wajib',
            'aktif' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patchJson(route('admin.masterdata.vaksin.toggleStatus', $vaksin->id));

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertFalse($vaksin->fresh()->aktif);
    }

    public function test_delete_without_children_hard_deletes()
    {
        $vaksin = JenisVaksin::create([
            'kode' => 'BCG',
            'nama' => 'BCG',
            'kategori' => 'Wajib',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson(route('admin.masterdata.vaksin.destroy', $vaksin->id));

        $response->assertStatus(200)->assertJson(['success' => true]);
        // Hard-deleted: completely removed from DB (including soft-deleted)
        $this->assertNull(JenisVaksin::withTrashed()->find($vaksin->id));
    }

    public function test_delete_with_children_soft_deletes()
    {
        $vaksin = JenisVaksin::create([
            'kode' => 'BCG',
            'nama' => 'BCG',
            'kategori' => 'Wajib',
        ]);

        // Create a child anak record manually (no factory exists)
        $anak = \App\Models\Anak::create([
            'no_kk' => '1234567890123456',
            'nik' => '1234567890123457',
            'nama' => 'Test Anak',
            'nik_ortu' => '1234567890123458',
            'nama_ibu' => 'Ibu Test',
            'nama_ayah' => 'Ayah Test',
            'jk' => 1,
            'tempat_lahir' => 'Jakarta',
            'tgl_lahir' => '2025-01-01',
            'golda' => 'O',
            'anak' => 1,
            'no' => '001',
            'status' => 1,
            'id_kec' => 1,
            'id_kel' => 1,
            'id_rt' => 1,
            'id_posyandu' => 1,
            'id_puskesmas' => 1,
            'catatan' => 'Test',
        ]);

        // Create a child imunisasi record referencing this vaksin
        Imunisasi::create([
            'id_anak' => $anak->id,
            'id_jenis_vaksin' => $vaksin->id,
            'dosis' => 1,
            'status' => 'sudah',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson(route('admin.masterdata.vaksin.destroy', $vaksin->id));

        $response->assertStatus(200)->assertJson(['success' => true, 'soft_deleted' => true]);
        // Soft-deleted: still in DB with deleted_at set
        $this->assertNotNull(JenisVaksin::withTrashed()->find($vaksin->id));
        $this->assertNotNull(JenisVaksin::withTrashed()->find($vaksin->id)->deleted_at);
        // Not visible via normal query
        $this->assertNull(JenisVaksin::find($vaksin->id));
    }

    public function test_superadmin_can_restore_soft_deleted_vaksin()
    {
        $vaksin = JenisVaksin::create([
            'kode' => 'BCG',
            'nama' => 'BCG',
            'kategori' => 'Wajib',
        ]);

        // Soft-delete the record
        $vaksin->delete();
        $this->assertNotNull(JenisVaksin::withTrashed()->find($vaksin->id)->deleted_at);

        $response = $this->actingAs($this->superAdmin)
            ->patchJson(route('admin.masterdata.vaksin.restore', $vaksin->id));

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNull(JenisVaksin::find($vaksin->id)->deleted_at);
    }

    public function test_store_fails_with_invalid_kategori()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.vaksin.store'), [
                'kode' => 'BCG',
                'nama' => 'BCG',
                'kategori' => 'InvalidKategori',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('kategori');
    }

    public function test_store_succeeds_with_valid_enum_kategori()
    {
        foreach (['Wajib', 'Tambahan', 'Booster'] as $kategori) {
            $response = $this->actingAs($this->superAdmin)
                ->postJson(route('admin.masterdata.vaksin.store'), [
                    'kode' => 'VAK_' . $kategori,
                    'nama' => 'Vaksin ' . $kategori,
                    'kategori' => $kategori,
                ]);

            $response->assertStatus(200)->assertJson(['success' => true]);
        }
    }
}
