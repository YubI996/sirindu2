<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EpidemiologiControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Kecamatan $kecamatan;
    private Kelurahan $kelurahan;
    private Rt $rt;
    private JenisKasusEpidemiologi $disease;

    protected function setUp(): void
    {
        parent::setUp();

        // type=1 => "admin" (via User accessor)
        $this->admin = User::factory()->create(['type' => 1]);

        $this->kecamatan = Kecamatan::factory()->create();
        $this->kelurahan = Kelurahan::factory()->create(['id_kecamatan' => $this->kecamatan->id]);
        $this->rt = Rt::factory()->create(['id_kelurahan' => $this->kelurahan->id]);
        $this->disease = JenisKasusEpidemiologi::factory()->create();
    }

    private function createCase(array $overrides = []): SurveillanceCase
    {
        $defaults = [
            'id_kec' => $this->kecamatan->id,
            'id_kel' => $this->kelurahan->id,
            'id_rt' => $this->rt->id,
            'id_jenis_kasus' => $this->disease->id,
            'id_petugas_input' => $this->admin->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ];

        return SurveillanceCase::factory()->create(array_merge($defaults, $overrides));
    }

    private function validCaseData(array $overrides = []): array
    {
        $defaults = [
            'no_registrasi' => 'EPI-2026-0001',
            'nik' => '3201011234560001',
            'nama_lengkap' => 'John Doe',
            'tanggal_lahir' => '1990-01-15',
            'jenis_kelamin' => 'L',
            'alamat_lengkap' => 'Jl. Merdeka No. 1',
            'id_kec' => $this->kecamatan->id,
            'id_kel' => $this->kelurahan->id,
            'id_rt' => $this->rt->id,
            'no_telepon' => '08123456789',
            'nama_pelapor' => 'Dr. Pelapor',
            'id_jenis_kasus' => $this->disease->id,
            'tanggal_onset' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'tanggal_konsultasi' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'tanggal_lapor' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'status_rawat' => 'rawat_jalan',
            'nama_faskes_rawat' => 'Puskesmas Cikutra',
            'status_kasus' => 'suspected',
            'kondisi_akhir' => 'dalam_perawatan',
            'status_lab' => 'belum_diperiksa',
            'riwayat_imunisasi' => 'tidak_tahu',
            'sumber_penularan' => 'unknown',
        ];

        return array_merge($defaults, $overrides);
    }

    // ==================== AUTH & MIDDLEWARE TESTS ====================

    public function test_unauthenticated_user_is_redirected_to_login()
    {
        $response = $this->get(route('admin.epidemiologi.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_user_gets_403()
    {
        // type=2 => "user" (via accessor)
        $user = User::factory()->create(['type' => 2]);

        $response = $this->actingAs($user)->get(route('admin.epidemiologi.dashboard'));
        $response->assertStatus(403);
    }

    // ==================== DASHBOARD TESTS ====================

    public function test_dashboard_displays_successfully()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.dashboard'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.epidemiologi.dashboard');
        $response->assertViewHasAll(['stats', 'recentCases', 'trendData', 'diseaseData', 'statusData', 'geoData']);
    }

    public function test_dashboard_stats_contain_expected_keys()
    {
        $this->createCase(['status_kasus' => 'confirmed', 'kondisi_akhir' => 'sembuh']);
        $this->createCase(['status_kasus' => 'suspected', 'kondisi_akhir' => 'dalam_perawatan']);

        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.dashboard'));
        $response->assertStatus(200);

        $stats = $response->viewData('stats');
        $this->assertArrayHasKey('total_cases', $stats);
        $this->assertArrayHasKey('confirmed_cases', $stats);
        $this->assertArrayHasKey('suspected_cases', $stats);
        $this->assertArrayHasKey('death_cases', $stats);
        $this->assertArrayHasKey('recovered_cases', $stats);
        $this->assertEquals(2, $stats['total_cases']);
    }

    // ==================== MAP DASHBOARD TESTS ====================

    public function test_map_dashboard_displays_successfully()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.map'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.epidemiologi.map');
        $response->assertViewHasAll(['diseases', 'kecamatanList']);
    }

    public function test_get_map_data_returns_json()
    {
        $this->createCase();

        $response = $this->actingAs($this->admin)->getJson(route('admin.epidemiologi.mapData'));
        $response->assertStatus(200);
        $response->assertJsonStructure(['casesByKelurahan', 'totalCases']);
    }

    public function test_get_map_data_filters_by_disease()
    {
        $disease2 = JenisKasusEpidemiologi::factory()->create();
        $this->createCase(['id_jenis_kasus' => $this->disease->id]);
        $this->createCase(['id_jenis_kasus' => $disease2->id]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.mapData', ['disease_id' => $this->disease->id]));

        $response->assertStatus(200);
        $response->assertJson(['totalCases' => 1]);
    }

    public function test_get_map_data_filters_by_status()
    {
        $this->createCase(['status_kasus' => 'confirmed']);
        $this->createCase(['status_kasus' => 'suspected']);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.mapData', ['status' => 'confirmed']));

        $response->assertStatus(200);
        $response->assertJson(['totalCases' => 1]);
    }

    // ==================== INDEX TESTS ====================

    public function test_index_displays_successfully()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.index'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.epidemiologi.index');
    }

    // ==================== CREATE TESTS ====================

    public function test_create_form_displays_successfully()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.create'));
        $response->assertStatus(200);
        $response->assertViewIs('admin.epidemiologi.create');
        $response->assertViewHasAll(['diseases', 'kecamatanList', 'puskesmasList', 'suggestedRegNumber']);
    }

    public function test_create_form_suggests_registration_number()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.create'));
        $suggestedRegNumber = $response->viewData('suggestedRegNumber');

        $this->assertStringStartsWith('EPI-' . date('Y') . '-', $suggestedRegNumber);
    }

    // ==================== STORE TESTS ====================

    public function test_store_creates_case_with_valid_data()
    {
        $data = $this->validCaseData();

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);

        $response->assertRedirect(route('admin.epidemiologi.index'));
        $this->assertDatabaseHas('surveillance_cases', [
            'no_registrasi' => 'EPI-2026-0001',
            'nik' => '3201011234560001',
            'nama_lengkap' => 'John Doe',
        ]);
    }

    public function test_store_auto_calculates_kategori_umur()
    {
        $data = $this->validCaseData([
            'tanggal_lahir' => Carbon::now()->subYears(3)->format('Y-m-d'),
        ]);

        $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);

        $case = SurveillanceCase::where('no_registrasi', $data['no_registrasi'])->first();
        $this->assertEquals('balita', $case->kategori_umur);
    }

    public function test_store_sets_audit_fields()
    {
        $data = $this->validCaseData();

        $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);

        $case = SurveillanceCase::where('no_registrasi', $data['no_registrasi'])->first();
        $this->assertEquals($this->admin->id, $case->id_petugas_input);
        $this->assertEquals($this->admin->id, $case->created_by);
        $this->assertEquals($this->admin->id, $case->updated_by);
    }

    public function test_store_handles_boolean_symptoms()
    {
        $data = $this->validCaseData([
            'gejala_demam' => 'on',
            'gejala_batuk' => 'on',
        ]);

        $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);

        $case = SurveillanceCase::where('no_registrasi', $data['no_registrasi'])->first();
        $this->assertTrue($case->gejala_demam);
        $this->assertTrue($case->gejala_batuk);
        $this->assertFalse($case->gejala_diare);
    }

    // ==================== STORE VALIDATION TESTS ====================

    public function test_store_fails_without_required_fields()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), []);

        $response->assertSessionHasErrors([
            'no_registrasi',
            'nik',
            'nama_lengkap',
            'tanggal_lahir',
            'jenis_kelamin',
            'alamat_lengkap',
            'id_kec',
            'id_kel',
            'id_rt',
            'nama_pelapor',
            'id_jenis_kasus',
            'tanggal_onset',
            'tanggal_konsultasi',
            'status_rawat',
            'nama_faskes_rawat',
        ]);
    }

    public function test_store_fails_with_invalid_nik_length()
    {
        $data = $this->validCaseData(['nik' => '12345']); // too short

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);
        $response->assertSessionHasErrors('nik');
    }

    public function test_store_fails_with_duplicate_no_registrasi()
    {
        $this->createCase(['no_registrasi' => 'EPI-2026-0001']);

        $data = $this->validCaseData(['no_registrasi' => 'EPI-2026-0001']);

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);
        $response->assertSessionHasErrors('no_registrasi');
    }

    public function test_store_fails_when_tanggal_onset_after_today()
    {
        $data = $this->validCaseData([
            'tanggal_onset' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'tanggal_konsultasi' => Carbon::now()->addDays(6)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);
        $response->assertSessionHasErrors('tanggal_onset');
    }

    public function test_store_fails_when_tanggal_konsultasi_before_onset()
    {
        $data = $this->validCaseData([
            'tanggal_onset' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'tanggal_konsultasi' => Carbon::now()->subDays(5)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);
        $response->assertSessionHasErrors('tanggal_konsultasi');
    }

    public function test_store_fails_with_invalid_jenis_kelamin()
    {
        $data = $this->validCaseData(['jenis_kelamin' => 'X']);

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);
        $response->assertSessionHasErrors('jenis_kelamin');
    }

    public function test_store_requires_penyebab_kematian_when_meninggal()
    {
        $data = $this->validCaseData([
            'kondisi_akhir' => 'meninggal',
            'penyebab_kematian' => null,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);
        $response->assertSessionHasErrors('penyebab_kematian');
    }

    public function test_store_requires_tanggal_hasil_lab_when_status_lab_positif()
    {
        $data = $this->validCaseData([
            'status_lab' => 'positif',
            'tanggal_pengambilan_spesimen' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'tanggal_hasil_lab' => null,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);
        $response->assertSessionHasErrors('tanggal_hasil_lab');
    }

    public function test_store_fails_with_nonexistent_kecamatan()
    {
        $data = $this->validCaseData(['id_kec' => 99999]);

        $response = $this->actingAs($this->admin)->post(route('admin.epidemiologi.store'), $data);
        $response->assertSessionHasErrors('id_kec');
    }

    // ==================== SHOW TESTS ====================

    public function test_show_displays_case_details()
    {
        $case = $this->createCase();

        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.show', $case->id));
        $response->assertStatus(200);
        $response->assertViewIs('admin.epidemiologi.show');
        $response->assertViewHas('case');
    }

    public function test_show_returns_404_for_nonexistent_case()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.show', 99999));
        $response->assertStatus(404);
    }

    // ==================== EDIT TESTS ====================

    public function test_edit_form_displays_successfully()
    {
        $case = $this->createCase();

        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.edit', $case->id));
        $response->assertStatus(200);
        $response->assertViewIs('admin.epidemiologi.edit');
        $response->assertViewHasAll(['case', 'diseases', 'kecamatanList', 'puskesmasList', 'kelurahanList', 'rtList']);
    }

    public function test_edit_returns_404_for_nonexistent_case()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.edit', 99999));
        $response->assertStatus(404);
    }

    // ==================== UPDATE TESTS ====================

    public function test_update_modifies_case_data()
    {
        $case = $this->createCase();

        $data = $this->validCaseData([
            'no_registrasi' => $case->no_registrasi,
            'nama_lengkap' => 'Jane Updated',
            'nik' => $case->nik,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.epidemiologi.update', $case->id), $data);

        $response->assertRedirect(route('admin.epidemiologi.index'));
        $this->assertDatabaseHas('surveillance_cases', [
            'id' => $case->id,
            'nama_lengkap' => 'Jane Updated',
        ]);
    }

    public function test_update_recalculates_kategori_umur()
    {
        $case = $this->createCase();

        $data = $this->validCaseData([
            'no_registrasi' => $case->no_registrasi,
            'nik' => $case->nik,
            'tanggal_lahir' => Carbon::now()->subMonths(6)->format('Y-m-d'),
            'tanggal_onset' => Carbon::now()->subDays(1)->format('Y-m-d'),
            'tanggal_konsultasi' => Carbon::now()->format('Y-m-d'),
            'tanggal_lapor' => Carbon::now()->format('Y-m-d'),
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.epidemiologi.update', $case->id), $data);

        $case->refresh();
        $this->assertEquals('bayi', $case->kategori_umur);
    }

    // ==================== DELETE TESTS ====================

    public function test_destroy_deletes_case()
    {
        $case = $this->createCase();

        $response = $this->actingAs($this->admin)
            ->deleteJson(route('admin.epidemiologi.destroy', $case->id));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('surveillance_cases', ['id' => $case->id]);
    }

    public function test_destroy_returns_error_for_nonexistent_case()
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson(route('admin.epidemiologi.destroy', 99999));

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
    }

    // ==================== AJAX HELPER TESTS ====================

    public function test_get_kelurahan_returns_json()
    {
        Kelurahan::factory()->count(3)->create(['id_kecamatan' => $this->kecamatan->id]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.getKelurahan', $this->kecamatan->id));

        $response->assertStatus(200);
        // 3 new + 1 from setUp
        $this->assertCount(4, $response->json());
    }

    public function test_get_rt_returns_json()
    {
        Rt::factory()->count(2)->create(['id_kelurahan' => $this->kelurahan->id]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.getRt', $this->kelurahan->id));

        $response->assertStatus(200);
        // 2 new + 1 from setUp
        $this->assertCount(3, $response->json());
    }

    public function test_check_nik_returns_exists_false_for_new_nik()
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.checkNik', '9999999999999999'));

        $response->assertStatus(200);
        $response->assertJson(['exists' => false]);
    }

    public function test_check_nik_returns_exists_true_for_existing_nik()
    {
        $case = $this->createCase(['nik' => '1234567890123456']);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.checkNik', '1234567890123456'));

        $response->assertStatus(200);
        $response->assertJson(['exists' => true]);
    }

    // ==================== EXPORT TESTS ====================

    public function test_export_excel_returns_csv_response()
    {
        $this->createCase();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.epidemiologi.exportExcel'));

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/csv', $response->headers->get('content-type'));
    }

    public function test_export_excel_filters_by_disease()
    {
        $this->createCase(['id_jenis_kasus' => $this->disease->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.epidemiologi.exportExcel'), [
                'disease_id' => $this->disease->id,
            ]);

        $response->assertStatus(200);
    }

    public function test_export_pdf_returns_view()
    {
        $case = $this->createCase();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.epidemiologi.exportPdf', $case->id));

        $response->assertStatus(200);
    }

    public function test_export_pdf_returns_404_for_nonexistent_case()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.epidemiologi.exportPdf', 99999));

        $response->assertStatus(404);
    }
}
