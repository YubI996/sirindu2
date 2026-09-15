<?php

namespace Tests\Feature\Kesmas;

use App\Http\Requests\Admin\Anak\KesmasRules;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layanan Kesmas per kunjungan di form Tambah Pengukuran & edit per-kunjungan (spec §3).
 * Payload meniru form asli: checkbox tak dicentang mengirim '0' lewat hidden input.
 */
class FormPengukuranKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Anak $anak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
        $this->anak = Anak::create([
            'nama' => 'Anak Kunjungan', 'nik' => '6474010101250001', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-01-10', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ]);
    }

    /** Payload form Tambah Pengukuran persis seperti form: semua field Kesmas dikirim. */
    private function payloadKunjungan(array $extra = []): array
    {
        $checkbox = array_fill_keys(array_keys(config('kesmas.layanan')), '0');

        return array_merge([
            'id_anak_hash' => $this->anak->hashid, 'tgl_kunjungan' => '2025-02-10', 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'asi' => 1, 'vit_a' => 0, 'obat_cacing' => 0, 'ddtka' => '',
            'tgl_penanda_ckg' => '', 'pemeriksaan_gigi' => '', 'rujukan' => '', 'mt_pangan_lokal' => '',
            'catatan_pengukuran' => '', 'pemeriksaan_lainnya' => '', 'pola_makan' => '', 'pola_asuh' => '', 'intervensi' => '',
        ], $checkbox, $extra);
    }

    private function kunjunganTersimpan(array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => '2025-02-10', 'bln' => 1, 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'id_user' => $this->admin->id,
        ], $extra));
    }

    public function test_store_pengukuran_menyimpan_layanan(): void
    {
        $this->actingAs($this->admin)->post(route('admin.storeDataAnak'), $this->payloadKunjungan([
            'kn1' => '1', 'mtbs' => '1', 'mbg' => '1', 'tgl_penanda_ckg' => '2025-02-10',
            'pemeriksaan_gigi' => 'Sehat', 'rujukan' => 'Tidak dirujuk', 'pola_makan' => 'ASI eksklusif',
        ]))->assertRedirect(route('admin.anak'));

        $d = DataAnak::where('id_anak', $this->anak->id)->firstOrFail();
        $this->assertSame(1, (int) $d->kn1);
        $this->assertSame(0, (int) $d->kn3);           // '0' dari hidden input → 0, bukan null
        $this->assertNotNull($d->kn3);
        $this->assertSame(1, (int) $d->mtbs);
        $this->assertSame(0, (int) $d->mtbm);
        $this->assertSame(1, (int) $d->mbg);
        $this->assertSame(0, (int) $d->kelas_ibu_balita);
        $this->assertSame('2025-02-10', $d->tgl_penanda_ckg);
        $this->assertSame('Sehat', $d->pemeriksaan_gigi);
        $this->assertSame('Tidak dirujuk', $d->rujukan);
        $this->assertSame('ASI eksklusif', $d->pola_makan);
        $this->assertNull($d->intervensi);              // '' → null
    }

    public function test_store_pengukuran_tanpa_field_kesmas_tetap_sukses(): void
    {
        $payload = $this->payloadKunjungan();
        foreach (array_keys(KesmasRules::kunjungan()) as $f) {
            unset($payload[$f]);
        }

        $this->actingAs($this->admin)->post(route('admin.storeDataAnak'), $payload)->assertRedirect(route('admin.anak'));

        $d = DataAnak::where('id_anak', $this->anak->id)->firstOrFail();
        $this->assertNull($d->kn1);
        $this->assertNull($d->pemeriksaan_gigi);
    }

    public function test_store_pengukuran_menolak_gigi_rujukan_dan_tanggal_tidak_sah(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.dataAnak', $this->anak->hashid))
            ->post(route('admin.storeDataAnak'), $this->payloadKunjungan([
                'pemeriksaan_gigi' => 'Bagus', 'rujukan' => 'Ke tetangga', 'tgl_penanda_ckg' => 'bukan-tanggal', 'kn1' => 'ya',
            ]))
            ->assertRedirect(route('admin.dataAnak', $this->anak->hashid))
            ->assertSessionHasErrors(['pemeriksaan_gigi', 'rujukan', 'tgl_penanda_ckg', 'kn1']);

        $this->assertSame(0, DataAnak::where('id_anak', $this->anak->id)->count());
    }

    public function test_update_pengukuran_mengubah_layanan(): void
    {
        $d = $this->kunjunganTersimpan(['kn1' => 1, 'rujukan' => 'Rumah sakit']);

        $payload = $this->payloadKunjungan(['kn1' => '0', 'kn3' => '1', 'rujukan' => '', 'intervensi' => 'Rujuk gizi']);
        unset($payload['id_anak_hash']);

        $this->actingAs($this->admin)->put(route('admin.updateDataAnak', $d->id), $payload)
            ->assertRedirect(route('admin.anak'));

        $d->refresh();
        $this->assertSame(0, (int) $d->kn1);
        $this->assertSame(1, (int) $d->kn3);
        $this->assertNull($d->rujukan);
        $this->assertSame('Rujuk gizi', $d->intervensi);
    }

    public function test_update_pengukuran_tanpa_field_kesmas_tidak_menimpa(): void
    {
        $d = $this->kunjunganTersimpan(['kn1' => 1, 'pemeriksaan_gigi' => 'Karies']);

        $this->actingAs($this->admin)->put(route('admin.updateDataAnak', $d->id), [
            'tgl_kunjungan' => '2025-02-10', 'posisi' => 'L', 'tb' => 56, 'bb' => 4.6, 'lla' => 11, 'lk' => 38,
            'asi' => 1, 'vit_a' => 0, 'obat_cacing' => 0, 'ddtka' => '',
        ])->assertRedirect(route('admin.anak'));

        $d->refresh();
        $this->assertSame(1, (int) $d->kn1);
        $this->assertSame('Karies', $d->pemeriksaan_gigi);
        $this->assertSame(56.0, (float) $d->tb);
    }

    public function test_update_pengukuran_menolak_nilai_tidak_sah(): void
    {
        $d = $this->kunjunganTersimpan();

        $payload = $this->payloadKunjungan(['pemeriksaan_gigi' => 'Bagus']);
        unset($payload['id_anak_hash']);

        $this->actingAs($this->admin)
            ->from(route('admin.editAnak', $this->anak->hashid))
            ->put(route('admin.updateDataAnak', $d->id), $payload)
            ->assertRedirect(route('admin.editAnak', $this->anak->hashid))
            ->assertSessionHasErrors('pemeriksaan_gigi');

        $this->assertNull($d->refresh()->pemeriksaan_gigi);
    }

    public function test_form_tambah_pengukuran_memuat_kartu_layanan(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.dataAnak', $this->anak->hashid))->assertOk()->getContent();

        $this->assertStringContainsString('Layanan Kesmas (opsional)', $html);
        $this->assertMatchesRegularExpression('/id="kartuLayanan" class="collapse"/', $html);
        $this->assertStringContainsString('<input type="hidden" name="kn1" value="0">', $html);
        $this->assertStringContainsString('id="kn1"', $html);
    }

    public function test_form_edit_per_kunjungan_punya_id_unik_dan_nilai_tersimpan(): void
    {
        $a = $this->kunjunganTersimpan(['kn1' => 1, 'pemeriksaan_gigi' => 'Karies']);
        $b = $this->kunjunganTersimpan(['tgl_kunjungan' => '2025-03-10', 'bln' => 2]);

        $html = $this->actingAs($this->admin)->get(route('admin.editAnak', $this->anak->hashid))->assertOk()->getContent();

        $this->assertStringContainsString('id="k' . $a->id . '_kn1"', $html);
        $this->assertStringContainsString('id="k' . $b->id . '_kn1"', $html);
        $this->assertMatchesRegularExpression('/id="k' . $a->id . '_kn1" value="1" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="k' . $b->id . '_kn1" value="1" checked/', $html);
        $this->assertMatchesRegularExpression('/id="k' . $a->id . '_pemeriksaan_gigi".*?<option value="Karies" selected/s', $html);
        // id tidak boleh ganda di satu halaman
        $this->assertSame(1, substr_count($html, 'id="k' . $a->id . '_kartuLayanan"'));
    }
}
