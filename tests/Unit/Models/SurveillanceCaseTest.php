<?php

namespace Tests\Unit\Models;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SurveillanceCaseTest extends TestCase
{
    use DatabaseTransactions;

    private function createCase(array $overrides = []): SurveillanceCase
    {
        $kecamatan = Kecamatan::factory()->create();
        $kelurahan = Kelurahan::factory()->create(['id_kecamatan' => $kecamatan->id]);
        $rt = Rt::factory()->create(['id_kelurahan' => $kelurahan->id]);
        $disease = JenisKasusEpidemiologi::factory()->create();
        $user = User::factory()->create(['type' => 1]);

        $defaults = [
            'id_kec' => $kecamatan->id,
            'id_kel' => $kelurahan->id,
            'id_rt' => $rt->id,
            'id_jenis_kasus' => $disease->id,
            'id_petugas_input' => $user->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        return SurveillanceCase::factory()->create(array_merge($defaults, $overrides));
    }

    // ==================== ACCESSOR TESTS ====================

    public function test_umur_accessor_calculates_age_at_onset()
    {
        // Umur dihitung pada TANGGAL ONSET (umur saat mulai sakit), bukan umur hari ini.
        // Tanggal dipatok tetap agar deterministik: lahir 2000-01-01, onset 2025-06-15 → 25.
        $case = $this->createCase([
            'tanggal_lahir' => '2000-01-01',
            'tanggal_onset' => '2025-06-15',
        ]);

        $this->assertSame(25, $case->umur);
    }

    public function test_umur_accessor_returns_null_when_no_tanggal_lahir()
    {
        $case = $this->createCase();
        $case->tanggal_lahir = null;

        $this->assertNull($case->umur);
    }

    public function test_lama_rawat_accessor_calculates_days_between_dates()
    {
        // Lama rawat dihitung INKLUSIF (hari masuk & hari keluar ikut dihitung):
        // 1–10 Januari = 10 hari. Lihat accessor lamaRawat (diffInDays + 1).
        $case = $this->createCase([
            'tanggal_masuk_rawat' => '2026-01-01',
            'tanggal_keluar_rawat' => '2026-01-10',
        ]);

        $this->assertEquals(10, $case->lama_rawat);
    }

    public function test_lama_rawat_returns_null_when_dates_missing()
    {
        $case = $this->createCase([
            'tanggal_masuk_rawat' => null,
            'tanggal_keluar_rawat' => null,
        ]);

        $this->assertNull($case->lama_rawat);
    }

    // ==================== RELATIONSHIP TESTS ====================

    public function test_belongs_to_kecamatan()
    {
        $kecamatan = Kecamatan::factory()->create();
        $case = $this->createCase(['id_kec' => $kecamatan->id]);

        $this->assertInstanceOf(Kecamatan::class, $case->kecamatan);
        $this->assertEquals($kecamatan->id, $case->kecamatan->id);
    }

    public function test_belongs_to_kelurahan()
    {
        $kecamatan = Kecamatan::factory()->create();
        $kelurahan = Kelurahan::factory()->create(['id_kecamatan' => $kecamatan->id]);
        $case = $this->createCase(['id_kec' => $kecamatan->id, 'id_kel' => $kelurahan->id]);

        $this->assertInstanceOf(Kelurahan::class, $case->kelurahan);
        $this->assertEquals($kelurahan->id, $case->kelurahan->id);
    }

    public function test_belongs_to_rt()
    {
        $case = $this->createCase();

        $this->assertInstanceOf(Rt::class, $case->rt);
    }

    public function test_belongs_to_jenis_kasus()
    {
        $disease = JenisKasusEpidemiologi::factory()->create();
        $case = $this->createCase(['id_jenis_kasus' => $disease->id]);

        $this->assertInstanceOf(JenisKasusEpidemiologi::class, $case->jenisKasus);
        $this->assertEquals($disease->id, $case->jenisKasus->id);
    }

    public function test_belongs_to_petugas_input()
    {
        $case = $this->createCase();

        $this->assertInstanceOf(User::class, $case->petugasInput);
    }

    public function test_belongs_to_creator_and_updater()
    {
        $case = $this->createCase();

        $this->assertInstanceOf(User::class, $case->creator);
        $this->assertInstanceOf(User::class, $case->updater);
    }

    // ==================== SCOPE TESTS ====================

    public function test_scope_by_disease()
    {
        $disease1 = JenisKasusEpidemiologi::factory()->create();
        $disease2 = JenisKasusEpidemiologi::factory()->create();

        $this->createCase(['id_jenis_kasus' => $disease1->id]);
        $this->createCase(['id_jenis_kasus' => $disease1->id]);
        $this->createCase(['id_jenis_kasus' => $disease2->id]);

        $this->assertCount(2, SurveillanceCase::byDisease($disease1->id)->get());
        $this->assertCount(1, SurveillanceCase::byDisease($disease2->id)->get());
    }

    public function test_scope_by_status()
    {
        $this->createCase(['status_kasus' => 'confirmed']);
        $this->createCase(['status_kasus' => 'confirmed']);
        $this->createCase(['status_kasus' => 'suspected']);

        $this->assertCount(2, SurveillanceCase::byStatus('confirmed')->get());
        $this->assertCount(1, SurveillanceCase::byStatus('suspected')->get());
    }

    public function test_scope_by_outcome()
    {
        $this->createCase(['kondisi_akhir' => 'sembuh']);
        $this->createCase(['kondisi_akhir' => 'meninggal', 'penyebab_kematian' => 'Komplikasi']);
        $this->createCase(['kondisi_akhir' => 'dalam_perawatan']);

        $this->assertCount(1, SurveillanceCase::byOutcome('sembuh')->get());
        $this->assertCount(1, SurveillanceCase::byOutcome('meninggal')->get());
    }

    public function test_scope_by_date_range()
    {
        $this->createCase(['tanggal_onset' => '2026-01-15']);
        $this->createCase(['tanggal_onset' => '2026-01-20']);
        $this->createCase(['tanggal_onset' => '2026-02-10']);

        $result = SurveillanceCase::byDateRange('2026-01-01', '2026-01-31')->get();
        $this->assertCount(2, $result);
    }

    public function test_scope_by_kecamatan()
    {
        $kec1 = Kecamatan::factory()->create();
        $kec2 = Kecamatan::factory()->create();

        $this->createCase(['id_kec' => $kec1->id]);
        $this->createCase(['id_kec' => $kec1->id]);
        $this->createCase(['id_kec' => $kec2->id]);

        $this->assertCount(2, SurveillanceCase::byKecamatan($kec1->id)->get());
    }

    public function test_scope_by_kelurahan()
    {
        $kec = Kecamatan::factory()->create();
        $kel1 = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $kel2 = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);

        $this->createCase(['id_kec' => $kec->id, 'id_kel' => $kel1->id]);
        $this->createCase(['id_kec' => $kec->id, 'id_kel' => $kel2->id]);

        $this->assertCount(1, SurveillanceCase::byKelurahan($kel1->id)->get());
    }

    // ==================== HELPER METHOD TESTS ====================

    public function test_get_symptoms_returns_all_symptom_fields()
    {
        $case = $this->createCase([
            'gejala_demam' => true,
            'gejala_batuk' => true,
            'gejala_pilek' => false,
            'gejala_sakit_kepala' => false,
            'gejala_mual' => false,
            'gejala_muntah' => false,
            'gejala_diare' => false,
            'gejala_ruam' => false,
            'gejala_sesak_napas' => false,
            'gejala_nyeri_otot' => false,
            'gejala_nyeri_sendi' => false,
            'gejala_lemas' => false,
            'gejala_kehilangan_nafsu_makan' => false,
            'gejala_mata_merah' => false,
            'gejala_pembengkakan_kelenjar' => false,
            'gejala_kejang' => false,
            'gejala_penurunan_kesadaran' => false,
        ]);

        $symptoms = $case->getSymptoms();

        $this->assertCount(20, $symptoms);
        $this->assertTrue($symptoms['demam']);
        $this->assertTrue($symptoms['batuk']);
        $this->assertFalse($symptoms['pilek']);
    }

    public function test_get_symptom_count_returns_active_count()
    {
        $case = $this->createCase([
            'gejala_demam' => true,
            'gejala_batuk' => true,
            'gejala_diare' => true,
            'gejala_pilek' => false,
            'gejala_sakit_kepala' => false,
            'gejala_mual' => false,
            'gejala_muntah' => false,
            'gejala_ruam' => false,
            'gejala_sesak_napas' => false,
            'gejala_nyeri_otot' => false,
            'gejala_nyeri_sendi' => false,
            'gejala_lemas' => false,
            'gejala_kehilangan_nafsu_makan' => false,
            'gejala_mata_merah' => false,
            'gejala_pembengkakan_kelenjar' => false,
            'gejala_kejang' => false,
            'gejala_penurunan_kesadaran' => false,
        ]);

        $this->assertEquals(3, $case->getSymptomCount());
    }

    // ==================== CAST TESTS ====================

    public function test_date_fields_are_cast_to_carbon()
    {
        $case = $this->createCase();

        $this->assertInstanceOf(Carbon::class, $case->tanggal_lahir);
        $this->assertInstanceOf(Carbon::class, $case->tanggal_onset);
        $this->assertInstanceOf(Carbon::class, $case->tanggal_konsultasi);
    }

    public function test_boolean_symptoms_are_cast_properly()
    {
        $case = $this->createCase([
            'gejala_demam' => 1,
            'gejala_batuk' => 0,
        ]);

        $this->assertTrue($case->gejala_demam);
        $this->assertFalse($case->gejala_batuk);
    }

    public function test_integer_fields_are_cast_properly()
    {
        $case = $this->createCase([
            'jumlah_kontak_serumah' => '3',
            'jumlah_kontak_diluar_rumah' => '5',
        ]);

        $this->assertIsInt($case->jumlah_kontak_serumah);
        $this->assertIsInt($case->jumlah_kontak_diluar_rumah);
    }

    // ==================== APPENDED ATTRIBUTES ====================

    public function test_umur_and_lama_rawat_are_appended_to_json()
    {
        $case = $this->createCase([
            'tanggal_lahir' => '1995-01-01',
            'tanggal_onset' => '2025-06-15',   // umur saat onset = 30
            'tanggal_masuk_rawat' => '2026-01-01',
            'tanggal_keluar_rawat' => '2026-01-05',
        ]);

        $json = $case->toArray();

        $this->assertArrayHasKey('umur', $json);
        $this->assertArrayHasKey('lama_rawat', $json);
        $this->assertSame(30, $json['umur']);
        $this->assertEquals(5, $json['lama_rawat']); // inklusif: 1–5 Januari = 5 hari
    }
}
