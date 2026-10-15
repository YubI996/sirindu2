<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/** Spec akun Kesmas §3 "Penerapan kunci" & §7: query string keluar lingkup ditimpa kunci akun. */
class KunciLingkupKesmasTest extends TestCase
{
    use RefreshDatabase;

    private Kelurahan $kelA;
    private Kelurahan $kelB;
    private Posyandu $posA;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);

        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $this->kelA = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $this->kelB = Kelurahan::create(['name' => 'Lok Tuan', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $this->posA = Posyandu::factory()->create(['id_puskesmas' => $pkm->id]);

        $this->anak('Anak A1', '6474010101230001', $this->kelA, $this->posA->id);
        $this->anak('Anak A2', '6474010101230002', $this->kelA, null);
        $this->anak('Anak B1', '6474010101230003', $this->kelB, null);
    }

    private function anak(string $nama, string $nik, Kelurahan $kel, ?int $pos): void
    {
        Anak::create([
            'nama' => $nama, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => now()->subMonths(10)->toDateString(), 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id, 'id_posyandu' => $pos, 'sasaran_balita_kesmas' => 1,
        ]);
    }

    private function namaRegistri(User $user, array $query = []): array
    {
        $json = $this->actingAs($user)->getJson(route('admin.kesmas.registri', $query))->assertOk()->json('data');

        return collect($json)->pluck('nama')->sort()->values()->all();
    }

    public function test_kelurahan_tidak_bisa_melihat_kelurahan_lain_lewat_query_string(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kelA->id]);

        $this->assertSame(['Anak A1', 'Anak A2'], $this->namaRegistri($user));
        $this->assertSame(['Anak A1', 'Anak A2'], $this->namaRegistri($user, ['id_kelurahan' => $this->kelB->id]));

        $this->actingAs($user)->get(route('admin.kesmas.dashboard', ['id_kelurahan' => $this->kelB->id]))
            ->assertOk()
            ->assertViewHas('filters', fn ($f) => $f['id_kelurahan'] === $this->kelA->id);
    }

    public function test_kader_hanya_posyandunya(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'kader', 'id_posyandu' => $this->posA->id]);

        // Posyandu lain yang nyata (id yang tak ada ditolak validasi 422, bukan soal lingkup).
        $posB = Posyandu::factory()->create(['id_puskesmas' => $this->posA->id_puskesmas]);

        $this->assertSame(['Anak A1'], $this->namaRegistri($user, ['id_posyandu' => $posB->id]));
    }

    public function test_dropdown_wilayah_terkunci_dan_tombol_sesuai_izin(): void
    {
        $kader = User::factory()->create(['type' => 2, 'role' => 'kader', 'id_posyandu' => $this->posA->id]);
        $html = $this->actingAs($kader)->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select name="id_posyandu" id="filterPos"[^>]*disabled/', $html);
        $this->assertStringNotContainsString(route('admin.export.kesmas.index'), $html, 'kader tak boleh export');
        $this->assertStringNotContainsString(route('admin.imunisasiDashboard'), $html, 'kader tak boleh dasbor imunisasi');

        $kel = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kelA->id]);
        $this->actingAs($kel)->get(route('admin.kesmas.dashboard'))->assertOk()
            ->assertSee(route('admin.export.kesmas.index'), false);
    }

    public function test_export_kelurahan_hanya_kelurahannya_walau_url_meminta_lain(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kelA->id]);

        $response = $this->actingAs($user)->get(route('admin.export.kesmas.download', ['id_kel' => $this->kelB->id]));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'kesmas');
        file_put_contents($path, $response->streamedContent());
        $reader = new Reader();
        $reader->open($path);
        $nama = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                $nama[] = implode('|', array_map('strval', $row->toArray()));
            }
            break; // sheet Per Anak
        }
        $reader->close();
        @unlink($path);

        $isi = implode("\n", $nama);
        $this->assertStringContainsString('Anak A1', $isi);
        $this->assertStringContainsString('Anak A2', $isi);
        $this->assertStringNotContainsString('Anak B1', $isi);
    }

    public function test_halaman_export_kelurahan_menampilkan_wilayah_terkunci(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'kelurahan', 'id_kel' => $this->kelA->id]);

        $this->actingAs($user)->get(route('admin.export.kesmas.index'))->assertOk()
            ->assertSee('Kel. Bontang Baru')
            ->assertDontSee('name="id_kel"', false);
    }

    public function test_admin_tidak_terkunci(): void
    {
        $admin = User::factory()->create(['type' => 1, 'role' => null]);
        $this->assertSame(['Anak B1'], $this->namaRegistri($admin, ['id_kelurahan' => $this->kelB->id]));
    }
}
