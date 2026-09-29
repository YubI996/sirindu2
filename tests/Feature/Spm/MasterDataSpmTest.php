<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSpmTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $adminBiasa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['type' => 0]);
        $this->adminBiasa = User::factory()->create(['type' => 1]);
    }

    public function test_superadmin_bisa_membuka_halaman(): void
    {
        $this->markTestSkipped('View dibuat di Task 5 — unskip di sana.');
    }

    public function test_admin_biasa_ditolak(): void
    {
        $this->actingAs($this->adminBiasa)
            ->get(route('admin.masterdata.spm.index'))
            ->assertStatus(403);
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get(route('admin.masterdata.spm.index'))->assertRedirect(route('login'));
    }

    public function test_superadmin_bisa_menambah_kategori(): void
    {
        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.spm.store'), [
                'nama'       => 'Pelayanan Kesehatan Ibu Hamil',
                'satuan'     => 'orang',
                'keterangan' => 'Indikator SPM 1',
                'urutan'     => 1,
                'is_active'  => 1,
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('spm_kategori', [
            'nama'   => 'Pelayanan Kesehatan Ibu Hamil',
            'satuan' => 'orang',
        ]);
    }

    public function test_nama_wajib_dan_tidak_boleh_ganda(): void
    {
        SpmKategori::create(['nama' => 'Pelayanan TB', 'satuan' => 'orang']);

        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.spm.store'), ['satuan' => 'orang'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama');

        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.spm.store'), ['nama' => 'Pelayanan TB', 'satuan' => 'orang'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama');
    }

    public function test_nama_kategori_yang_sudah_dihapus_boleh_dipakai_lagi(): void
    {
        $lama = SpmKategori::create(['nama' => 'Pelayanan ODGJ', 'satuan' => 'orang']);
        $lama->delete();

        $this->actingAs($this->superAdmin)
            ->postJson(route('admin.masterdata.spm.store'), ['nama' => 'Pelayanan ODGJ', 'satuan' => 'orang'])
            ->assertStatus(200);

        $this->assertSame(2, SpmKategori::withTrashed()->where('nama', 'Pelayanan ODGJ')->count());
    }

    public function test_superadmin_bisa_mengubah_kategori(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan HIV', 'satuan' => 'orang']);

        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.update', $kategori->id), [
                'nama'   => 'Pelayanan Kesehatan Orang dengan Risiko HIV',
                'satuan' => 'orang',
                'urutan' => 12,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('spm_kategori', [
            'id'     => $kategori->id,
            'nama'   => 'Pelayanan Kesehatan Orang dengan Risiko HIV',
            'urutan' => 12,
        ]);
    }

    public function test_update_boleh_memakai_namanya_sendiri(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan DM', 'satuan' => 'orang']);

        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.update', $kategori->id), [
                'nama'   => 'Pelayanan DM',
                'satuan' => 'pasien',
            ])
            ->assertStatus(200);
    }

    public function test_toggle_hapus_dan_pulihkan(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan Lansia', 'satuan' => 'orang']);

        $this->actingAs($this->superAdmin)
            ->patchJson(route('admin.masterdata.spm.toggleStatus', $kategori->id))
            ->assertStatus(200);
        $this->assertFalse($kategori->fresh()->is_active);

        $this->actingAs($this->superAdmin)
            ->deleteJson(route('admin.masterdata.spm.destroy', $kategori->id))
            ->assertStatus(200);
        $this->assertSoftDeleted('spm_kategori', ['id' => $kategori->id]);

        $this->actingAs($this->superAdmin)
            ->patchJson(route('admin.masterdata.spm.restore', $kategori->id))
            ->assertStatus(200);
        $this->assertNotNull(SpmKategori::find($kategori->id));
    }

    public function test_get_data_menampilkan_angka_tahun_terpilih(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan Balita', 'satuan' => 'anak']);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2025, 'sasaran' => 800, 'tw1' => 800]);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2026, 'sasaran' => 1000, 'tw1' => 200]);

        $data = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.getData', ['tahun' => 2026]))
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('1000.00', (string) $data[0]['sasaran']);
        $this->assertSame(200.0, (float) $data[0]['kumulatif']);
    }

    public function test_get_data_tetap_menampilkan_kategori_tanpa_angka_tahun_itu(): void
    {
        SpmKategori::create(['nama' => 'Pelayanan Hipertensi', 'satuan' => 'orang']);

        $data = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.getData', ['tahun' => 2026]))
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertNull($data[0]['sasaran']);
        $this->assertStringContainsString('Belum dilaporkan', $data[0]['status_badge']);
    }

    public function test_tahun_ngawur_jatuh_ke_tahun_ini_tanpa_error(): void
    {
        SpmKategori::create(['nama' => 'Pelayanan TB', 'satuan' => 'orang']);

        foreach (['abcd', '1900', '9999', ''] as $tahun) {
            $this->actingAs($this->superAdmin)
                ->getJson(route('admin.masterdata.spm.getData', ['tahun' => $tahun]))
                ->assertStatus(200)
                ->assertJsonCount(1, 'data');
        }
    }

    public function test_nama_kategori_berisi_html_tidak_lolos_mentah(): void
    {
        SpmKategori::create(['nama' => '<script>alert(1)</script> & "kutip"', 'satuan' => 'orang']);

        $data = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.getData', ['tahun' => 2026]))
            ->json('data');

        $this->assertStringNotContainsString('<script>', $data[0]['nama']);
        $this->assertStringContainsString('&lt;script&gt;', $data[0]['nama']);
    }
}
