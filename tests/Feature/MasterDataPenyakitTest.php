<?php

namespace Tests\Feature;

use App\Models\JenisKasusEpidemiologi;
use App\Models\SurveillanceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataPenyakitTest extends TestCase
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

    public function test_superadmin_can_view_penyakit_index()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.penyakit.index'));

        $response->assertStatus(200);
    }

    public function test_regular_admin_cannot_view_penyakit_index()
    {
        $response = $this->actingAs($this->regularAdmin)
            ->get(route('admin.masterdata.penyakit.index'));

        $response->assertStatus(403);
    }

    public function test_superadmin_can_store_penyakit()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.penyakit.store'), [
                'kode_penyakit' => 'CAMPAK',
                'nama_penyakit' => 'Campak / Measles',
                'kategori' => 'PD3I',
                'deskripsi' => 'Penyakit campak',
                'is_active' => 1,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('jenis_kasus_epidemiologi', ['kode_penyakit' => 'CAMPAK']);
    }

    public function test_store_fails_with_duplicate_kode()
    {
        JenisKasusEpidemiologi::create([
            'kode_penyakit' => 'CAMPAK',
            'nama_penyakit' => 'Campak Existing',
            'kategori' => 'PD3I',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.penyakit.store'), [
                'kode_penyakit' => 'CAMPAK',
                'nama_penyakit' => 'Campak Duplicate',
                'kategori' => 'PD3I',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('kode_penyakit');
    }

    public function test_store_fails_with_special_chars_in_kode()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.penyakit.store'), [
                'kode_penyakit' => 'CAM@#!',
                'nama_penyakit' => 'Invalid',
                'kategori' => 'PD3I',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('kode_penyakit');
    }

    public function test_store_fails_with_invalid_kategori()
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.penyakit.store'), [
                'kode_penyakit' => 'TEST',
                'nama_penyakit' => 'Test Penyakit',
                'kategori' => 'invalid_category',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('kategori');
    }

    public function test_superadmin_can_update_penyakit()
    {
        $penyakit = JenisKasusEpidemiologi::create([
            'kode_penyakit' => 'CAMPAK',
            'nama_penyakit' => 'Campak Original',
            'kategori' => 'PD3I',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.penyakit.update', $penyakit->id), [
                'kode_penyakit' => 'CAMPAK',
                'nama_penyakit' => 'Campak Updated',
                'kategori' => 'PD3I',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('jenis_kasus_epidemiologi', ['nama_penyakit' => 'Campak Updated']);
    }

    public function test_superadmin_can_toggle_status()
    {
        $penyakit = JenisKasusEpidemiologi::create([
            'kode_penyakit' => 'CAMPAK',
            'nama_penyakit' => 'Campak',
            'kategori' => 'PD3I',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->patchJson(route('admin.masterdata.penyakit.toggleStatus', $penyakit->id));

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertFalse($penyakit->fresh()->is_active);
    }

    public function test_delete_without_children_hard_deletes()
    {
        $penyakit = JenisKasusEpidemiologi::create([
            'kode_penyakit' => 'CAMPAK',
            'nama_penyakit' => 'Campak',
            'kategori' => 'PD3I',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson(route('admin.masterdata.penyakit.destroy', $penyakit->id));

        $response->assertStatus(200)->assertJson(['success' => true]);
        // Hard-deleted: completely removed from DB (including soft-deleted)
        $this->assertNull(JenisKasusEpidemiologi::withTrashed()->find($penyakit->id));
    }

    public function test_delete_with_children_soft_deletes()
    {
        $penyakit = JenisKasusEpidemiologi::create([
            'kode_penyakit' => 'CAMPAK',
            'nama_penyakit' => 'Campak',
            'kategori' => 'PD3I',
        ]);

        // Disable FK checks to insert surveillance case without geographic FK dependencies
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
        SurveillanceCase::create([
            'no_registrasi' => 'REG-TEST-001',
            'nik' => '1234567890123456',
            'nama_lengkap' => 'Test Patient',
            'tanggal_lahir' => '2020-01-01',
            'kategori_umur' => 'anak',
            'jenis_kelamin' => 'L',
            'alamat_lengkap' => 'Jl. Test No. 1',
            'id_kec' => 1,
            'id_kel' => 1,
            'id_rt' => 1,
            'nama_pelapor' => 'Petugas Test',
            'id_jenis_kasus' => $penyakit->id,
            'tanggal_onset' => '2026-01-01',
            'tanggal_konsultasi' => '2026-01-02',
            'tanggal_lapor' => '2026-01-02',
            'status_kasus' => 'suspected',
            'status_rawat' => 'rawat_jalan',
            'nama_faskes_rawat' => 'Puskesmas Test',
            'id_petugas_input' => $this->superAdmin->id,
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson(route('admin.masterdata.penyakit.destroy', $penyakit->id));

        $response->assertStatus(200)->assertJson(['success' => true, 'soft_deleted' => true]);
        // Soft-deleted: still in DB with deleted_at set
        $this->assertNotNull(JenisKasusEpidemiologi::withTrashed()->find($penyakit->id));
        $this->assertNotNull(JenisKasusEpidemiologi::withTrashed()->find($penyakit->id)->deleted_at);
        // Not visible via normal query
        $this->assertNull(JenisKasusEpidemiologi::find($penyakit->id));
    }

    public function test_superadmin_can_restore_soft_deleted_penyakit()
    {
        $penyakit = JenisKasusEpidemiologi::create([
            'kode_penyakit' => 'CAMPAK',
            'nama_penyakit' => 'Campak',
            'kategori' => 'PD3I',
        ]);

        // Soft-delete the record
        $penyakit->delete();
        $this->assertNotNull(JenisKasusEpidemiologi::withTrashed()->find($penyakit->id)->deleted_at);

        $response = $this->actingAs($this->superAdmin)
            ->patchJson(route('admin.masterdata.penyakit.restore', $penyakit->id));

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertNull(JenisKasusEpidemiologi::find($penyakit->id)->deleted_at);
    }
}
