<?php

namespace Tests\Feature\Kesmas;

use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class KesmasSwarmAksesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
    }

    public function test_semua_pintu_kesmas_menerapkan_matriks_peran_yang_sama(): void
    {
        // Faskes imunisasi: dasbor (angka) se-kota tetap terbuka, tetapi data pribadi (registri, Export)
        // butuh puskesmas — tanpa itu ditolak. Batas wilayahnya dikunci BatasDataPribadiFaskesKesmasTest.
        $roles = [
            ['type' => 0, 'role' => null, 'status' => 200],
            ['type' => 1, 'role' => null, 'status' => 200],
            ['type' => 2, 'role' => 'superadmin', 'status' => 200],
            ['type' => 0, 'role' => 'imunisasi_faskes', 'status' => 200, 'kecuali' => [
                'admin.kesmas.registri' => 403, 'admin.export.kesmas.index' => 403, 'admin.export.kesmas.download' => 403,
            ]],
            ['type' => 0, 'role' => 'surveilans_puskesmas', 'status' => 403],
            ['type' => 0, 'role' => 'surveilans_rs', 'status' => 403],
            ['type' => 2, 'role' => 'rt', 'status' => 403],
            ['type' => 2, 'role' => null, 'status' => 403],
        ];
        Excel::fake();

        foreach ($roles as $role) {
            $user = User::factory()->create(['type' => $role['type'], 'role' => $role['role']]);
            $this->actingAs($user);
            foreach (['admin.kesmas.dashboard', 'admin.kesmas.registri', 'admin.export.kesmas.index', 'admin.export.kesmas.download'] as $route) {
                $this->getJson(route($route))->assertStatus($role['kecuali'][$route] ?? $role['status']);
            }
        }
    }

    public function test_tamu_tidak_bisa_membuka_html_json_atau_unduhan(): void
    {
        foreach (['admin.kesmas.dashboard', 'admin.export.kesmas.index', 'admin.export.kesmas.download'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->getJson(route('admin.kesmas.registri'))->assertUnauthorized();
    }

    public function test_input_array_ditolak_sebelum_dipakai_query_atau_view(): void
    {
        $this->actingAs(User::factory()->create(['type' => 1]));
        foreach (['tahun', 'periode', 'id_kecamatan', 'id_kelurahan', 'id_rt', 'id_posyandu', 'id_puskesmas', 'usia', 'q', 'status_gizi', 'page'] as $key) {
            $this->getJson(route('admin.kesmas.registri', [$key => ['tak-sah']]))
                ->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }

    public function test_filter_kosong_memakai_default_dan_tidak_menghasilkan_error(): void
    {
        $this->actingAs(User::factory()->create(['type' => 1]));
        $filter = array_fill_keys(['tahun', 'periode', 'id_kecamatan', 'id_kelurahan', 'id_rt', 'id_posyandu', 'id_puskesmas', 'usia'], '');
        $this->get(route('admin.kesmas.dashboard', $filter))->assertOk()
            ->assertViewHas('periode', fn ($p) => $p->tahun() === now()->year && $p->kode() === 'tahun')
            ->assertViewHas('usia', 'semua');
        $this->getJson(route('admin.kesmas.registri', array_merge($filter, ['q' => '', 'status_gizi' => '', 'page' => ''])))
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('total', 0)
            ->assertJsonPath('page', 1)->assertJsonPath('per_page', 20);
    }
}
