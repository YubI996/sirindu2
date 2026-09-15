<?php

namespace Tests\Feature\Pj;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\User;
use App\Services\PjAlokasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PjAlokasiServiceTest extends TestCase
{
    use RefreshDatabase;

    private function stunting(string $nik, int $idKel, ?int $idPos = null, ?string $pj = null): Anak
    {
        $a = Anak::create(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $idKel, 'id_posyandu' => $idPos, 'pj_nama' => $pj]);
        DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => now()->subDays(5)->toDateString(), 'bln' => 24, 'posisi' => 'berdiri', 'tb' => 85, 'bb' => 10,
            'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => -2.5, 'zscore_bb_pb' => 0, 'sumber' => 'operasi_timbang']);
        return $a;
    }

    public function test_dibagi_rata_bergilir_per_kelurahan_dan_lewati_yang_sudah_punya_pj(): void
    {
        $kel = Kelurahan::create(['name' => 'Belimbing', 'id_kecamatan' => 1]);
        $anak = [];
        for ($i = 1; $i <= 5; $i++) $anak[] = $this->stunting('320100000004000'.$i, $kel->id);
        $sudah = $this->stunting('3201000000040006', $kel->id, null, 'Kader Lama');
        $user = User::factory()->create(['type' => 0]);

        $r = app(PjAlokasiService::class)->alokasikan([
            ['kelurahan' => 'belimbing', 'posyandu' => null, 'nama_pj' => 'Kader A', 'baris' => 2],
            ['kelurahan' => 'BELIMBING', 'posyandu' => '',   'nama_pj' => 'Kader B', 'baris' => 3],
        ], false, $user->id);

        $this->assertSame(5, $r['dialokasikan']);
        $this->assertSame(1, $r['dilewati']);
        $pj = array_map(fn ($a) => $a->fresh()->pj_nama, $anak);
        $this->assertSame(['Kader A', 'Kader B', 'Kader A', 'Kader B', 'Kader A'], $pj);
        $this->assertSame('Kader Lama', $sudah->fresh()->pj_nama);
        $this->assertSame($user->id, (int) $anak[0]->fresh()->pj_updated_by);
        $this->assertSame([], $r['gagal']);
        $this->assertCount(1, $r['wilayah']);
        $this->assertSame(2, $r['wilayah'][0]['pj']);
    }

    public function test_timpa_mengganti_pj_lama_dan_posyandu_dicocokkan(): void
    {
        $kel = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        $pos = Posyandu::factory()->create(['name' => 'Anggrek II']);
        $a = $this->stunting('3201000000040011', $kel->id, $pos->id, 'Kader Lama');
        $b = $this->stunting('3201000000040012', $kel->id, null); // posyandu lain → tidak kena baris posyandu

        $r = app(PjAlokasiService::class)->alokasikan([
            ['kelurahan' => 'Kanaan', 'posyandu' => 'Anggrek 2', 'nama_pj' => 'Kader Baru', 'baris' => 2],
        ], true, User::factory()->create(['type' => 0])->id);

        $this->assertSame('Kader Baru', $a->fresh()->pj_nama);
        $this->assertNull($b->fresh()->pj_nama);
        $this->assertSame(1, $r['dialokasikan']);
    }

    public function test_wilayah_tak_dikenal_dilaporkan_dan_tidak_menghentikan_yang_lain(): void
    {
        $kel = Kelurahan::create(['name' => 'Telihan', 'id_kecamatan' => 1]);
        $a = $this->stunting('3201000000040021', $kel->id);

        $r = app(PjAlokasiService::class)->alokasikan([
            ['kelurahan' => 'Kelurahan Antah', 'posyandu' => null, 'nama_pj' => 'Kader X', 'baris' => 2],
            ['kelurahan' => 'Telihan', 'posyandu' => 'Posyandu Tak Ada', 'nama_pj' => 'Kader Y', 'baris' => 3],
            ['kelurahan' => 'Telihan', 'posyandu' => null, 'nama_pj' => 'Kader Z', 'baris' => 4],
        ], false, User::factory()->create(['type' => 0])->id);

        $this->assertSame('Kader Z', $a->fresh()->pj_nama);
        $this->assertCount(2, $r['gagal']);
        $this->assertStringContainsString('Baris 2', $r['gagal'][0]);
        $this->assertStringContainsString('Baris 3', $r['gagal'][1]);
    }
}
