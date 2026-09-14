<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Rt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointTautanRtTest extends TestCase
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
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id, 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah',
        ], $o));
    }

    private function kandidat(Anak $x, Anak $y): void
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        AnakKandidat::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'skor' => 1190, 'via' => 'kk',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);
    }

    public function test_kandidat_mengembalikan_pasangan_dengan_field_yang_berbeda(): void
    {
        $a = $this->anak('3201000000014001', ['nama' => 'Rafi Ahmad', 'sumber' => 'operasi_timbang']);
        $b = $this->anak('3201000000014002', ['nama' => 'Rafi Ahmad', 'alamat' => 'Jl. Lain', 'id_rt' => null]);
        $this->kandidat($a, $b);

        $res = $this->actingAs($this->userRt)->getJson(route('rt.api.kandidat'))->assertOk()->json();

        $this->assertSame(1, $res['jumlah']);
        $row = $res['rows'][0];
        $this->assertSame($a->hashid, $row['a']['id']);
        $this->assertSame($b->hashid, $row['b']['id']);
        $this->assertSame('OT', $row['a']['sumber_label']);
        $this->assertSame('kk', $row['via']);
        $this->assertContains('nik', $row['beda']);
        $this->assertContains('alamat', $row['beda']);
        $this->assertNotContains('nama', $row['beda']);
    }

    public function test_putuskan_sama_menyimpan_tautan_dan_menghilangkan_kandidat(): void
    {
        $a = $this->anak('3201000000014003');
        $b = $this->anak('3201000000014004', ['id_rt' => null]);
        $this->kandidat($a, $b);

        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.putuskan'), ['a' => $b->hashid, 'b' => $a->hashid, 'keputusan' => 'sama', 'catatan' => 'sama'])
            ->assertOk()
            ->assertJsonPath('keputusan', 'sama')
            ->assertJsonPath('status', 'diusulkan');

        $this->assertSame(1, AnakTautan::count());
        $this->assertSame(0, $this->actingAs($this->userRt)->getJson(route('rt.api.kandidat'))->json('jumlah'));
    }

    public function test_putuskan_pasangan_di_luar_cakupan_403_dan_bukan_kandidat_422(): void
    {
        $rtLain = Rt::factory()->create();
        $x = $this->anak('3201000000014005', ['id_rt' => $rtLain->id, 'id_kel' => $rtLain->id_kelurahan]);
        $y = $this->anak('3201000000014006', ['id_rt' => $rtLain->id, 'id_kel' => $rtLain->id_kelurahan]);
        $this->kandidat($x, $y);
        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.putuskan'), ['a' => $x->hashid, 'b' => $y->hashid, 'keputusan' => 'sama'])
            ->assertForbidden();

        $p = $this->anak('3201000000014007');
        $q = $this->anak('3201000000014008');
        $this->actingAs($this->userRt)
            ->postJson(route('rt.api.putuskan'), ['a' => $p->hashid, 'b' => $q->hashid, 'keputusan' => 'beda'])
            ->assertStatus(422);
    }

    public function test_halaman_rt_memuat_tab_kemungkinan_sama(): void
    {
        $this->actingAs($this->userRt)->get(route('rt.verifikasi'))
            ->assertOk()
            ->assertSee('Kemungkinan sama')
            ->assertSee(route('rt.api.kandidat'))
            ->assertSee(route('rt.api.putuskan'))
            ->assertSee('data-keputusan="sama"', false)
            ->assertSee('data-keputusan="beda"', false);
    }
}
