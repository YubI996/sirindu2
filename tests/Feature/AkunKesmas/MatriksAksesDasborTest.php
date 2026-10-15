<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\RumahSakit;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Spec akun Kesmas §2 & §4: setiap peran baru × setiap pintu dasbor. */
class MatriksAksesDasborTest extends TestCase
{
    use RefreshDatabase;

    private array $lingkup;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);

        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $pos = Posyandu::factory()->create(['id_puskesmas' => $pkm->id]);
        // users.id_rs punya foreign key ke rumah_sakits — id fiktif akan ditolak MySQL.
        $rs = RumahSakit::create(['name' => 'RSUD Uji', 'jenis_rs' => 'RSUD', 'is_active' => true]);
        $this->lingkup = ['id_kel' => $kel->id, 'id_posyandu' => $pos->id, 'id_puskesmas' => $pkm->id, 'id_rs' => $rs->id, 'kel' => $kel->id];
    }

    private function akun(string $role): User
    {
        $l = $this->lingkup;
        $attr = match ($role) {
            'kelurahan' => ['id_kel' => $l['id_kel']],
            'kader' => ['id_posyandu' => $l['id_posyandu']],
            'kesmas_puskesmas' => ['id_puskesmas' => $l['id_puskesmas']],
            'kesmas_rs' => ['id_rs' => $l['id_rs']],
            default => [],
        };

        return User::factory()->create(['type' => 2, 'role' => $role] + $attr);
    }

    /** @return array<string, array{0:string,1:array}> nama kunci => [nama rute, parameter] */
    private function pintu(): array
    {
        return [
            'kesmas'          => ['admin.kesmas.dashboard', []],
            'kesmas_registri' => ['admin.kesmas.registri', []],
            'export_kesmas'   => ['admin.export.kesmas.index', []],
            'imunisasi'       => ['admin.imunisasiDashboard', []],
            'imun_sasaran'    => ['admin.imunisasiDashboard.sasaran', ['kelompok' => 'bbl']],
            'imun_export'     => ['admin.imunisasiDashboard.sasaranExport', ['kelompok' => 'bbl']],
            'timbang'         => ['admin.timbang.dashboard', []],
            'timbang_ring'    => ['admin.timbang.ringkasan', []],
            'timbang_daftar'  => ['admin.timbang.daftar', []],
            'timbang_export'  => ['admin.timbang.daftar.export', []],
            'spm'             => ['admin.spm.dashboard', []],
            'rt_by_kel'       => ['admin.getRtByKelAnak', ['id' => $this->lingkup['kel']]],
            'anak'            => ['admin.anak', []],
        ];
    }

    public static function harapan(): array
    {
        $tolakSemua = array_fill_keys(['kesmas', 'kesmas_registri', 'export_kesmas', 'imunisasi', 'imun_sasaran', 'imun_export', 'timbang', 'timbang_ring', 'timbang_daftar', 'timbang_export', 'spm', 'rt_by_kel', 'anak'], 403);
        $hanyaKesmas = array_merge($tolakSemua, ['kesmas' => 200, 'kesmas_registri' => 200, 'rt_by_kel' => 200]);

        return [
            'pimpinan' => ['pimpinan', array_merge($tolakSemua, [
                'kesmas' => 200, 'kesmas_registri' => 200, 'imunisasi' => 200, 'imun_sasaran' => 200,
                'timbang' => 200, 'timbang_ring' => 200, 'timbang_daftar' => 200, 'spm' => 200, 'rt_by_kel' => 200,
            ])],
            'kelurahan' => ['kelurahan', array_merge($hanyaKesmas, ['export_kesmas' => 200])],
            'kader' => ['kader', $hanyaKesmas],
            'kesmas_puskesmas' => ['kesmas_puskesmas', $hanyaKesmas],
            'kesmas_rs' => ['kesmas_rs', array_merge($tolakSemua, ['kesmas' => 200, 'rt_by_kel' => 200])],
            'sarkes' => ['sarkes', $tolakSemua],
        ];
    }

    /** @dataProvider harapan */
    public function test_matriks(string $role, array $harapan): void
    {
        $user = $this->akun($role);
        foreach ($this->pintu() as $kunci => [$rute, $param]) {
            $status = $this->actingAs($user)->getJson(route($rute, $param))->status();
            $this->assertSame($harapan[$kunci], $status, "$role → $kunci ($rute)");
        }
    }

    public function test_tautan_registri_hanya_bila_registri_tampil(): void
    {
        // RS tak boleh melihat registri → "balita perlu perhatian" jadi teks biasa, bukan tautan mati ke #registri.
        $rs = $this->actingAs($this->akun('kesmas_rs'))->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('href="#registri"', $rs);
        $this->assertStringContainsString('balita perlu perhatian', $rs);

        $pimpinan = $this->actingAs($this->akun('pimpinan'))->get(route('admin.kesmas.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('href="#registri"', $pimpinan);
    }

    public function test_tulis_ot_ditolak_untuk_pimpinan(): void
    {
        $user = $this->akun('pimpinan');
        $this->actingAs($user)->postJson(route('admin.timbang.publikasi'), ['aktif' => 1])->assertForbidden();
    }

    public function test_admin_lama_tetap_membuka_semua_pintu(): void
    {
        $admin = User::factory()->create(['type' => 1, 'role' => null]);
        foreach (['admin.kesmas.dashboard', 'admin.imunisasiDashboard', 'admin.timbang.dashboard', 'admin.spm.dashboard', 'admin.export.kesmas.index'] as $rute) {
            $this->actingAs($admin)->get(route($rute))->assertOk();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('admin.timbang.dashboard'))->assertRedirect(route('login'));
        $this->getJson(route('admin.kesmas.registri'))->assertUnauthorized();
    }
}
