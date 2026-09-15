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

/**
 * Field Kesmas & riwayat lahir di form Tambah/Edit Anak (spec §3.3–3.4).
 * Payload meniru form asli: select kosong mengirim '', bukan menghilangkan field.
 */
class FormAnakKesmasTest extends TestCase
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
        $this->wilayah = [
            'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => $pkm->id,
            'id_posyandu' => $pos->id, 'id_rt' => $rt->id,
        ];
    }

    /** Payload minimum form Tambah Anak (field wajib lama). */
    private function payloadDasar(array $extra = []): array
    {
        return array_merge([
            'no_kk' => '6474010101010001', 'nik' => '6474010101230001', 'nama' => 'Anak Kesmas',
            'nik_ortu' => '6474010101900001', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
            'tb' => 60, 'bb' => 5.5, 'lla' => 12, 'lk' => 40, 'asi' => 1, 'obat_cacing' => 0,
            'tgl_kunjungan' => '2025-06-10',
        ], $this->wilayah, $extra);
    }

    /** Field Kesmas persis seperti form baru: semua dikirim, select kosong = ''. */
    private function payloadKesmasKosong(): array
    {
        return [
            'no_id_epus' => '', 'fktp_bpjs' => '', 'air_bersih' => '', 'jamban_sehat' => '', 'merokok_keluarga' => '',
            'status_tk_paud' => '', 'penyakit_penyerta' => '', 'pjb' => '',
            'bbl' => '', 'pbl' => '', 'lk_lahir' => '', 'usia_kehamilan_lahir' => '', 'tempat_bersalin' => '',
            'jenis_persalinan' => '', 'penolong_lahir' => '', 'imd' => '', 'riwayat_kek_ibu' => '',
            'komplikasi_persalinan' => '', 'skrining_shk' => '', 'skrining_shak' => '', 'skrining_g6pd' => '',
            'pemeriksaan_hepatitis_b' => '', 'komplikasi_neonatal' => '',
        ];
    }

    public function test_store_menyimpan_field_kesmas_dan_riwayat_lahir(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadDasar(array_merge($this->payloadKesmasKosong(), [
            'no_id_epus' => 'EP-001', 'fktp_bpjs' => 'PKM Bontang Utara', 'air_bersih' => '1', 'jamban_sehat' => '0',
            'status_tk_paud' => 'PAUD/KB', 'pjb' => 'Tidak Ada',
            'bbl' => '3.2', 'usia_kehamilan_lahir' => '38', 'jenis_persalinan' => 'SC', 'penolong_lahir' => 'Bidan',
            'imd' => '1', 'riwayat_kek_ibu' => '0', 'skrining_shk' => 'normal', 'pemeriksaan_hepatitis_b' => 'non_reaktif',
            'komplikasi_neonatal' => 'Ikterus, fototerapi 2 hari',
        ])))->assertRedirect(route('admin.anak'));

        $anak = Anak::where('nik', '6474010101230001')->firstOrFail();
        $this->assertSame('EP-001', $anak->no_id_epus);
        $this->assertSame('PKM Bontang Utara', $anak->fktp_bpjs);
        $this->assertSame(1, (int) $anak->air_bersih);
        $this->assertSame(0, (int) $anak->jamban_sehat);
        $this->assertNull($anak->merokok_keluarga);           // select '' → null, BUKAN 0
        $this->assertSame('PAUD/KB', $anak->status_tk_paud);
        $this->assertSame('Tidak Ada', $anak->pjb);
        $this->assertSame(3.2, (float) $anak->bbl);
        $this->assertSame(38, (int) $anak->usia_kehamilan_lahir);
        $this->assertSame('SC', $anak->jenis_persalinan);
        $this->assertSame('Bidan', $anak->penolong_lahir);
        $this->assertSame(1, (int) $anak->imd);
        $this->assertSame(0, (int) $anak->riwayat_kek_ibu);
        $this->assertSame('normal', $anak->skrining_shk);
        $this->assertNull($anak->skrining_shak);
        $this->assertSame('non_reaktif', $anak->pemeriksaan_hepatitis_b);
        $this->assertSame('Ikterus, fototerapi 2 hari', $anak->komplikasi_neonatal);
    }

    public function test_store_tanpa_field_kesmas_tetap_sukses(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeAnak'), $this->payloadDasar())
            ->assertRedirect(route('admin.anak'));

        $anak = Anak::where('nik', '6474010101230001')->firstOrFail();
        $this->assertNull($anak->air_bersih);
        $this->assertNull($anak->skrining_shk);
        $this->assertNull($anak->no_id_epus);
    }

    public function test_store_menolak_nilai_di_luar_daftar(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), $this->payloadDasar([
                'status_tk_paud' => 'SMA', 'skrining_shk' => 'positif', 'usia_kehamilan_lahir' => '60', 'air_bersih' => 'ya',
            ]))
            ->assertRedirect(route('admin.createAnak'))
            ->assertSessionHasErrors(['status_tk_paud', 'skrining_shk', 'usia_kehamilan_lahir', 'air_bersih']);

        $this->assertDatabaseMissing('anak', ['nik' => '6474010101230001']);
    }

    private function anakTersimpan(array $kesmas = []): Anak
    {
        return Anak::create(array_merge([
            'no_kk' => '6474010101010002', 'nik' => '6474010101230002', 'nama' => 'Anak Edit',
            'nik_ortu' => '6474010101900002', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 2,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'golda' => 'A', 'anak' => 2, 'no' => '2',
            'status' => 1, 'sumber' => 'manual',
        ], $this->wilayah, $kesmas));
    }

    /** Payload minimum form Edit Anak tanpa ganti lokasi (id_kec tidak dikirim → cabang pertama updateAnak). */
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

    public function test_update_mengubah_field_kesmas(): void
    {
        $anak = $this->anakTersimpan(['air_bersih' => 1, 'skrining_shk' => 'belum']);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, array_merge($this->payloadKesmasKosong(), [
            'air_bersih' => '0', 'merokok_keluarga' => '1', 'skrining_shk' => 'tidak_normal',
            'tempat_bersalin' => 'RSUD Taman Husada',
        ])))->assertRedirect(route('admin.anak'));

        $anak->refresh();
        $this->assertSame(0, (int) $anak->air_bersih);
        $this->assertSame(1, (int) $anak->merokok_keluarga);
        $this->assertSame('tidak_normal', $anak->skrining_shk);
        $this->assertSame('RSUD Taman Husada', $anak->tempat_bersalin);
        $this->assertNull($anak->jamban_sehat);
    }

    public function test_update_tanpa_field_kesmas_tidak_menimpa_data_lama(): void
    {
        $anak = $this->anakTersimpan(['air_bersih' => 1, 'no_id_epus' => 'EP-777', 'skrining_g6pd' => 'normal']);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak))
            ->assertRedirect(route('admin.anak'));

        $anak->refresh();
        $this->assertSame(1, (int) $anak->air_bersih);
        $this->assertSame('EP-777', $anak->no_id_epus);
        $this->assertSame('normal', $anak->skrining_g6pd);
    }

    public function test_update_menerima_penolong_lahir_lama_di_luar_daftar(): void
    {
        $anak = $this->anakTersimpan(['penolong_lahir' => 'Dukun terlatih']);

        $this->actingAs($this->admin)->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, array_merge($this->payloadKesmasKosong(), [
            'penolong_lahir' => 'Dukun terlatih', 'imd' => '1',
        ])))->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $anak->refresh();
        $this->assertSame('Dukun terlatih', $anak->penolong_lahir);
        $this->assertSame(1, (int) $anak->imd);
    }

    public function test_update_menolak_penolong_lahir_baru_di_luar_daftar(): void
    {
        $anak = $this->anakTersimpan();

        $this->actingAs($this->admin)
            ->from(route('admin.editAnak', $anak->hashid))
            ->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, ['penolong_lahir' => 'Tetangga']))
            ->assertRedirect(route('admin.editAnak', $anak->hashid))
            ->assertSessionHasErrors('penolong_lahir');

        $this->assertNull($anak->refresh()->penolong_lahir);
    }
}
