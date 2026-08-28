<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Mengunci sisi INPUT dari reviu formulir Agustus 2026.
 *
 * Separuh keluhan klien ("kolomnya kosong") bukan soal cetak, melainkan karena
 * field-nya belum pernah ditanyakan — atau ada tapi mustahil diisi: blok
 * .disease-section di dalam kartu accordion hanya dirender tampak dari $case,
 * sehingga di halaman create (yang belum punya $case) selalu display:none.
 * Test ini menjaga field baru tetap ada dan blok itu tetap bisa muncul.
 */
class FormSurveilansFieldBaruTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 0]);
    }

    private function halamanCreate(): string
    {
        return $this->actingAs($this->admin)
            ->get(route('admin.epidemiologi.create'))
            ->assertOk()
            ->getContent();
    }

    public function test_field_baru_hasil_reviu_tersedia_di_form_create(): void
    {
        $html = $this->halamanCreate();

        // Bag A — DIF-1 no.13
        $this->assertStringContainsString('name="pekerjaan"', $html);
        // Bag D — PERT-01 "Batuk rejan" & DIF-1 "Sakit Tenggorokan"
        $this->assertStringContainsString('name="gejala_batuk_rejan"', $html);
        $this->assertStringContainsString('name="gejala_sakit_tenggorokan"', $html);
        // Bag D3 — DIF-1 "Tracheostomi" & FP-1 "Gangguan rasa raba"
        $this->assertStringContainsString('name="tracheostomi"', $html);
        foreach (['tungkai_kanan', 'tungkai_kiri', 'lengan_kanan', 'lengan_kiri'] as $anggota) {
            $this->assertStringContainsString('name="rasa_raba_' . $anggota . '"', $html);
        }
        // Bag F — DIF-1 no.7 "No. Kode Spesimen"
        $this->assertStringContainsString('spesimen[__IDX__][no_kode_spesimen]', $html);
        // Bag G — PERT-01 "Nomor Rekam Medik"
        $this->assertStringContainsString('name="no_rekam_medik"', $html);
    }

    /** Checkbox gejala baru wajib berpasangan hidden 0 — lihat CLAUDE.md. */
    public function test_gejala_baru_punya_hidden_input_pendamping(): void
    {
        $html = $this->halamanCreate();

        foreach (['gejala_batuk_rejan', 'gejala_sakit_tenggorokan'] as $field) {
            $this->assertStringContainsString(
                '<input type="hidden" name="' . $field . '" value="0">',
                $html,
                "$field kehilangan hidden input; nilainya akan tersimpan salah"
            );
        }
    }

    /** Status gizi (blok D2) kini juga milik Difteri, bukan cuma Campak-Rubella. */
    public function test_blok_status_gizi_terbuka_untuk_difteri(): void
    {
        $html = $this->halamanCreate();

        $this->assertStringContainsString('data-diseases="CAMPAK_RUBELLA,DIFTERI_OBS"', $html);
    }

    /**
     * Blok penyakit di DALAM kartu harus ikut di-toggle oleh JS. Tanpa ini,
     * di halaman create isinya tak pernah tampil dan field seperti status gizi,
     * antibiotik, kelumpuhan, serta sanitasi mustahil diisi.
     */
    public function test_blok_penyakit_dalam_kartu_ikut_ditoggle_javascript(): void
    {
        $html = $this->halamanCreate();

        $this->assertStringContainsString("\$('.disease-section, .disease-field').each(function()", $html);
    }

    public function test_field_baru_tersimpan_saat_edit_kasus(): void
    {
        $kec = Kecamatan::factory()->create();
        $kel = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt  = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $jk  = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'DIFTERI_OBS']);

        $case = SurveillanceCase::factory()->create([
            'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_rt' => $rt->id,
            'id_jenis_kasus' => $jk->id, 'id_petugas_input' => $this->admin->id,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        $case->update([
            'pekerjaan'               => 'Pelajar',
            'tracheostomi'            => 'ya',
            'no_rekam_medik'          => 'RM-9001',
            'gejala_batuk_rejan'      => true,
            'gejala_sakit_tenggorokan' => true,
            'rasa_raba_tungkai_kanan' => 'ya',
        ]);

        $this->assertDatabaseHas('surveillance_cases', [
            'id'                      => $case->id,
            'pekerjaan'               => 'Pelajar',
            'tracheostomi'            => 'ya',
            'no_rekam_medik'          => 'RM-9001',
            'gejala_batuk_rejan'      => 1,
            'rasa_raba_tungkai_kanan' => 'ya',
        ]);
    }
}
