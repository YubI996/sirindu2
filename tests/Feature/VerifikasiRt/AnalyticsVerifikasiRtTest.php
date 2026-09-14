<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\VerifikasiRtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsVerifikasiRtTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil'], $o));
    }

    /** Set kolom denormalisasi langsung (tanpa lewat usulan) — cukup untuk menguji filter/ringkasan. */
    private function tandai(Anak $a, ?string $status, ?string $reviu): void
    {
        DB::table('anak')->where('id', $a->id)->update(['verif_rt_status' => $status, 'verif_rt_reviu' => $reviu, 'verif_rt_at' => now()]);
    }

    private function seedCampuran(): array
    {
        $kel = Kelurahan::factory()->create();
        $a = $this->anak('3201000000021001', ['id_kel' => $kel->id]); $this->tandai($a, 'berdomisili', 'disetujui');
        $b = $this->anak('3201000000021002', ['id_kel' => $kel->id]); $this->tandai($b, 'pindah', 'disetujui');
        $c = $this->anak('3201000000021003', ['id_kel' => $kel->id]); $this->tandai($c, 'berdomisili', 'diusulkan');
        $d = $this->anak('3201000000021004', ['id_kel' => $kel->id]); $this->tandai($d, 'meninggal', 'ditolak');
        $e = $this->anak('3201000000021005', ['id_kel' => $kel->id]); // belum pernah
        $f = $this->anak('3201000000021006', ['id_kel' => Kelurahan::factory()->create()->id]); $this->tandai($f, 'berdomisili', 'disetujui');
        return compact('kel', 'a', 'b', 'c', 'd', 'e', 'f');
    }

    public function test_filter_verif_berdomisili_hanya_yang_disetujui(): void
    {
        $s = $this->seedCampuran();
        $svc = app(VerifikasiRtService::class);

        $q = DB::table('anak'); $svc->terapkanFilterVerif($q, 'berdomisili');
        $this->assertSame([$s['a']->id, $s['f']->id], $q->orderBy('id')->pluck('id')->all());

        $q = DB::table('anak'); $svc->terapkanFilterVerif($q, 'belum');
        $this->assertSame([$s['c']->id, $s['d']->id, $s['e']->id], $q->orderBy('id')->pluck('id')->all());

        $q = DB::table('anak'); $svc->terapkanFilterVerif($q, '');
        $this->assertSame(6, $q->count(), 'kosong = Semua');

        $q = DB::table('anak'); $svc->terapkanFilterVerif($q, 'ngawur');
        $this->assertSame(6, $q->count(), 'nilai tak dikenal diabaikan');
    }

    public function test_ringkasan_verifikasi_per_kelurahan(): void
    {
        $s = $this->seedCampuran();
        $svc = app(VerifikasiRtService::class);

        $this->assertSame(
            ['total' => 6, 'berdomisili' => 2, 'pindah' => 1, 'meninggal' => 0, 'tidak_dikenal' => 0, 'menunggu' => 1, 'belum' => 2],
            $svc->ringkasanVerifikasi()
        );
        $this->assertSame(
            ['total' => 5, 'berdomisili' => 1, 'pindah' => 1, 'meninggal' => 0, 'tidak_dikenal' => 0, 'menunggu' => 1, 'belum' => 2],
            $svc->ringkasanVerifikasi($s['kel']->id)
        );
    }

    // ---- bagian controller (Task 2) ----

    public function test_analytics_json_default_semua_tidak_mengubah_angka_dan_memuat_ringkasan(): void
    {
        $s = $this->seedCampuran();
        $super = User::factory()->create(['type' => 0]);

        $r = $this->actingAs($super)->getJson(route('admin.analytics.filterImunisasi'))->assertOk()->json();
        $this->assertSame(6, $r['totalAnak']);
        $this->assertSame(2, $r['verifikasiRt']['berdomisili']);
        $this->assertSame(6, $r['verifikasiRt']['total']);

        $r = $this->actingAs($super)->getJson(route('admin.analytics.filterImunisasi', ['verif' => 'berdomisili']))->assertOk()->json();
        $this->assertSame(2, $r['totalAnak']);
        $this->assertSame(2, array_sum($r['genderDistribution']['data']));

        $r = $this->actingAs($super)->getJson(route('admin.analytics.filterImunisasi', ['kelurahan' => $s['kel']->id, 'verif' => 'belum']))->assertOk()->json();
        $this->assertSame(3, $r['totalAnak'], 'filter belum = belum final: menunggu + ditolak + belum pernah (c, d, e)');
        $this->assertSame(5, $r['verifikasiRt']['total'], 'ringkasan mengikuti kelurahan, bukan filter verif');
        $this->assertSame(1, $r['verifikasiRt']['menunggu']);
        $this->assertSame(2, $r['verifikasiRt']['belum'], 'kartu memisahkan menunggu dari belum');
    }

    public function test_halaman_analytics_memuat_filter_dan_kartu_verifikasi(): void
    {
        $this->seedCampuran();
        $super = User::factory()->create(['type' => 0]);

        $this->actingAs($super)->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('id="filterVerifRt"', false)
            ->assertSee('Status Verifikasi RT')
            ->assertSee('id="verifBerdomisili"', false)
            ->assertSee('id="verifMenunggu"', false)
            ->assertSee('id="verifBelum"', false);
    }
}
