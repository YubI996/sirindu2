<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpanAngkaSpmTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected SpmKategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['type' => 0]);
        $this->kategori   = SpmKategori::create(['nama' => 'Pelayanan Balita', 'satuan' => 'anak']);
    }

    /** Payload seperti form aslinya: keempat kotak TW selalu terkirim, yang kosong berupa ''. */
    private function payload(array $ganti = []): array
    {
        return array_merge([
            'tahun'   => 2026,
            'sasaran' => 1000,
            'tw1'     => '200',
            'tw2'     => '',
            'tw3'     => '',
            'tw4'     => '',
            'catatan' => '',
        ], $ganti);
    }

    public function test_menyimpan_angka_untuk_satu_tahun(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload())
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $baris = SpmCapaian::first();

        $this->assertSame(2026, $baris->tahun);
        $this->assertSame(1000.0, $baris->sasaran);
        $this->assertSame(200.0, $baris->tw1);
    }

    public function test_kotak_triwulan_kosong_tersimpan_null_bukan_nol(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload())
            ->assertStatus(200);

        $baris = SpmCapaian::first();

        $this->assertNull($baris->tw2, 'kotak kosong = belum dilaporkan, bukan capaian 0');
        $this->assertNull($baris->tw3);
        $this->assertNull($baris->tw4);
    }

    public function test_nol_yang_diketik_sengaja_tersimpan_sebagai_nol(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload(['tw2' => '0']))
            ->assertStatus(200);

        $this->assertSame(0.0, SpmCapaian::first()->tw2);
    }

    public function test_menyimpan_dua_kali_tidak_menggandakan_baris(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)->putJson($url, $this->payload())->assertStatus(200);
        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tw2' => '150']))->assertStatus(200);

        $this->assertSame(1, SpmCapaian::count());
        $this->assertSame(150.0, SpmCapaian::first()->tw2);
    }

    public function test_mengosongkan_kembali_triwulan_yang_terisi_menghapus_nilainya(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        // Petugas salah ketik TW III, lalu mengosongkannya dan menyimpan ulang.
        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tw3' => '999']))->assertStatus(200);
        $this->assertSame(999.0, SpmCapaian::first()->tw3);

        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tw3' => '']))->assertStatus(200);

        $this->assertNull(SpmCapaian::first()->tw3, 'angka yang dihapus di form harus benar-benar hilang');
    }

    public function test_tahun_berbeda_adalah_baris_berbeda(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tahun' => 2025]))->assertStatus(200);
        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['tahun' => 2026]))->assertStatus(200);

        $this->assertSame(2, SpmCapaian::count());
    }

    public function test_catatan_tersimpan_dan_bisa_dikosongkan(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['catatan' => 'Stok vaksin terlambat di TW II']))
            ->assertStatus(200);
        $this->assertSame('Stok vaksin terlambat di TW II', SpmCapaian::first()->catatan);

        $this->actingAs($this->superAdmin)->putJson($url, $this->payload(['catatan' => '']))->assertStatus(200);
        $this->assertNull(SpmCapaian::first()->catatan);
    }

    public function test_angka_besar_dan_desimal_diterima(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload([
                'sasaran' => '1234567.89',
                'tw1'     => '0.5',
            ]))
            ->assertStatus(200);

        $baris = SpmCapaian::first();

        $this->assertSame(1234567.89, $baris->sasaran);
        $this->assertSame(0.5, $baris->tw1);
    }

    public function test_sasaran_wajib_dan_tidak_boleh_negatif(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['sasaran' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('sasaran');

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['sasaran' => '-5']))
            ->assertStatus(422)->assertJsonValidationErrors('sasaran');

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['tw1' => '-1']))
            ->assertStatus(422)->assertJsonValidationErrors('tw1');
    }

    public function test_tahun_di_luar_rentang_ditolak(): void
    {
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['tahun' => 1999]))
            ->assertStatus(422)->assertJsonValidationErrors('tahun');

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['tahun' => 'abcd']))
            ->assertStatus(422)->assertJsonValidationErrors('tahun');
    }

    public function test_angka_kelewat_besar_ditolak_sebagai_galat_isian(): void
    {
        // decimal(14,2) mentok di 999.999.999.999,99. Salah ketik 13 digit
        // harus jadi pesan di bawah kolomnya, bukan QueryException 500 yang
        // muncul sebagai "Terjadi kesalahan" tanpa keterangan apa pun.
        $url = route('admin.masterdata.spm.angka', $this->kategori->id);

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['sasaran' => '10000000000000']))
            ->assertStatus(422)->assertJsonValidationErrors('sasaran');

        $this->actingAs($this->superAdmin)
            ->putJson($url, $this->payload(['tw1' => '10000000000000']))
            ->assertStatus(422)->assertJsonValidationErrors('tw1');

        $this->assertSame(0, SpmCapaian::count());
    }

    public function test_batas_atas_yang_masih_sah_tetap_diterima(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload([
                'sasaran' => '999999999999.99',
            ]))
            ->assertStatus(200);

        $this->assertSame(999999999999.99, SpmCapaian::first()->sasaran);
    }

    public function test_admin_biasa_tidak_bisa_menyimpan_angka(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $this->actingAs($admin)
            ->putJson(route('admin.masterdata.spm.angka', $this->kategori->id), $this->payload())
            ->assertStatus(403);

        $this->assertSame(0, SpmCapaian::count());
    }

    public function test_kategori_tidak_ada_menghasilkan_404(): void
    {
        $this->actingAs($this->superAdmin)
            ->putJson(route('admin.masterdata.spm.angka', 99999), $this->payload())
            ->assertStatus(404);
    }
}
