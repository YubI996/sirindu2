<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->create(['type' => 0]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah'], $o));
    }

    private function tautanSetuju(Anak $x, Anak $y): AnakTautan
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        return AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => 'sama', 'status' => 'disetujui',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
    }

    public function test_hanya_superadmin(): void
    {
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => 1]);
        $ot = $this->anak('3201000000020001', ['sumber' => 'operasi_timbang']);
        $t = $this->tautanSetuju($ot, $this->anak('3201000000020002'));

        $this->actingAs($faskes)->get(route('admin.gabung.index'))->assertForbidden();
        $this->actingAs($faskes)->get(route('admin.gabung.show', $t))->assertForbidden();
        $this->actingAs($faskes)->post(route('admin.gabung.store', $t))->assertForbidden();
    }

    public function test_index_menampilkan_antrean_dan_show_pemilih_kolom(): void
    {
        $ot  = $this->anak('3201000000020003', ['sumber' => 'operasi_timbang', 'nama' => 'Nama OT']);
        $cap = $this->anak('3201000000020004', ['nama' => 'Nama Capil']);
        $t = $this->tautanSetuju($ot, $cap);

        $this->actingAs($this->super)->get(route('admin.gabung.index'))
            ->assertOk()->assertSee('Nama OT')->assertSee(route('admin.gabung.show', $t));

        $this->actingAs($this->super)->get(route('admin.gabung.show', $t))
            ->assertOk()
            ->assertSee('Nama Capil')
            ->assertSee('name="pilihan[nama]"', false)
            ->assertSee('name="pilihan[nik]"', false)
            ->assertSee('Operasi Timbang');
    }

    public function test_store_menggabungkan_sesuai_pilihan(): void
    {
        $ot  = $this->anak('3201000000020005', ['sumber' => 'operasi_timbang', 'nama' => 'Nama OT']);
        $cap = $this->anak('3201000000020006', ['nama' => 'Nama Capil']);
        $t = $this->tautanSetuju($ot, $cap);
        $sisiCap = $ot->id < $cap->id ? 'b' : 'a';

        $this->actingAs($this->super)->from(route('admin.gabung.show', $t))
            ->post(route('admin.gabung.store', $t), ['pilihan' => ['nama' => $sisiCap]])
            ->assertRedirect(route('admin.gabung.index'))
            ->assertSessionHas('success');

        $this->assertSame('Nama Capil', $ot->fresh()->nama);
        $this->assertNull(Anak::find($cap->id));
        $this->assertSame(1, AnakMergeLog::count());
    }

    public function test_dua_ot_diarahkan_kembali_dengan_error(): void
    {
        $x = $this->anak('3201000000020007', ['sumber' => 'operasi_timbang']);
        $y = $this->anak('3201000000020008', ['sumber' => 'operasi_timbang']);
        $t = $this->tautanSetuju($x, $y);

        $this->actingAs($this->super)->get(route('admin.gabung.show', $t))
            ->assertRedirect(route('admin.gabung.index'))->assertSessionHas('error');
    }

    public function test_batalkan_dari_log(): void
    {
        $ot  = $this->anak('3201000000020009', ['sumber' => 'operasi_timbang']);
        $cap = $this->anak('3201000000020010');
        $t = $this->tautanSetuju($ot, $cap);
        $this->actingAs($this->super)->post(route('admin.gabung.store', $t));
        $log = AnakMergeLog::sole();

        $this->actingAs($this->super)->get(route('admin.gabung.index'))->assertSee(route('admin.gabung.batalkan', $log));
        $this->actingAs($this->super)->post(route('admin.gabung.batalkan', $log))
            ->assertRedirect(route('admin.gabung.index'))->assertSessionHas('success');

        $this->assertNotNull(Anak::find($cap->id));
        $this->assertNotNull($log->fresh()->dibatalkan_at);
    }
}
