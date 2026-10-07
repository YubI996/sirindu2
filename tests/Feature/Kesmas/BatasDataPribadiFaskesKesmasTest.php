<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Puskesmas;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Akun `imunisasi_faskes` melihat ANGKA dasbor se-kota, tetapi data pribadi anak (NIK, nama orang tua,
 * penyakit penyerta, riwayat kunjungan — registri & Export Kesmas) hanya untuk anak di catchment
 * puskesmas-nya (audit Kesmas 2026-10-06, SEC-001; keputusan pemilik: pisahkan agregat dari data pribadi).
 * Akun RS atau tanpa puskesmas tak punya wilayah → data pribadi ditolak (fail-closed).
 * Admin/superadmin tidak berubah.
 */
class BatasDataPribadiFaskesKesmasTest extends TestCase
{
    use RefreshDatabase;

    private Kecamatan $kec;
    private Kelurahan $kelUtara;     // catchment Bontang Utara 1
    private Kelurahan $kelLestari;   // catchment Bontang Lestari
    private Puskesmas $pkmUtara;
    private Puskesmas $pkmLestari;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);

        $this->kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $this->kelUtara = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $this->kec->id]);
        $this->kelLestari = Kelurahan::create(['name' => 'Bontang Lestari', 'id_kecamatan' => $this->kec->id]);
        $this->pkmUtara = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $this->kec->id]);
        $this->pkmLestari = Puskesmas::create(['name' => 'Bontang Lestari', 'id_kecamatan' => $this->kec->id]);

        $this->anak('6474010101230001', 'Anak Utara', $this->kelUtara);
        $this->anak('6474010101230002', 'Anak Lestari', $this->kelLestari);
    }

    private function anak(string $nik, string $nama, Kelurahan $kel): Anak
    {
        return Anak::create([
            'nama' => $nama, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => now()->subMonths(10)->toDateString(), 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $kel->id, 'sasaran_balita_kesmas' => 1,
        ]);
    }

    private function faskes(array $atribut = []): User
    {
        return User::factory()->create(array_merge(['type' => 0, 'role' => 'imunisasi_faskes'], $atribut));
    }

    private function faskesUtara(): User
    {
        return $this->faskes(['faskes_type' => 'puskesmas', 'id_puskesmas' => $this->pkmUtara->id]);
    }

    /** NIK pada sheet "Per Anak" dari unduhan Export Kesmas lewat HTTP. */
    private function nikDiExport(User $user, array $query = []): array
    {
        $resp = $this->actingAs($user)->get(route('admin.export.kesmas.download', $query))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'kesmas');
        try {
            file_put_contents($path, $resp->streamedContent());
            $sheet = IOFactory::load($path)->getSheet(0);
            $nik = [];
            foreach ($sheet->toArray(null, true, true, false) as $i => $baris) {
                if ($i > 0 && !empty($baris[0])) {
                    $nik[] = (string) $baris[0];
                }
            }

            return $nik;
        } finally {
            @unlink($path);
        }
    }

    public function test_registri_akun_puskesmas_hanya_anak_catchment_sendiri(): void
    {
        $r = $this->actingAs($this->faskesUtara())->getJson(route('admin.kesmas.registri'))->assertOk();

        $this->assertSame(['Anak Utara'], $r->json('data.*.nama'));
        $this->assertSame(1, $r->json('total'));
    }

    public function test_akun_puskesmas_tak_bisa_melebarkan_registri_lewat_parameter(): void
    {
        $akun = $this->faskesUtara();

        // memilih puskesmas lain tidak mengganti batas milik akun
        $this->actingAs($akun)->getJson(route('admin.kesmas.registri', ['id_puskesmas' => $this->pkmLestari->id]))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.nama', 'Anak Utara');
        // memilih kelurahan di luar catchment → kosong, bukan data kelurahan itu
        $this->actingAs($akun)->getJson(route('admin.kesmas.registri', ['id_kelurahan' => $this->kelLestari->id]))
            ->assertOk()->assertJsonPath('total', 0);
        // pencarian nama anak di luar catchment tidak membocorkannya
        $this->actingAs($akun)->getJson(route('admin.kesmas.registri', ['q' => 'Lestari']))
            ->assertOk()->assertJsonPath('total', 0);
    }

    public function test_export_akun_puskesmas_hanya_anak_catchment_sendiri(): void
    {
        $akun = $this->faskesUtara();

        $this->assertSame(['6474010101230001'], $this->nikDiExport($akun));
        // filter puskesmas/kelurahan lain tak melebarkan: batas akun selalu ikut (AND)
        $this->assertSame([], $this->nikDiExport($akun, ['id_kel' => $this->kelLestari->id]));
        $this->assertSame(['6474010101230001'], $this->nikDiExport($akun, ['id_kec' => $this->kec->id]));
    }

    public function test_akun_rs_atau_tanpa_puskesmas_ditolak_untuk_data_pribadi_tetapi_dasbor_terbuka(): void
    {
        $tanpaWilayah = [
            $this->faskes(['faskes_type' => 'rs']),
            $this->faskes(['faskes_type' => 'puskesmas', 'id_puskesmas' => null]),
            $this->faskes(),
        ];

        foreach ($tanpaWilayah as $akun) {
            $this->actingAs($akun)->getJson(route('admin.kesmas.registri'))->assertForbidden();
            $this->actingAs($akun)->get(route('admin.export.kesmas.index'))->assertForbidden();
            $this->actingAs($akun)->get(route('admin.export.kesmas.download'))->assertForbidden();

            $html = $this->actingAs($akun)->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();
            $this->assertStringNotContainsString('data-blok="registri"', $html, 'registri tak boleh dirender untuk akun tanpa wilayah');
        }
    }

    public function test_dasbor_akun_puskesmas_memuat_registri_terbatas_dan_agregat_tetap_ada(): void
    {
        $html = $this->actingAs($this->faskesUtara())->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-blok="registri"', $html);
        $this->assertStringContainsString('data-blok="layanan"', $html);
    }

    public function test_admin_dan_superadmin_tetap_melihat_seluruh_kota(): void
    {
        foreach ([User::factory()->create(['type' => 1]), User::factory()->create(['type' => 0])] as $akun) {
            $this->actingAs($akun)->getJson(route('admin.kesmas.registri'))->assertOk()->assertJsonPath('total', 2);
            $this->assertEqualsCanonicalizing(['6474010101230001', '6474010101230002'], $this->nikDiExport($akun));
        }
    }
}
