<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BB < 2 bulan diinput dalam gram di form pengukuran (spec 2026-10-02 §5.5).
 * Anak lahir 10 Jan 2025 → batas gram 10 Mar 2025 (kunjungan sebelum itu = gram).
 * data_anak.bb SELALU kg.
 */
class SatuanBeratBadanFormTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_GRAM = 'Untuk umur di bawah 2 bulan, berat badan diisi dalam gram (300–8.000), mis. 3250.';
    private const PESAN_KG = 'Berat badan diisi dalam kg (1–150), mis. 7.5.';

    private User $admin;
    private Anak $anak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $this->anak = Anak::create([
            'nama' => 'Bayi Gram', 'nik' => '6474010101250101', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-01-10', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ]);
    }

    private function payload(string $tgl, string $bb, array $extra = []): array
    {
        $checkbox = array_fill_keys(array_keys(config('kesmas.layanan')), '0');

        return array_merge([
            'id_anak_hash' => $this->anak->hashid, 'tgl_kunjungan' => $tgl, 'posisi' => 'L',
            'tb' => 50, 'bb' => $bb, 'lla' => 10, 'lk' => 35, 'asi' => 1, 'vit_a' => 0, 'obat_cacing' => 0, 'ddtka' => '',
            'tgl_penanda_ckg' => '', 'pemeriksaan_gigi' => '', 'rujukan' => '', 'mt_pangan_lokal' => '',
            'catatan_pengukuran' => '', 'pemeriksaan_lainnya' => '', 'pola_makan' => '', 'pola_asuh' => '', 'intervensi' => '',
        ], $checkbox, $extra);
    }

    private function simpan(string $tgl, string $bb)
    {
        return $this->actingAs($this->admin)->from(route('admin.dataAnak', $this->anak->hashid))
            ->post(route('admin.storeDataAnak'), $this->payload($tgl, $bb));
    }

    private function kunjungan(string $tgl, float $bb, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 50, 'bb' => $bb, 'lla' => 10, 'lk' => 35, 'id_user' => $this->admin->id,
        ], $extra));
    }

    private function ubah(DataAnak $d, string $tgl, string $bb, array $extra = [])
    {
        $payload = $this->payload($tgl, $bb, $extra);
        unset($payload['id_anak_hash']);

        return $this->actingAs($this->admin)->from(route('admin.editAnak', $this->anak->hashid))
            ->put(route('admin.updateDataAnak', $d->id), $payload);
    }

    private function bbTersimpan(?DataAnak $d = null): float
    {
        return round((float) ($d ? $d->fresh()->bb : DataAnak::where('id_anak', $this->anak->id)->value('bb')), 3);
    }

    public function test_kunjungan_di_bawah_dua_bulan_disimpan_dari_gram_ke_kg(): void
    {
        $this->simpan('2025-02-10', '3250')->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $this->assertSame(3.25, $this->bbTersimpan());
    }

    public function test_kg_diketik_di_kolom_gram_ditolak_dengan_pesan_gram(): void
    {
        $this->simpan('2025-02-10', '3.25')
            ->assertRedirect(route('admin.dataAnak', $this->anak->hashid))
            ->assertSessionHasErrors(['bb' => self::PESAN_GRAM]);

        $this->assertSame(0, DataAnak::where('id_anak', $this->anak->id)->count());
    }

    public function test_dua_bulan_ke_atas_tetap_kg_dan_gram_ditolak(): void
    {
        $this->simpan('2025-06-10', '5.4')->assertSessionDoesntHaveErrors();
        $this->assertSame(5.4, $this->bbTersimpan());

        $this->simpan('2025-07-10', '5400')->assertSessionHasErrors(['bb' => self::PESAN_KG]);
        $this->assertSame(1, DataAnak::where('id_anak', $this->anak->id)->count());
    }

    public function test_sehari_sebelum_batas_gram_tepat_batas_kg(): void
    {
        $this->simpan('2025-03-09', '4100')->assertSessionDoesntHaveErrors();
        $this->simpan('2025-03-10', '4.1')->assertSessionDoesntHaveErrors();

        $this->assertSame([4.1, 4.1], DataAnak::where('id_anak', $this->anak->id)->orderBy('tgl_kunjungan')
            ->pluck('bb')->map(fn ($v) => round((float) $v, 3))->all());
    }

    public function test_hash_anak_salah_404(): void
    {
        $payload = $this->payload('2025-02-10', '3250');
        $payload['id_anak_hash'] = 'tidak-ada';

        $this->actingAs($this->admin)->post(route('admin.storeDataAnak'), $payload)->assertNotFound();
    }

    public function test_edit_placeholder_bb_nol_bisa_disimpan_tanpa_diubah(): void
    {
        // Baris placeholder ImunisasiImport: bb = tb = lla = lk = 0, sumber imunisasi.
        $d = $this->kunjungan('2025-02-10', 0, ['tb' => 0, 'lla' => 0, 'lk' => 0, 'sumber' => 'imunisasi', 'alasan_tidak_imunisasi' => 'Sakit']);

        $this->ubah($d, '2025-02-10', '0', ['tb' => 0, 'lla' => 0, 'lk' => 0])
            ->assertRedirect(route('admin.anak'))->assertSessionDoesntHaveErrors();

        $this->assertSame(0.0, $this->bbTersimpan($d));
    }

    public function test_edit_data_lama_ganjil_tanpa_diubah_tetap_tersimpan_apa_adanya(): void
    {
        // Data lama: dulu gram diketik di kolom kg → tersimpan 3250 "kg".
        $d = $this->kunjungan('2025-02-10', 3250);

        // Form edit menampilkannya sebagai 3.250.000 gram; dikirim ulang tanpa diubah.
        $this->ubah($d, '2025-02-10', '3250000')->assertSessionDoesntHaveErrors();

        $this->assertSame(3250.0, $this->bbTersimpan($d));
    }

    public function test_edit_nilai_baru_salah_satuan_ditolak_dan_yang_benar_disimpan(): void
    {
        $d = $this->kunjungan('2025-02-10', 3.25);

        $this->ubah($d, '2025-02-10', '3.3')->assertSessionHasErrors(['bb' => self::PESAN_GRAM]);
        $this->assertSame(3.25, $this->bbTersimpan($d));

        $this->ubah($d, '2025-02-10', '3300')->assertSessionDoesntHaveErrors();
        $this->assertSame(3.3, $this->bbTersimpan($d));
    }

    public function test_tanggal_digeser_melewati_batas_tanpa_js_ditolak_bukan_tersimpan_ribuan_kg(): void
    {
        // Review Focus #1: kunjungan 1 bln (3,25 kg tampil 3250 g) digeser ke 3 bln; JS tidak jalan,
        // jadi 3250 terkirim apa adanya dan server membacanya sebagai kg.
        $d = $this->kunjungan('2025-02-10', 3.25);

        $this->ubah($d, '2025-04-10', '3250')->assertSessionHasErrors(['bb' => self::PESAN_KG]);

        $this->assertSame('2025-02-10', $d->fresh()->tgl_kunjungan);
        $this->assertSame(3.25, $this->bbTersimpan($d));
    }

    public function test_edit_bb_dan_tanggal_wajib_diisi(): void
    {
        $d = $this->kunjungan('2025-06-10', 6.0);

        $this->ubah($d, '2025-06-10', '')->assertSessionHasErrors('bb');
        $this->ubah($d, '', '6')->assertSessionHasErrors('tgl_kunjungan');
    }

    public function test_form_tambah_pengukuran_membawa_batas_gram_dari_server(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dataAnak', $this->anak->hashid))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input type="number"[^>]*name="bb" id="bb"[^>]*data-batas-gram="2025-03-10"/', $html);
        $this->assertStringContainsString('<span data-satuan-label="bb">', $html);
        $this->assertStringContainsString('aria-live="polite" data-satuan-info="bb"', $html);
        $this->assertStringContainsString('js/satuan-bb.js', $html);
    }

    public function test_batas_gram_akhir_bulan_tidak_meluber(): void
    {
        // Review Focus #2.
        $anak = Anak::create([
            'nama' => 'Lahir 31 Des', 'nik' => '6474010101250102', 'jk' => 2, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-12-31', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ]);

        $html = $this->actingAs($this->admin)->get(route('admin.dataAnak', $anak->hashid))->assertOk()->getContent();

        $this->assertStringContainsString('data-batas-gram="2026-02-28"', $html);
    }

    public function test_form_edit_per_kunjungan_menampilkan_gram_bulat_untuk_bayi_dan_kg_untuk_lainnya(): void
    {
        $bayi = $this->kunjungan('2025-02-10', 3.2);
        $besar = $this->kunjungan('2025-06-10', 6.5, ['bln' => 5]);

        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $this->anak->hashid))->assertOk()->getContent();

        $idBayi = 'k' . $bayi->id . '_bb';
        $idBesar = 'k' . $besar->id . '_bb';
        $this->assertMatchesRegularExpression('/<label for="' . $idBayi . '">Berat Badan <span data-satuan-label="' . $idBayi . '">\(gram\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/id="' . $idBayi . '"[^>]*value="3200"/', $html);
        $this->assertMatchesRegularExpression('/<label for="' . $idBesar . '">Berat Badan <span data-satuan-label="' . $idBesar . '">\(kg\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/id="' . $idBesar . '"[^>]*value="6.5"/', $html);
        // Form identitas tetap kg dan labelnya kini menyebut satuan.
        $this->assertStringContainsString('<label for="bb">Berat Badan Lahir (kg)', $html);
        $this->assertStringContainsString('js/satuan-bb.js', $html);
    }

    public function test_form_identitas_tambah_anak_tetap_kg_walau_kunjungan_di_bawah_dua_bulan(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);

        $this->actingAs($this->admin)->post(route('admin.storeAnak'), [
            'no_kk' => '6474010101010009', 'nik' => '6474010101250109', 'nama' => 'Bayi Identitas',
            'nik_ortu' => '6474010101900009', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2025-01-10', 'golda' => 'O', 'anak' => 1, 'no' => '1',
            'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_puskesmas' => 1, 'id_posyandu' => 1, 'id_rt' => 1,
            'tb' => 49, 'bb' => '3.2', 'lla' => 10, 'lk' => 34, 'asi' => 1, 'obat_cacing' => 0, 'tgl_kunjungan' => '2025-01-11',
        ])->assertRedirect(route('admin.anak'));

        $id = Anak::where('nik', '6474010101250109')->value('id');
        $this->assertSame(3.2, round((float) DataAnak::where('id_anak', $id)->value('bb'), 3));
    }
}
