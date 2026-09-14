<?php

namespace Tests\Feature\VerifikasiRt;

use App\Jobs\PindaiIdentitasJob;
use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\User;
use App\Services\TautanIdentitasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReviuTautanTest extends TestCase
{
    use RefreshDatabase;

    private Rt $rt;
    private User $userRt;
    private TautanIdentitasService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(TautanIdentitasService::class);
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id,
        ], $o));
    }

    private function tautan(string $nikA, string $nikB, string $keputusan = 'sama'): AnakTautan
    {
        $a = $this->anak($nikA);
        $b = $this->anak($nikB, ['id_rt' => null]);
        [$x, $y] = AnakTautan::urut($a->id, $b->id);
        AnakKandidat::create(['id_anak_a' => $x, 'id_anak_b' => $y, 'skor' => 190, 'via' => 'ortu',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);
        return $this->svc->putuskan($a->id, $b->id, $this->rt, $this->userRt, $keputusan);
    }

    public function test_tab_tautan_menampilkan_pasangan_kelurahan_peninjau(): void
    {
        $t = $this->tautan('3201000000015001', '3201000000015002');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);

        $this->actingAs($faskes)->get(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->assertOk()
            ->assertSee('Anak 3201000000015001')
            ->assertSee('Anak 3201000000015002')
            ->assertSee(route('admin.verifikasiRt.tinjauTautan', $t));
    }

    public function test_setujui_tautan_sama_menambah_menunggu_gabung(): void
    {
        $t = $this->tautan('3201000000015003', '3201000000015004');
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->from(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->post(route('admin.verifikasiRt.tinjauTautan', $t), ['setuju' => 1])
            ->assertRedirect(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->assertSessionHas('success');

        $this->assertSame('disetujui', $t->fresh()->status);
        $this->actingAs($super)->get(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->assertSee('1 tautan menunggu penggabungan');
    }

    public function test_tolak_tautan_membuka_kembali_kandidat(): void
    {
        $t = $this->tautan('3201000000015005', '3201000000015006', 'beda');
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->post(route('admin.verifikasiRt.tinjauTautan', $t), ['setuju' => 0, 'catatan' => 'cek KK']);

        $this->assertSame('ditolak', $t->fresh()->status);
        $this->assertCount(1, $this->svc->kandidatUntukRt($this->rt));
    }

    public function test_faskes_kelurahan_lain_403(): void
    {
        $t = $this->tautan('3201000000015007', '3201000000015008');
        $lain = User::factory()->create(['type' => 1, 'id_kel' => Kelurahan::factory()->create()->id]);

        $this->actingAs($lain)->post(route('admin.verifikasiRt.tinjauTautan', $t), ['setuju' => 1])->assertForbidden();
    }

    public function test_pindai_ulang_hanya_superadmin_dan_mengantrekan_job(): void
    {
        Queue::fake();
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);
        $this->actingAs($faskes)->post(route('admin.verifikasiRt.pindai'))->assertForbidden();

        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->from(route('admin.verifikasiRt.index'))
            ->post(route('admin.verifikasiRt.pindai'))
            ->assertRedirect(route('admin.verifikasiRt.index', ['tab' => 'tautan']))
            ->assertSessionHas('success');

        Queue::assertPushed(PindaiIdentitasJob::class, 1);
    }
}
