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
        SpmKategori::create(['nama' => 'Pelayanan Balita', 'satuan' => 'anak']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index'));

        $response->assertStatus(200)
            ->assertSee('Master Data SPM')
            ->assertSee('spmTable', false)
            ->assertSee('angkaModal', false)
            ->assertSee('Tambah Kategori');
    }

    public function test_halaman_memakai_tahun_dari_query_string(): void
    {
        SpmCapaian::create([
            'id_kategori' => SpmKategori::create(['nama' => 'Pelayanan TB', 'satuan' => 'orang'])->id,
            'tahun'       => 2025,
            'sasaran'     => 500,
        ]);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index', ['tahun' => 2025]))
            ->assertStatus(200)
            ->assertSee('<option value="2025" selected', false);
    }

    public function test_breadcrumb_dan_judul_terisi(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index'))
            ->assertStatus(200)
            ->assertSee('Master Data')      // @yield('item')
            ->assertSee('SPM')              // @yield('item-active')
            ->assertDontSee('@endsection'); // jebakan directive nempel
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
        $this->assertStringContainsString('Belum dilaporkan', $data[0]['capaian_badge']);
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

    public function test_detail_mengembalikan_nilai_mentah_bukan_yang_sudah_di_escape(): void
    {
        // Modal mengisi ulang form dari sini. Kalau yang dipakai adalah nilai
        // hasil escape DataTables, teks bebas ditulis balik ke DB dalam bentuk
        // ter-escape dan menumpuk tiap kali disimpan (& -> &amp; -> &amp;amp;).
        $kategori = SpmKategori::create([
            'nama'       => 'Pelayanan "Khusus" & Lansia',
            'satuan'     => 'orang',
            'keterangan' => 'Gabungan <b>dua</b> program & rujukan',
        ]);
        SpmCapaian::create([
            'id_kategori' => $kategori->id,
            'tahun'       => 2026,
            'sasaran'     => 100,
            'tw1'         => 10,
            'catatan'     => 'Stok vaksin & alat terlambat',
        ]);

        $json = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.detail', ['id' => $kategori->id, 'tahun' => 2026]))
            ->assertStatus(200)
            ->json();

        $this->assertSame('Pelayanan "Khusus" & Lansia', $json['kategori']['nama']);
        $this->assertSame('Gabungan <b>dua</b> program & rujukan', $json['kategori']['keterangan']);
        $this->assertSame('Stok vaksin & alat terlambat', $json['angka']['catatan']);
        // Angka datang sebagai float (model meng-cast), siap dipakai <input type=number>.
        $this->assertSame(10.0, (float) $json['angka']['tw1']);
    }

    public function test_detail_tahun_tanpa_angka_mengembalikan_angka_kosong(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan TB', 'satuan' => 'orang']);

        $json = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.detail', ['id' => $kategori->id, 'tahun' => 2026]))
            ->assertStatus(200)
            ->json();

        $this->assertNull($json['angka']['sasaran']);
        $this->assertNull($json['angka']['tw1']);
    }

    public function test_detail_ditolak_untuk_admin_biasa(): void
    {
        $kategori = SpmKategori::create(['nama' => 'Pelayanan TB', 'satuan' => 'orang']);

        $this->actingAs($this->adminBiasa)
            ->getJson(route('admin.masterdata.spm.detail', ['id' => $kategori->id]))
            ->assertStatus(403);
    }

    public function test_pilihan_tahun_mencakup_tahun_depan_walau_belum_ada_datanya(): void
    {
        // Tanpa ini, sasaran tahun depan mustahil diisi: tahunnya tak pernah
        // muncul di dropdown, dan barisnya hanya bisa dibuat dari halaman
        // yang tahunnya itu.
        $depan = now()->year + 1;

        $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index'))
            ->assertStatus(200)
            ->assertSee('<option value="' . $depan . '"', false)
            ->assertSee('<option value="' . config('spm.tahun_min') . '"', false);
    }

    public function test_tahun_terpilih_selalu_ada_di_daftar(): void
    {
        $depan = now()->year + 1;

        $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index', ['tahun' => $depan]))
            ->assertStatus(200)
            ->assertSee('<option value="' . $depan . '" selected', false);
    }

    public function test_keadaan_baris_terlihat_aktif_nonaktif_dan_dihapus(): void
    {
        SpmKategori::create(['nama' => 'Pelayanan Aktif', 'satuan' => 'orang', 'is_active' => true]);
        SpmKategori::create(['nama' => 'Pelayanan Nonaktif', 'satuan' => 'orang', 'is_active' => false]);
        SpmKategori::create(['nama' => 'Pelayanan Dihapus', 'satuan' => 'orang'])->delete();

        $data = collect($this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.getData', ['tahun' => 2026]))
            ->json('data'))
            ->keyBy('nama');

        $this->assertStringContainsString('Aktif', $data['Pelayanan Aktif']['keadaan_badge']);
        $this->assertStringContainsString('Tidak Aktif', $data['Pelayanan Nonaktif']['keadaan_badge']);
        $this->assertStringContainsString('Dihapus', $data['Pelayanan Dihapus']['keadaan_badge']);
    }

    public function test_urutan_menentukan_urutan_baris(): void
    {
        // Dibuat Alfa dulu supaya urutan sisip (Alfa, Zeta) BERBEDA dari urutan
        // yang diharapkan (Zeta, Alfa) — kalau tidak, tesnya lulus tanpa kode apa pun.
        SpmKategori::create(['nama' => 'Alfa', 'satuan' => 'orang', 'urutan' => 2]);
        SpmKategori::create(['nama' => 'Zeta', 'satuan' => 'orang', 'urutan' => 1]);

        $data = $this->actingAs($this->superAdmin)
            ->getJson(route('admin.masterdata.spm.getData', ['tahun' => 2026]))
            ->json('data');

        $this->assertSame(['Zeta', 'Alfa'], array_column($data, 'nama'), 'urutan harus menang atas abjad');
    }

    public function test_halaman_memuat_font_barlow(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.masterdata.spm.index'))
            ->assertStatus(200)
            ->assertSee('family=Barlow', false);
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
