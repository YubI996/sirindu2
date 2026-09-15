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

    public function test_dibagi_rata_lintas_kelurahan_dan_posyandu_dan_lewati_pj_lama(): void
    {
        $kel = Kelurahan::create(['name' => 'Belimbing', 'id_kecamatan' => 1]);
        $kelLain = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        $pos = Posyandu::factory()->create();
        $anak = [];
        for ($i = 1; $i <= 5; $i++) $anak[] = $this->stunting('320100000004000'.$i, $i <= 3 ? $kel->id : $kelLain->id, $i % 2 ? $pos->id : null);
        $sudah = $this->stunting('3201000000040006', $kel->id, null, 'Kader Lama');
        $sudah->update(['pj_nip' => '197001012000011001']);
        $user = User::factory()->create(['type' => 0]);

        $r = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => 'Kader A', 'nip_pj' => '198703032012011003', 'baris' => 2],
            ['nama_pj' => 'Kader B', 'nip_pj' => '198804042013011004', 'baris' => 3],
        ], false, $user->id);

        $this->assertSame(5, $r['dialokasikan']);
        $this->assertSame(1, $r['dilewati']);
        $pj = array_map(fn ($a) => $a->fresh()->pj_nama, $anak);
        $this->assertSame(['Kader A', 'Kader B', 'Kader A', 'Kader B', 'Kader A'], $pj);
        $this->assertSame('Kader Lama', $sudah->fresh()->pj_nama);
        $this->assertSame('197001012000011001', $sudah->fresh()->pj_nip);
        $this->assertSame(['198703032012011003', '198804042013011004', '198703032012011003', '198804042013011004', '198703032012011003'], array_map(fn ($a) => $a->fresh()->pj_nip, $anak));
        $this->assertSame($user->id, (int) $anak[0]->fresh()->pj_updated_by);
        $this->assertSame([], $r['gagal']);
        $this->assertSame(2, $r['pj']);
        $this->assertSame(6, $r['anak']);
    }

    public function test_timpa_mengganti_pj_lama_dan_mengisi_anak_tanpa_posyandu(): void
    {
        $kel = Kelurahan::create(['name' => 'Kanaan', 'id_kecamatan' => 1]);
        $pos = Posyandu::factory()->create(['name' => 'Anggrek II']);
        $a = $this->stunting('3201000000040011', $kel->id, $pos->id, 'Kader Lama');
        $a->update(['pj_nip' => '197001012000011001']);
        $b = $this->stunting('3201000000040012', $kel->id, null);

        $r = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => 'Kader Baru', 'nip_pj' => '198905052014011005', 'baris' => 2],
        ], true, User::factory()->create(['type' => 0])->id);

        $this->assertSame('Kader Baru', $a->fresh()->pj_nama);
        $this->assertSame('Kader Baru', $b->fresh()->pj_nama);
        $this->assertSame('198905052014011005', $a->fresh()->pj_nip);
        $this->assertSame('198905052014011005', $b->fresh()->pj_nip);
        $this->assertSame(2, $r['dialokasikan']);
        $this->assertSame(0, $r['dilewati']);
    }

    public function test_pj_tidak_valid_dilaporkan_dan_tidak_menghentikan_yang_lain(): void
    {
        $kel = Kelurahan::create(['name' => 'Telihan', 'id_kecamatan' => 1]);
        $a = $this->stunting('3201000000040021', $kel->id);

        $r = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => 'Kader X', 'nip_pj' => 'NIP RUSAK', 'baris' => 2],
            ['nama_pj' => '', 'nip_pj' => '199107072016011007', 'baris' => 3],
            ['nama_pj' => 'Kader Z', 'nip_pj' => '199208082017011008', 'baris' => 4],
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
        $pj1 = ['nama_pj' => 'Sari', 'nip_pj' => '198501012010012001', 'baris' => 2];
        $pj2 = array_replace($pj1, ['nip_pj' => '198602022011012002', 'baris' => 3]);
        $hasil = app(PjAlokasiService::class)->alokasikan([$pj1, $pj2, $pj1], false, User::factory()->create(['type' => 0])->id);
        $this->assertSame(3, $hasil['dialokasikan'], 'Anak multi-kategori hanya dialokasikan sekali.');
        $this->assertSame(2, $hasil['pj'], 'NIP duplikat tidak menggandakan PJ.');
        $this->assertSame($pj1['nip_pj'], $stunting->fresh()->pj_nip);
        $this->assertSame($pj2['nip_pj'], $wasting->fresh()->pj_nip);
        $this->assertSame($pj1['nip_pj'], $underweight->fresh()->pj_nip);
        $this->assertNull($normal->fresh()->pj_nama);
        $this->assertNull($normal->fresh()->pj_nip);
    }

    public function test_tanpa_pj_valid_tidak_mengubah_anak(): void
    {
        $a = $this->stunting('3201000000040201', Kelurahan::factory()->create()->id);
        $hasil = app(PjAlokasiService::class)->alokasikan([
            ['nip_pj' => '', 'nama_pj' => 'Sari', 'baris' => 2],
        ], true, User::factory()->create(['type' => 0])->id);
        $this->assertSame(0, $hasil['dialokasikan']);
        $this->assertSame(0, $hasil['pj']);
        $this->assertCount(1, $hasil['gagal']);
        $this->assertNull($a->fresh()->pj_nama);
        $this->assertNull($a->fresh()->pj_nip);
    }

    public function test_nip_sama_dengan_nama_berbeda_dilaporkan(): void
    {
        $a = $this->stunting('3201000000040202', Kelurahan::factory()->create()->id);
        $hasil = app(PjAlokasiService::class)->alokasikan([
            ['nip_pj' => '198501012010012001', 'nama_pj' => 'Sari', 'baris' => 2],
            ['nip_pj' => '198501012010012001', 'nama_pj' => 'Rina', 'baris' => 3],
        ], false, User::factory()->create(['type' => 0])->id);
        $this->assertSame(1, $hasil['pj']);
        $this->assertCount(1, $hasil['gagal']);
        $this->assertStringContainsString('Baris 3', $hasil['gagal'][0]);
        $this->assertSame('Sari', $a->fresh()->pj_nama);
    }
}
