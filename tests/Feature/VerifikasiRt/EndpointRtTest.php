<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointRtTest extends TestCase
{
    use RefreshDatabase;

    private Rt $rt;
    private User $userRt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rt     = Rt::factory()->create();
        $this->userRt = User::factory()->create(['type' => 2, 'role' => 'rt', 'id_rt' => $this->rt->id, 'id_kel' => $this->rt->id_kelurahan]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 2, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id, 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah',
        ], $o));
    }

    public function test_warga_mengembalikan_baris_lengkap_dengan_badge_sumber(): void
    {
        $anak = $this->anak('3201000000009101');
        $this->anak('3201000000009102', ['id_rt' => Rt::factory()->create()->id]);

        $res = $this->actingAs($this->userRt)->getJson(route('rt.api.warga'))->assertOk()->json();

        $this->assertCount(1, $res['rows']);
        $row = $res['rows'][0];
        $this->assertSame($anak->hashid, $row['id']);
        $this->assertSame('3201000000009101', $row['nik'], 'RT melihat NIK lengkap (spec §8)');
        $this->assertSame('Capil', $row['sumber_label']);
        $this->assertSame('P', $row['jk']);
        $this->assertNull($row['verif_status']);
        $this->assertSame(['total' => 1, 'diverifikasi' => 0], $res['progres']);
        $this->assertArrayNotHasKey('bb', $row);
    }

    public function test_tanpa_rt_hanya_sekelurahan_tanpa_id_rt(): void
    {
        $a = $this->anak('3201000000009103', ['id_rt' => null]);
        $this->anak('3201000000009104');

        $ids = collect($this->actingAs($this->userRt)->getJson(route('rt.api.tanpaRt'))->assertOk()->json('rows'))->pluck('id');
        $this->assertSame([$a->hashid], $ids->all());
    }

    public function test_usulkan_menyimpan_dan_mengembalikan_tag(): void
    {
        $anak = $this->anak('3201000000009105');

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'pindah', 'catatan' => 'ke Samarinda'])
            ->assertOk()
            ->assertJsonPath('verif_status', 'pindah')
            ->assertJsonPath('verif_label', 'Pindah')
            ->assertJsonPath('verif_reviu', 'diusulkan')
            ->assertJsonPath('hilang', false);

        $this->assertSame('pindah', $anak->fresh()->verif_rt_status);
    }

    public function test_usulkan_bukan_rt_ini_menandai_hilang(): void
    {
        $anak = $this->anak('3201000000009106', ['id_rt' => null]);

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'bukan_rt_ini'])
            ->assertOk()
            ->assertJsonPath('hilang', true);
    }

    public function test_usulkan_anak_rt_lain_ditolak_403(): void
    {
        $anak = $this->anak('3201000000009107', ['id_rt' => Rt::factory()->create()->id]);

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'berdomisili'])
            ->assertForbidden();
    }

    public function test_status_tidak_valid_422(): void
    {
        $anak = $this->anak('3201000000009108');

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.usulkan', $anak), ['status' => 'hilang'])
            ->assertStatus(422);
    }

    public function test_halaman_rt_merender_identitas_rt(): void
    {
        $this->actingAs($this->userRt)->get(route('rt.verifikasi'))
            ->assertOk()
            ->assertSee($this->rt->name)
            ->assertSee(route('rt.api.warga'));
    }

    public function test_superadmin_pratinjau_dengan_parameter_rt(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('rt.verifikasi'))->assertForbidden();
        $this->actingAs($super)->get(route('rt.verifikasi', ['rt' => $this->rt->id]))->assertOk()->assertSee($this->rt->name);
        $this->actingAs($super)->getJson(route('rt.api.warga', ['rt' => $this->rt->id]))->assertOk();
    }
}
