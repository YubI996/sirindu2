<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Models\SasaranKesmasLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tanda "Sasaran Balita Kesmas" (spec 2026-10-02 §5.2). Payload meniru form asli: checkbox tak
 * dicentang mengirim '0' lewat hidden input.
 */
class FormSasaranKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $wilayah;
    private int $n = 0;

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

    private function payloadTambah(array $extra = []): array
    {
        return array_merge([
            'no_kk' => '6474010101010001', 'nik' => '6474010101230001', 'nama' => 'Anak Baru',
            'nik_ortu' => '6474010101900001', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
            'tb' => 60, 'bb' => 5.5, 'lla' => 12, 'lk' => 40, 'asi' => 1, 'obat_cacing' => 0,
            'tgl_kunjungan' => '2025-06-10',
        ], $this->wilayah, $extra);
    }

    private function anakTersimpan(?int $sasaran): Anak
    {
        $this->n++;

        return Anak::create(array_merge([
            'no_kk' => '6474010101010002', 'nik' => '64740101012399' . str_pad((string) $this->n, 2, '0', STR_PAD_LEFT),
            'nama' => 'Anak Lama ' . $this->n, 'nik_ortu' => '6474010101900002', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah',
            'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'golda' => 'A', 'anak' => 2, 'no' => '2',
            'status' => 1, 'sumber' => 'manual', 'sasaran_balita_kesmas' => $sasaran,
        ], $this->wilayah));
    }

    /** Payload form Edit Anak tanpa ganti lokasi (id_kec tidak dikirim → cabang pertama updateAnak). */
    private function payloadEdit(Anak $anak, array $extra = []): array
    {
        return array_merge([
            'no_kk' => $anak->no_kk, 'nik' => $anak->nik, 'nama' => $anak->nama, 'nik_ortu' => $anak->nik_ortu,
            'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05',
            'golda' => 'A', 'anak' => 2, 'no' => '2', 'status' => 1,
            'posisi' => 'L', 'tb' => 70, 'bb' => 8, 'lla' => 13, 'lk' => 44, 'asi' => 1, 'vit_a' => 1,
            'pitting_edema' => 0, 'kelas_ibu_balita' => 0, 'mbg' => 0, 'tgl_kunjungan' => '2025-05-05',
            'obat_cacing' => 0, 'ddtka' => '',
        ], $extra);
    }

    public function test_form_tambah_checkbox_tercentang_default_di_luar_kartu_collapse(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.createAnak'))->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="sasaran_balita_kesmas" value="0">', $html);
        $this->assertMatchesRegularExpression('/id="sasaran_balita_kesmas" name="sasaran_balita_kesmas" value="1" aria-describedby="sasaran_balita_kesmas_bantuan"\s*checked>/', $html);
        $this->assertStringContainsString('<label class="form-check-label" for="sasaran_balita_kesmas">Sasaran Balita Kesmas</label>', $html);
        $this->assertLessThan(strpos($html, 'id="kartuKesmas"'), strpos($html, 'name="sasaran_balita_kesmas"'),
            'Checkbox harus di bagian identitas, bukan di dalam kartu collapse');
    }

    public function test_tambah_dicentang_tersimpan_1_dan_tercatat(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadTambah(['sasaran_balita_kesmas' => '1']))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $anak = Anak::where('nik', '6474010101230001')->firstOrFail();
        $this->assertSame(1, (int) $anak->sasaran_balita_kesmas);
        $log = SasaranKesmasLog::where('id_anak', $anak->id)->sole();
        $this->assertNull($log->nilai_lama);
        $this->assertSame(1, (int) $log->nilai_baru);
        $this->assertSame('form_tambah', $log->sumber);
        $this->assertSame($this->admin->id, (int) $log->id_user);
    }

    public function test_tambah_centang_dilepas_tersimpan_0_bukan_null(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadTambah(['sasaran_balita_kesmas' => '0']))
            ->assertRedirect(route('admin.anak'));

        $nilai = Anak::where('nik', '6474010101230001')->value('sasaran_balita_kesmas');
        $this->assertNotNull($nilai, 'Melepas centang default di Tambah Anak adalah keputusan');
        $this->assertSame(0, (int) $nilai);
    }

    public function test_tambah_tanpa_field_tetap_null_tanpa_log(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadTambah())->assertRedirect(route('admin.anak'));

        $this->assertNull(Anak::where('nik', '6474010101230001')->firstOrFail()->sasaran_balita_kesmas);
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_edit_null_tak_dicentang_tetap_null_tanpa_log(): void
    {
        // Review Focus #4: petugas hanya membetulkan nama anak lama.
        $anak = $this->anakTersimpan(null);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid),
            $this->payloadEdit($anak, ['nama' => 'Nama Dibetulkan', 'sasaran_balita_kesmas' => '0']))
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $anak->refresh();
        $this->assertSame('Nama Dibetulkan', $anak->nama);
        $this->assertNull($anak->sasaran_balita_kesmas, "'0' dari anak NULL bukan keputusan — tetap belum ditandai");
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_edit_mengikuti_matriks_di_kedua_cabang_update(): void
    {
        // [tersimpan, dikirim, hasil, jumlah log]
        $kasus = [[null, '1', 1, 1], [1, '0', 0, 1], [0, '1', 1, 1], [1, '1', 1, 0], [0, '0', 0, 0]];

        foreach ([false, true] as $gantiLokasi) {
            foreach ($kasus as [$lama, $kirim, $hasil, $jumlahLog]) {
                $anak = $this->anakTersimpan($lama);
                $payload = $this->payloadEdit($anak, ['sasaran_balita_kesmas' => $kirim]);
                if ($gantiLokasi) {
                    $payload = array_merge($payload, $this->wilayah); // id_kec terisi → cabang kedua updateAnak
                }

                $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $payload)
                    ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

                $label = sprintf('tersimpan=%s dikirim=%s cabang=%s', var_export($lama, true), $kirim, $gantiLokasi ? 'ganti-lokasi' : 'tetap');
                $this->assertSame($hasil, (int) $anak->fresh()->sasaran_balita_kesmas, $label);
                $this->assertSame($jumlahLog, SasaranKesmasLog::where('id_anak', $anak->id)->count(), $label);
                if ($jumlahLog === 1) {
                    $log = SasaranKesmasLog::where('id_anak', $anak->id)->first();
                    $this->assertSame('form_edit', $log->sumber, $label);
                    $this->assertSame($lama, $log->nilai_lama === null ? null : (int) $log->nilai_lama, $label);
                    $this->assertSame($this->admin->id, (int) $log->id_user, $label);
                }
            }
        }
    }

    public function test_edit_tanpa_field_tidak_menyentuh_tanda(): void
    {
        $anak = $this->anakTersimpan(1);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak))
            ->assertRedirect(route('admin.anak'));

        $this->assertSame(1, (int) $anak->fresh()->sasaran_balita_kesmas);
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_nilai_bukan_boolean_ditolak(): void
    {
        $this->actingAs($this->admin)->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), $this->payloadTambah(['sasaran_balita_kesmas' => 'ya']))
            ->assertRedirect(route('admin.createAnak'))->assertSessionHasErrors('sasaran_balita_kesmas');

        $anak = $this->anakTersimpan(null);
        $this->from(route('admin.editAnak', $anak->hashid))
            ->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, ['sasaran_balita_kesmas' => '2']))
            ->assertSessionHasErrors('sasaran_balita_kesmas');
    }

    public function test_form_edit_null_tak_tercentang_dengan_keterangan_dan_1_tercentang(): void
    {
        $belum = $this->anakTersimpan(null);
        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $belum->hashid))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="1" aria-describedby="sasaran_balita_kesmas_bantuan"\s*>/', $html);
        $this->assertStringContainsString('Status: belum pernah ditandai (tidak dihitung).', $html);

        $sudah = $this->anakTersimpan(1);
        $html = $this->get(route('admin.editAnak', $sudah->hashid))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/value="1" aria-describedby="sasaran_balita_kesmas_bantuan"\s*checked>/', $html);
        $this->assertStringNotContainsString('belum pernah ditandai', $html);
    }
}
