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

    /** Kelurahan sasaran alokasi otomatis (config pj.kelurahan_sasaran). */
    private function lestari(): Kelurahan
    {
        return Kelurahan::create(['name' => 'Bontang Lestari', 'id_kecamatan' => 3]);
    }

    private function stunting(string $nik, int $idKel, ?int $idPos = null, ?string $pj = null): Anak
    {
        $a = Anak::create(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $idKel, 'id_posyandu' => $idPos, 'pj_nama' => $pj]);
        DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => now()->subDays(5)->toDateString(), 'bln' => 24, 'posisi' => 'berdiri', 'tb' => 85, 'bb' => 10,
            'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => -2.5, 'zscore_bb_pb' => 0, 'sumber' => 'operasi_timbang']);
        return $a;
    }

    public function test_dibagi_rata_lintas_posyandu_dan_lewati_pj_lama(): void
    {
        $kel = $this->lestari();
        $pos = Posyandu::factory()->create();
        $anak = [];
        for ($i = 1; $i <= 5; $i++) $anak[] = $this->stunting('320100000004000'.$i, $kel->id, $i % 2 ? $pos->id : null);
        $sudah = $this->stunting('3201000000040006', $kel->id, null, 'Kader Lama');
        $user = User::factory()->create(['type' => 0]);

        $r = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => 'Kader A', 'baris' => 2],
            ['nama_pj' => 'Kader B', 'baris' => 3],
        ], false, $user->id);

        $this->assertSame(5, $r['dialokasikan']);
        $this->assertSame(1, $r['dilewati']);
        $pj = array_map(fn ($a) => $a->fresh()->pj_nama, $anak);
        $this->assertSame(['Kader A', 'Kader B', 'Kader A', 'Kader B', 'Kader A'], $pj);
        $this->assertSame('Kader Lama', $sudah->fresh()->pj_nama);
        $this->assertSame($user->id, (int) $anak[0]->fresh()->pj_updated_by);
        $this->assertSame([], $r['gagal']);
        $this->assertSame(2, $r['pj']);
        $this->assertSame(6, $r['anak']);
    }

    public function test_timpa_mengganti_pj_lama_dan_mengisi_anak_tanpa_posyandu(): void
    {
        $kel = $this->lestari();
        $pos = Posyandu::factory()->create(['name' => 'Anggrek II']);
        $a = $this->stunting('3201000000040011', $kel->id, $pos->id, 'Kader Lama');
        $b = $this->stunting('3201000000040012', $kel->id, null);

        $r = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => 'Kader Baru', 'baris' => 2],
        ], true, User::factory()->create(['type' => 0])->id);

        $this->assertSame('Kader Baru', $a->fresh()->pj_nama);
        $this->assertSame('Kader Baru', $b->fresh()->pj_nama);
        $this->assertSame(2, $r['dialokasikan']);
        $this->assertSame(0, $r['dilewati']);
    }

    public function test_pj_tidak_valid_dilaporkan_dan_tidak_menghentikan_yang_lain(): void
    {
        $kel = $this->lestari();
        $a = $this->stunting('3201000000040021', $kel->id);

        $r = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => str_repeat('A', Anak::PJ_NAMA_MAKS + 1), 'baris' => 2],
            ['nama_pj' => '', 'baris' => 3],
            ['nama_pj' => 'Kader Z', 'baris' => 4],
        ], false, User::factory()->create(['type' => 0])->id);

        $this->assertSame('Kader Z', $a->fresh()->pj_nama);
        $this->assertCount(2, $r['gagal']);
        $this->assertStringContainsString('Baris 2', $r['gagal'][0]);
        $this->assertStringContainsString('Baris 3', $r['gagal'][1]);
    }

    public function test_tiga_kategori_dan_nama_sama_beda_kapital_dihitung_satu_pj(): void
    {
        $kel = $this->lestari();
        $stunting = $this->stunting('3201000000040101', $kel->id);
        $wasting = $this->stunting('3201000000040102', $kel->id);
        $underweight = $this->stunting('3201000000040103', $kel->id);
        $normal = $this->stunting('3201000000040104', $kel->id);
        $wasting->latestDataAnak->update(['zscore_pb_u' => 0, 'zscore_bb_pb' => -2.5]);
        $underweight->latestDataAnak->update(['zscore_bb_u' => -2.5]); // dua kategori
        $normal->latestDataAnak->update(['zscore_pb_u' => 0]);
        $hasil = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => 'Sari', 'baris' => 2],
            ['nama_pj' => 'Rina', 'baris' => 3],
            ['nama_pj' => ' SARI ', 'baris' => 4], // orang yang sama, ejaan beda kapital
        ], false, User::factory()->create(['type' => 0])->id);
        $this->assertSame(3, $hasil['dialokasikan'], 'Anak multi-kategori hanya dialokasikan sekali.');
        $this->assertSame(2, $hasil['pj'], 'Nama duplikat tidak menggandakan PJ.');
        $this->assertSame([], $hasil['gagal']);
        $this->assertSame('Sari', $stunting->fresh()->pj_nama);
        $this->assertSame('Rina', $wasting->fresh()->pj_nama);
        $this->assertSame('Sari', $underweight->fresh()->pj_nama);
        $this->assertNull($normal->fresh()->pj_nama);
    }

    public function test_tanpa_pj_valid_tidak_mengubah_anak(): void
    {
        $a = $this->stunting('3201000000040201', $this->lestari()->id);
        $hasil = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => '   ', 'baris' => 2],
        ], true, User::factory()->create(['type' => 0])->id);
        $this->assertSame(0, $hasil['dialokasikan']);
        $this->assertSame(0, $hasil['pj']);
        $this->assertCount(1, $hasil['gagal']);
        $this->assertNull($a->fresh()->pj_nama);
    }

    public function test_hanya_anak_bontang_lestari_yang_dipasangkan(): void
    {
        // Ejaan kapital: pencocokan nama kelurahan sasaran tak boleh peka huruf besar/kecil.
        $lestari = Kelurahan::create(['name' => 'BONTANG LESTARI', 'id_kecamatan' => 3]);
        $belimbing = Kelurahan::create(['name' => 'Belimbing', 'id_kecamatan' => 1]);
        $diLestari = $this->stunting('3201000000040301', $lestari->id);
        $diBelimbing = $this->stunting('3201000000040302', $belimbing->id);
        $belimbingPjLama = $this->stunting('3201000000040303', $belimbing->id, null, 'Kader Lama');

        $r = app(PjAlokasiService::class)->alokasikan([
            ['nama_pj' => 'Kader A', 'baris' => 2],
        ], true, User::factory()->create(['type' => 0])->id);

        $this->assertSame('Kader A', $diLestari->fresh()->pj_nama);
        $this->assertNull($diBelimbing->fresh()->pj_nama, 'Anak di luar Bontang Lestari tidak dipasangkan.');
        $this->assertSame('Kader Lama', $belimbingPjLama->fresh()->pj_nama, 'PJ lama di luar Bontang Lestari dibiarkan walau mode timpa.');
        $this->assertSame(1, $r['anak'], 'Hitungan sasaran hanya anak Bontang Lestari.');
        $this->assertSame(1, $r['dialokasikan']);
        $this->assertSame(0, $r['dilewati']);
        $this->assertSame('Bontang Lestari', $r['kelurahan']);
    }

    public function test_gagal_jelas_bila_kelurahan_sasaran_tidak_ada(): void
    {
        $a = $this->stunting('3201000000040311', Kelurahan::create(['name' => 'Belimbing', 'id_kecamatan' => 1])->id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bontang Lestari');

        try {
            app(PjAlokasiService::class)->alokasikan([
                ['nama_pj' => 'Kader A', 'baris' => 2],
            ], false, User::factory()->create(['type' => 0])->id);
        } finally {
            $this->assertNull($a->fresh()->pj_nama, 'Tidak ada anak yang disentuh bila kelurahan sasaran tak ditemukan.');
        }
    }
}
