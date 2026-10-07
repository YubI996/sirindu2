<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Kelas Ibu Balita" dan "MBG" di form Edit Anak adalah select TIGA keadaan: '' = belum diisi (NULL),
 * 1 = Ya, 0 = Tidak (audit Kesmas 2026-10-06, UX-001/REQ-003).
 *
 * Dulu select hanya "Tidak"/"Ya": NULL tampil "Tidak", lalu menyimpan form (mis. hanya membetulkan nama)
 * menulis 0. Dasbor Layanan memakai pembagi "terisi" (IS NOT NULL) dan menghitung "belum diisi" dari NULL,
 * jadi cakupan tampak anjlok dan daftar "belum diisi" menyusut sendiri tanpa ada layanan yang berubah.
 *
 * Payload meniru form asli: select kosong mengirim '', bukan menghilangkan field.
 */
class EditAnakLayananTigaKeadaanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Anak $anak;
    private DataAnak $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
        $pos = Posyandu::create(['name' => 'Melati', 'id_puskesmas' => $pkm->id]);
        $rt  = Rt::create(['name' => '01', 'id_kelurahan' => $kel->id, 'id_posyandu' => $pos->id]);

        $this->anak = Anak::create([
            'no_kk' => '6474010101010002', 'nik' => '6474010101230002', 'nama' => 'Anak Edit',
            'nik_ortu' => '6474010101900002', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 2,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'golda' => 'A', 'anak' => 2, 'no' => '2',
            'status' => 1, 'sumber' => 'manual',
            'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => $pkm->id, 'id_posyandu' => $pos->id, 'id_rt' => $rt->id,
        ]);
        // mbg & kelas_ibu_balita sengaja tak diisi → NULL ("belum diisi")
        $this->kunjungan = DataAnak::create([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => '2025-05-05', 'bln' => 12, 'posisi' => 'L',
            'tb' => 70, 'bb' => 8, 'lla' => 13, 'lk' => 44, 'asi' => 1, 'vit_a' => 1, 'id_user' => $this->admin->id,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'no_kk' => $this->anak->no_kk, 'nik' => $this->anak->nik, 'nama' => 'Anak Edit (dibetulkan)',
            'nik_ortu' => $this->anak->nik_ortu, 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 2,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'golda' => 'A', 'anak' => 2, 'no' => '2', 'status' => 1,
            'posisi' => 'L', 'tb' => 70, 'bb' => 8, 'lla' => 13, 'lk' => 44, 'asi' => 1, 'vit_a' => 1,
            'pitting_edema' => 0, 'tgl_kunjungan' => '2025-05-05',
            'kelas_ibu_balita' => '', 'mbg' => '',
        ], $extra);
    }

    private function simpan(array $extra = []): DataAnak
    {
        $this->actingAs($this->admin)
            ->put(route('admin.updateAnak', $this->anak->hashid), $this->payload($extra))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        return $this->kunjungan->refresh();
    }

    private function formEdit(): string
    {
        return $this->actingAs($this->admin)->get(route('admin.editAnak', $this->anak->hashid))->assertOk()->getContent();
    }

    public function test_form_edit_menampilkan_null_sebagai_belum_diisi_bukan_tidak(): void
    {
        $html = $this->formEdit();

        foreach (['mbg', 'kelas_ibu_balita'] as $f) {
            $this->assertMatchesRegularExpression('/<select name="' . $f . '"[^>]*>\s*<option value=""\s+selected/s', $html, "$f tak menampilkan 'belum diisi'");
            $this->assertDoesNotMatchRegularExpression('/<select name="' . $f . '"[^>]*>.*?<option value="0"\s+selected/s', $html);
        }
    }

    public function test_form_edit_menampilkan_nol_dan_satu_apa_adanya(): void
    {
        $this->kunjungan->update(['mbg' => 0, 'kelas_ibu_balita' => 1]);
        $html = $this->formEdit();

        $this->assertMatchesRegularExpression('/<select name="mbg"[^>]*>.*?<option value="0"\s+selected/s', $html);
        $this->assertMatchesRegularExpression('/<select name="kelas_ibu_balita"[^>]*>.*?<option value="1"\s+selected/s', $html);
    }

    public function test_membetulkan_nama_anak_tidak_mengubah_belum_diisi_menjadi_tidak(): void
    {
        $dt = $this->simpan();

        $this->assertSame('Anak Edit (dibetulkan)', $this->anak->refresh()->nama);
        $this->assertNull($dt->mbg);
        $this->assertNull($dt->kelas_ibu_balita);
    }

    public function test_pilihan_ya_dan_tidak_tersimpan_dan_bisa_dikembalikan_ke_belum_diisi(): void
    {
        $dt = $this->simpan(['mbg' => '1', 'kelas_ibu_balita' => '0']);
        $this->assertSame(1, (int) $dt->mbg);
        $this->assertSame(0, (int) $dt->kelas_ibu_balita);

        $dt = $this->simpan(['mbg' => '', 'kelas_ibu_balita' => '']);
        $this->assertNull($dt->mbg);
        $this->assertNull($dt->kelas_ibu_balita);
    }

    public function test_field_yang_tidak_dikirim_tidak_disentuh(): void
    {
        $this->kunjungan->update(['mbg' => 1, 'kelas_ibu_balita' => 0]);
        $payload = $this->payload();
        unset($payload['mbg'], $payload['kelas_ibu_balita']);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $this->anak->hashid), $payload)
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $dt = $this->kunjungan->refresh();
        $this->assertSame(1, (int) $dt->mbg);
        $this->assertSame(0, (int) $dt->kelas_ibu_balita);
    }

    public function test_nilai_di_luar_pilihan_ditolak_dengan_pesan_indonesia(): void
    {
        $url = route('admin.editAnak', $this->anak->hashid);

        $html = $this->actingAs($this->admin)->followingRedirects()->from($url)
            ->put(route('admin.updateAnak', $this->anak->hashid), $this->payload(['mbg' => 'mungkin']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Makan Bergizi Gratis (MBG) harus dipilih Ya atau Tidak.', $html);
        $this->assertNull($this->kunjungan->refresh()->mbg);
    }
}
