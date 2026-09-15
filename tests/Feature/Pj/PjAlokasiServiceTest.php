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
            ['kelurahan' => 'belimbing', 'posyandu' => null, 'nama_pj' => 'Kader A', 'nip_pj' => '198703032012011003', 'baris' => 2],
            ['kelurahan' => 'BELIMBING', 'posyandu' => '',   'nama_pj' => 'Kader B', 'nip_pj' => '198804042013011004', 'baris' => 3],
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
            ['kelurahan' => 'Kanaan', 'posyandu' => 'Anggrek 2', 'nama_pj' => 'Kader Baru', 'nip_pj' => '198905052014011005', 'baris' => 2],
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
            ['kelurahan' => 'Kelurahan Antah', 'posyandu' => null, 'nama_pj' => 'Kader X', 'nip_pj' => '199006062015011006', 'baris' => 2],
            ['kelurahan' => 'Telihan', 'posyandu' => 'Posyandu Tak Ada', 'nama_pj' => 'Kader Y', 'nip_pj' => '199107072016011007', 'baris' => 3],
            ['kelurahan' => 'Telihan', 'posyandu' => null, 'nama_pj' => 'Kader Z', 'nip_pj' => '199208082017011008', 'baris' => 4],
        ], false, User::factory()->create(['type' => 0])->id);

        $this->assertSame('Kader Z', $a->fresh()->pj_nama);
        $this->assertCount(2, $r['gagal']);
        $this->assertStringContainsString('Baris 2', $r['gagal'][0]);
        $this->assertStringContainsString('Baris 3', $r['gagal'][1]);
    }

    public function test_tiga_kategori_dan_nama_sama_dengan_nip_berbeda(): void
    {
        $kel = Kelurahan::factory()->create();
        $stunting = $this->stunting('3201000000040101', $kel->id);
        $wasting = $this->stunting('3201000000040102', $kel->id);
        $underweight = $this->stunting('3201000000040103', $kel->id);
        $normal = $this->stunting('3201000000040104', $kel->id);
        $wasting->latestDataAnak->update(['zscore_pb_u' => 0, 'zscore_bb_pb' => -2.5]);
        $underweight->latestDataAnak->update(['zscore_bb_u' => -2.5]); // dua kategori
        $normal->latestDataAnak->update(['zscore_pb_u' => 0]);
        $pj1 = ['kelurahan' => $kel->name, 'posyandu' => null, 'nama_pj' => 'Sari', 'nip_pj' => '198501012010012001', 'baris' => 2];
        $pj2 = array_replace($pj1, ['nip_pj' => '198602022011012002', 'baris' => 3]);
        $hasil = app(PjAlokasiService::class)->alokasikan([$pj1, $pj2, $pj1], false, User::factory()->create(['type' => 0])->id);
        $this->assertSame(3, $hasil['dialokasikan'], 'Anak multi-kategori hanya dialokasikan sekali.');
        $this->assertSame(2, $hasil['wilayah'][0]['pj'], 'NIP duplikat tidak menggandakan PJ.');
        $this->assertSame($pj1['nip_pj'], $stunting->fresh()->pj_nip);
        $this->assertSame($pj2['nip_pj'], $wasting->fresh()->pj_nip);
        $this->assertSame($pj1['nip_pj'], $underweight->fresh()->pj_nip);
        $this->assertNull($normal->fresh()->pj_nama);
        $this->assertNull($normal->fresh()->pj_nip);
    }
}
