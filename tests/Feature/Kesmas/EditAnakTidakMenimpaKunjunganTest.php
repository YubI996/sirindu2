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
 * Menyimpan Edit Anak (form identitas) tidak boleh menghapus isian kunjungan yang tak punya
 * input di form itu (audit Kesmas 2026-10-06, REQ-002).
 *
 * Form identitas mengirim tb/bb/lla/lk/asi/vit_a/pitting_edema/kelas_ibu_balita/mbg/tgl_kunjungan/posisi,
 * tetapi TIDAK mengirim ddtka, obat_cacing, asi_bulan_0..6, maupun ntob. Dulu semuanya ditulis ulang
 * (null/false) pada baris kunjungan pertama setiap kali nama anak dibetulkan. Dasbor Kesmas membaca
 * `ntob = 'T'` (BB tidak naik) dan kolom lain itu untuk SDIDTK/K2–K4 → angkanya turun tanpa tanda.
 *
 * Payload di sini meniru form asli: field-field itu SENGAJA tidak ada.
 */
class EditAnakTidakMenimpaKunjunganTest extends TestCase
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

        // kunjungan hasil import/OT: berisi kolom yang tak bisa diisi dari form identitas
        $this->kunjungan = DataAnak::create([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => '2025-05-05', 'bln' => 12, 'posisi' => 'L',
            'tb' => 70, 'bb' => 8, 'lla' => 13, 'lk' => 44, 'asi' => 1, 'vit_a' => 1, 'id_user' => $this->admin->id,
            'ntob' => 'T', 'obat_cacing' => 1, 'ddtka' => 'Sesuai',
            'asi_bulan_0' => 1, 'asi_bulan_1' => 1, 'asi_bulan_2' => 1, 'asi_bulan_3' => 1,
            'asi_bulan_4' => 1, 'asi_bulan_5' => 1, 'asi_bulan_6' => 1,
        ]);
    }

    /** Payload form Edit Anak asli: tanpa ddtka, obat_cacing, asi_bulan_*, ntob. */
    private function payloadFormIdentitas(array $extra = []): array
    {
        return array_merge([
            'no_kk' => $this->anak->no_kk, 'nik' => $this->anak->nik, 'nama' => 'Anak Edit (dibetulkan)',
            'nik_ortu' => $this->anak->nik_ortu, 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 2,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'golda' => 'A', 'anak' => 2, 'no' => '2', 'status' => 1,
            'posisi' => 'L', 'tb' => 70, 'bb' => 8, 'lla' => 13, 'lk' => 44, 'asi' => 1, 'vit_a' => 1,
            'pitting_edema' => 0, 'kelas_ibu_balita' => 0, 'mbg' => 0, 'tgl_kunjungan' => '2025-05-05',
        ], $extra);
    }

    private function simpanIdentitas(array $extra = []): DataAnak
    {
        $this->actingAs($this->admin)
            ->put(route('admin.updateAnak', $this->anak->hashid), $this->payloadFormIdentitas($extra))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        return $this->kunjungan->refresh();
    }

    public function test_membetulkan_nama_anak_tidak_menghapus_kolom_kunjungan_yang_tak_ada_di_form(): void
    {
        $dt = $this->simpanIdentitas();

        $this->assertSame('Anak Edit (dibetulkan)', $this->anak->refresh()->nama);
        $this->assertSame('Sesuai', $dt->ddtka);
        $this->assertSame(1, (int) $dt->obat_cacing);
        foreach (range(0, 6) as $i) {
            $this->assertSame(1, (int) $dt->{"asi_bulan_$i"}, "asi_bulan_$i tertimpa");
        }
    }

    public function test_membetulkan_nama_anak_mempertahankan_ntob_selama_bb_tidak_berubah(): void
    {
        $this->assertSame('T', $this->simpanIdentitas()->ntob);
    }

    public function test_mengubah_bb_mengosongkan_ntob_yang_kini_basi(): void
    {
        $this->assertNull($this->simpanIdentitas(['bb' => 9])->ntob);
    }

    public function test_field_yang_dikirim_tetap_ditulis(): void
    {
        $dt = $this->simpanIdentitas(['ddtka' => 'Penyimpangan', 'obat_cacing' => 0, 'asi_bulan_0' => 0, 'asi_bulan_1' => 1]);

        $this->assertSame('Penyimpangan', $dt->ddtka);
        $this->assertSame(0, (int) $dt->obat_cacing);
        $this->assertSame(0, (int) $dt->asi_bulan_0);
        $this->assertSame(1, (int) $dt->asi_bulan_1);
    }

    private function simpanKunjungan(array $extra = []): DataAnak
    {
        $this->actingAs($this->admin)
            ->put(route('admin.updateDataAnak', $this->kunjungan->id), array_merge([
                'tgl_kunjungan' => '2025-05-05', 'posisi' => 'L', 'tb' => 70, 'bb' => 8, 'lla' => 13, 'lk' => 44,
                'asi' => 1, 'vit_a' => 1, 'obat_cacing' => 1, 'ddtka' => 'Sesuai',
            ], $extra))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        return $this->kunjungan->refresh();
    }

    public function test_form_per_kunjungan_mempertahankan_ntob_selama_bb_tidak_berubah(): void
    {
        $this->assertSame('T', $this->simpanKunjungan(['catatan_pengukuran' => 'dicek ulang'])->ntob);
    }

    public function test_form_per_kunjungan_mengosongkan_ntob_bila_bb_berubah(): void
    {
        $this->assertNull($this->simpanKunjungan(['bb' => 9])->ntob);
    }
}
