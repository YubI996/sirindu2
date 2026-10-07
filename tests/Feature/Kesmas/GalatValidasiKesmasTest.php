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
 * Galat validasi field Kesmas harus bisa dipahami petugas (audit Kesmas 2026-10-06, A11Y-002/UX-002).
 *
 * Field Kesmas hidup di kartu collapse yang tertutup. Sebelumnya galatnya berbunyi
 * "The usia kehamilan lahir field must be between 20 and 45." (Inggris, nama kolom mentah) dan
 * kartunya tetap tertutup, jadi petugas tak tahu field mana yang harus dibetulkan.
 */
class GalatValidasiKesmasTest extends TestCase
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

    private function anakTersimpan(): Anak
    {
        return Anak::create(array_merge([
            'no_kk' => '6474010101010002', 'nik' => '6474010101230002', 'nama' => 'Anak Edit',
            'nik_ortu' => '6474010101900002', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 2,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-05-05', 'golda' => 'A', 'anak' => 2, 'no' => '2',
            'status' => 1, 'sumber' => 'manual',
        ], $this->wilayah));
    }

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

    /** Edit Anak dengan satu field Kesmas salah → halaman edit hasil redirect (dengan galatnya). */
    private function editDenganGalat(array $extra): array
    {
        $anak = $this->anakTersimpan();
        $url = route('admin.editAnak', $anak->hashid);

        $resp = $this->actingAs($this->admin)->followingRedirects()->from($url)
            ->put(route('admin.updateAnak', $anak->hashid), $this->payloadEdit($anak, $extra))
            ->assertOk();

        return [$resp->getContent(), $anak];
    }

    public function test_galat_field_riwayat_lahir_berbahasa_indonesia_dengan_label_terbaca(): void
    {
        [$html] = $this->editDenganGalat(['usia_kehamilan_lahir' => '99']);

        $this->assertStringContainsString('Usia kehamilan saat lahir harus antara 20 sampai 45.', $html);
        $this->assertStringNotContainsString('usia kehamilan lahir', $html); // nama kolom mentah dari pesan Inggris
    }

    public function test_galat_tanggal_hbig_sebelum_tanggal_lahir_berbahasa_indonesia(): void
    {
        [$html] = $this->editDenganGalat(['tgl_hbig' => '2020-01-01']);

        $this->assertStringContainsString('Tanggal pemberian HBIG tidak boleh sebelum tanggal lahir.', $html);
        $this->assertStringNotContainsString('tgl hbig', $html);
    }

    public function test_galat_di_kartu_riwayat_lahir_membuka_kartunya(): void
    {
        [$html] = $this->editDenganGalat(['usia_kehamilan_lahir' => '99']);

        $this->assertMatchesRegularExpression('/id="kartuRiwayatLahir" class="collapse show"/', $html);
        $this->assertMatchesRegularExpression('/data-target="#kartuRiwayatLahir" aria-expanded="true"/', $html);
        // kartu Kesmas lain yang tak bermasalah tetap tertutup
        $this->assertMatchesRegularExpression('/id="kartuKesmas" class="collapse"/', $html);
    }

    public function test_galat_di_kartu_kesmas_membuka_kartu_itu_saja(): void
    {
        [$html] = $this->editDenganGalat(['status_tk_paud' => 'SMA']);

        $this->assertMatchesRegularExpression('/id="kartuKesmas" class="collapse show"/', $html);
        $this->assertMatchesRegularExpression('/id="kartuRiwayatLahir" class="collapse"/', $html);
    }

    public function test_tambah_anak_dengan_galat_membuka_kartu_dan_berbahasa_indonesia(): void
    {
        $html = $this->actingAs($this->admin)->followingRedirects()->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), array_merge([
                'no_kk' => '6474010101010001', 'nik' => '6474010101230001', 'nama' => 'Anak Kesmas',
                'nik_ortu' => '6474010101900001', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
                'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
                'tb' => 60, 'bb' => 5.5, 'lla' => 12, 'lk' => 40, 'asi' => 1, 'obat_cacing' => 0,
                'tgl_kunjungan' => '2025-06-10', 'usia_kehamilan_lahir' => '60',
            ], $this->wilayah))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Usia kehamilan saat lahir harus antara 20 sampai 45.', $html);
        $this->assertStringNotContainsString('usia kehamilan lahir', $html);
        $this->assertMatchesRegularExpression('/id="kartuRiwayatLahir" class="collapse show"/', $html);
    }

    public function test_galat_field_bawaan_bukan_kesmas_tidak_ikut_berubah(): void
    {
        // pesan lama (storeAnakRequest::messages) tetap dipakai apa adanya
        $html = $this->actingAs($this->admin)->followingRedirects()->from(route('admin.createAnak'))
            ->post(route('admin.storeAnak'), array_merge(['nik' => '', 'nama' => ''], $this->wilayah))
            ->assertOk()->getContent();

        $this->assertStringContainsString('NIK Tidak Boleh Kosong', $html);
        $this->assertMatchesRegularExpression('/id="kartuRiwayatLahir" class="collapse"/', $html);
    }
}
