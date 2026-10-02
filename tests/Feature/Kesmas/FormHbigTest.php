<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** HBIG = field tanggal anak.tgl_hbig, bukan jenis vaksin (spec 2026-10-02 §5.1). */
class FormHbigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $wilayah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
        $pos = Posyandu::create(['name' => 'Melati', 'id_puskesmas' => $pkm->id]);
        $rt  = Rt::create(['name' => '01', 'id_kelurahan' => $kel->id, 'id_posyandu' => $pos->id]);
        $this->wilayah = ['id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => $pkm->id, 'id_posyandu' => $pos->id, 'id_rt' => $rt->id];
    }

    /** Payload form Tambah Anak; tgl_hbig dikirim '' seperti form asli bila tidak diisi. */
    private function payloadTambah(array $extra = []): array
    {
        return array_merge([
            'no_kk' => '6474010101010001', 'nik' => '6474010101230001', 'nama' => 'Bayi HBIG',
            'nik_ortu' => '6474010101900001', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
            'tb' => 49, 'bb' => 3.2, 'lla' => 10, 'lk' => 34, 'asi' => 1, 'obat_cacing' => 0,
            'tgl_kunjungan' => '2025-01-11', 'tgl_hbig' => '',
        ], $this->wilayah, $extra);
    }

    private function anakTersimpan(string $nik, ?string $tglHbig): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Bayi', 'nik' => $nik, 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10',
            'status' => 1, 'no' => '1', 'sumber' => 'manual', 'tgl_hbig' => $tglHbig,
        ], $this->wilayah));
    }

    public function test_tanggal_hbig_tersimpan_dan_dikosongkan_jadi_null(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadTambah(['tgl_hbig' => '2025-01-10']))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();
        $anak = Anak::where('nik', '6474010101230001')->firstOrFail();
        $this->assertSame('2025-01-10', $anak->tgl_hbig);

        $this->put(route('admin.updateAnak', $anak->hashid), array_merge($this->payloadTambah(), [
            'status' => 1, 'posisi' => 'L', 'vit_a' => 0, 'pitting_edema' => 0, 'kelas_ibu_balita' => 0, 'mbg' => 0, 'ddtka' => '',
        ]))->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $this->assertNull($anak->fresh()->tgl_hbig, "'' dari form = dikosongkan petugas");
    }

    public function test_hbig_sebelum_lahir_atau_di_masa_depan_ditolak(): void
    {
        $this->actingAs($this->admin)->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), $this->payloadTambah(['tgl_hbig' => '2025-01-09']))
            ->assertRedirect(route('admin.createAnak'))->assertSessionHasErrors('tgl_hbig');

        $this->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), $this->payloadTambah(['tgl_hbig' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('tgl_hbig');

        $this->assertDatabaseMissing('anak', ['nik' => '6474010101230001']);
    }

    public function test_form_edit_memuat_input_hbig_di_kartu_riwayat_lahir(): void
    {
        $anak = $this->anakTersimpan('6474010101230002', '2025-01-11');

        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $anak->hashid))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="kartuRiwayatLahir" class="collapse">.*<input type="date" name="tgl_hbig" id="tgl_hbig" class="form-control" value="2025-01-11">/s',
            $html
        );
        $this->assertStringContainsString('<label for="tgl_hbig">Tanggal pemberian HBIG</label>', $html);
        $this->assertStringContainsString('Bukan bagian Imunisasi Dasar Lengkap', $html);
    }

    public function test_detail_menampilkan_hbig_di_riwayat_kelahiran(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.showAnak', $this->anakTersimpan('6474010101230003', '2025-01-11')->hashid))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/HBIG<\/dt>\s*<dd[^>]*>\s*11\/01\/2025\s*</', $html);
    }

    public function test_detail_hbig_kosong_tampil_strip(): void
    {
        $anak = $this->anakTersimpan('6474010101230004', null);
        $anak->update(['penolong_lahir' => 'Bidan']); // agar kartu tidak "Belum diisi"

        $html = $this->actingAs($this->admin)->get(route('admin.showAnak', $anak->hashid))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/HBIG<\/dt>\s*<dd[^>]*>\s*—\s*</', $html);
    }
}
