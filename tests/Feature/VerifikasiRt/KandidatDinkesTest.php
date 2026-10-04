<?php

namespace Tests\Feature\VerifikasiRt;

use App\Jobs\PindaiIdentitasJob;
use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakTautan;
use App\Models\Rt;
use App\Models\User;
use App\Services\TautanIdentitasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Halaman Verifikasi RT untuk Dinkes: tab "Dicurigai sama" (pasangan hasil pindai yang
 * belum diputus) diputuskan LANGSUNG oleh Dinkes tanpa tahap usulan RT, tombol pindai di
 * kepala halaman, dan link menu yang pindah ke grup Data Master.
 */
class KandidatDinkesTest extends TestCase
{
    use RefreshDatabase;

    private User $super;
    private Rt $rt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->super = User::factory()->create(['type' => 0]);
        $this->rt    = Rt::factory()->create();
        Cache::forget(PindaiIdentitasJob::KUNCI);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'id_kel' => $this->rt->id_kelurahan, 'id_rt' => $this->rt->id, 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah',
        ], $o));
    }

    private function kandidat(Anak $x, Anak $y): array
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        AnakKandidat::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'skor' => 1190, 'via' => 'kk',
            'child_sim' => 100, 'parent_sim' => 90, 'dipindai_at' => now()]);

        return ['id_anak_a' => $a, 'id_anak_b' => $b];
    }

    /** Dua anak kandidat dengan nama khas supaya assertSee/assertDontSee tak bentrok. */
    private function pasangan(string $nama, array $oa = [], array $ob = []): array
    {
        static $n = 0;
        $n++;
        $a = $this->anak('32010000100'.str_pad((string) $n, 5, '0', STR_PAD_LEFT), ['nama' => $nama.' Satu'] + $oa);
        $b = $this->anak('32010000200'.str_pad((string) $n, 5, '0', STR_PAD_LEFT), ['nama' => $nama.' Dua'] + $ob);

        return [$a, $b, $this->kandidat($a, $b)];
    }

    // ---- tab "Dicurigai sama" ----------------------------------------------------

    public function test_tab_dicurigai_menjadi_default_superadmin_dan_menampilkan_pasangan(): void
    {
        $this->pasangan('Zulfikar');

        $this->actingAs($this->super)->get(route('admin.verifikasiRt.index'))
            ->assertOk()
            ->assertSee('Dicurigai sama')
            ->assertSee('Zulfikar Satu')
            ->assertSee('Zulfikar Dua')
            ->assertSee(route('admin.verifikasiRt.putuskan'), false);
    }

    public function test_pasangan_yang_sudah_diputus_tidak_muncul_lagi(): void
    {
        [$a, $b, $ids] = $this->pasangan('Wahyudi');
        AnakTautan::create($ids + ['keputusan' => 'beda', 'status' => 'disetujui',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);

        $this->actingAs($this->super)->get(route('admin.verifikasiRt.index', ['tab' => 'dicurigai']))
            ->assertOk()->assertDontSee('Wahyudi Satu');
    }

    public function test_tab_dicurigai_tidak_tersedia_untuk_non_superadmin(): void
    {
        $this->pasangan('Yusrizal');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);

        $this->actingAs($faskes)->get(route('admin.verifikasiRt.index', ['tab' => 'dicurigai']))
            ->assertOk()->assertDontSee('Dicurigai sama')->assertDontSee('Yusrizal Satu');
    }

    // ---- keputusan Dinkes langsung -----------------------------------------------

    public function test_putuskan_sama_langsung_disetujui_tanpa_tahap_rt(): void
    {
        [, , $ids] = $this->pasangan('Taufik');

        $this->actingAs($this->super)
            ->post(route('admin.verifikasiRt.putuskan'), $ids + ['keputusan' => 'sama', 'catatan' => 'KK sama'])
            ->assertRedirect(route('admin.verifikasiRt.index', ['tab' => 'dicurigai']))
            ->assertSessionHas('success');

        $t = AnakTautan::firstOrFail();
        $this->assertSame('sama', $t->keputusan);
        $this->assertSame('disetujui', $t->status, 'Dinkes memutus dan menyetujui sekaligus');
        $this->assertSame($this->super->id, (int) $t->diusulkan_oleh);
        $this->assertSame($this->super->id, (int) $t->ditinjau_oleh);
        $this->assertNotNull($t->ditinjau_at);
        $this->assertNull($t->id_rt, 'bukan keputusan RT');
        $this->assertSame('KK sama', $t->catatan);
        $this->assertSame(1, app(TautanIdentitasService::class)->menungguGabung(), 'langsung masuk antrean Penggabungan');
    }

    public function test_putuskan_beda_langsung_disetujui_dan_pasangan_hilang_dari_daftar(): void
    {
        [, , $ids] = $this->pasangan('Ramdhani');

        $this->actingAs($this->super)
            ->post(route('admin.verifikasiRt.putuskan'), $ids + ['keputusan' => 'beda'])
            ->assertSessionHas('success');

        $this->assertSame('disetujui', AnakTautan::firstOrFail()->status);
        $this->assertSame(0, app(TautanIdentitasService::class)->menungguGabung(), '"beda" tidak menunggu penggabungan');
        $this->actingAs($this->super)->get(route('admin.verifikasiRt.index', ['tab' => 'dicurigai']))
            ->assertDontSee('Ramdhani Satu');
    }

    public function test_sama_ditolak_untuk_pasangan_keduanya_operasi_timbang_tetapi_beda_boleh(): void
    {
        [, , $ids] = $this->pasangan('Hidayat', ['sumber' => 'operasi_timbang'], ['sumber' => 'operasi_timbang']);

        $this->actingAs($this->super)
            ->post(route('admin.verifikasiRt.putuskan'), $ids + ['keputusan' => 'sama'])
            ->assertSessionHas('error');
        $this->assertSame(0, AnakTautan::count(), 'tak boleh ada tautan "sama" yang mustahil digabung');

        $this->actingAs($this->super)
            ->post(route('admin.verifikasiRt.putuskan'), $ids + ['keputusan' => 'beda'])
            ->assertSessionHas('success');
        $this->assertSame(1, AnakTautan::count());
    }

    public function test_putuskan_hanya_superadmin(): void
    {
        [, , $ids] = $this->pasangan('Kusnadi');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);

        $this->actingAs($faskes)
            ->post(route('admin.verifikasiRt.putuskan'), $ids + ['keputusan' => 'sama'])
            ->assertForbidden();
        $this->assertSame(0, AnakTautan::count());
    }

    public function test_putuskan_pasangan_yang_bukan_kandidat_ditolak(): void
    {
        $a = $this->anak('3201000030000001');
        $b = $this->anak('3201000030000002');
        [$x, $y] = AnakTautan::urut($a->id, $b->id);

        $this->actingAs($this->super)
            ->post(route('admin.verifikasiRt.putuskan'), ['id_anak_a' => $x, 'id_anak_b' => $y, 'keputusan' => 'sama'])
            ->assertSessionHas('error');
        $this->assertSame(0, AnakTautan::count());
    }

    // ---- tautan RT sebagai pilihan -----------------------------------------------

    public function test_tombol_buat_tautan_rt_tersedia_untuk_rt_anak(): void
    {
        $this->pasangan('Anwar');

        $this->actingAs($this->super)->get(route('admin.verifikasiRt.index', ['tab' => 'dicurigai']))
            ->assertOk()
            ->assertSee(route('admin.aksesTautan.buat', $this->rt), false)
            ->assertSee($this->rt->name);
    }

    // ---- tombol pindai -----------------------------------------------------------

    public function test_tombol_pindai_di_semua_tab_untuk_superadmin_saja(): void
    {
        foreach (['domisili', 'tautan', 'dicurigai'] as $tab) {
            $this->actingAs($this->super)->get(route('admin.verifikasiRt.index', ['tab' => $tab]))
                ->assertOk()->assertSee('Pindai ulang')->assertSee(route('admin.verifikasiRt.pindai'), false);
        }

        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);
        $this->actingAs($faskes)->get(route('admin.verifikasiRt.index'))
            ->assertOk()->assertDontSee('Pindai ulang');
    }

    public function test_pindai_ganda_hanya_mengantrekan_satu_job(): void
    {
        Queue::fake();

        $this->actingAs($this->super)->post(route('admin.verifikasiRt.pindai'))->assertSessionHas('success');
        $this->actingAs($this->super)->post(route('admin.verifikasiRt.pindai'))->assertSessionHas('error');

        Queue::assertPushed(PindaiIdentitasJob::class, 1);
    }

    public function test_job_pindai_melepas_kunci_setelah_selesai(): void
    {
        Cache::put(PindaiIdentitasJob::KUNCI, 'x', 600);

        (new PindaiIdentitasJob())->handle(app(TautanIdentitasService::class));

        $this->assertFalse(Cache::has(PindaiIdentitasJob::KUNCI));
    }

    // ---- menu --------------------------------------------------------------------

    /** Isi grup menu sidebar (potongan HTML per <li class="dropdown section-group">) berjudul $judul. */
    private function grupMenu(string $html, string $judul): string
    {
        foreach (explode('<li class="dropdown section-group', $html) as $potongan) {
            if (str_contains($potongan, 'mtext">'.$judul.'</span>')) {
                return $potongan;
            }
        }

        $this->fail("Grup menu '{$judul}' tidak ditemukan di sidebar.");
    }

    public function test_link_verifikasi_rt_pindah_ke_data_master_untuk_superadmin(): void
    {
        $html = $this->actingAs($this->super)->get(route('admin.verifikasiRt.index'))->assertOk()->getContent();
        $url  = route('admin.verifikasiRt.index');

        $this->assertStringContainsString($url, $this->grupMenu($html, 'Data Master'));
        $this->assertStringNotContainsString($url, $this->grupMenu($html, 'Dashboard'));
    }

    public function test_link_verifikasi_rt_pindah_ke_data_master_untuk_admin_biasa(): void
    {
        $admin = User::factory()->create(['type' => 1, 'id_kel' => $this->rt->id_kelurahan]);
        $html  = $this->actingAs($admin)->get(route('admin.verifikasiRt.index'))->assertOk()->getContent();
        $url   = route('admin.verifikasiRt.index');

        $this->assertStringContainsString($url, $this->grupMenu($html, 'Data Master'), 'admin biasa tidak kehilangan akses');
        $this->assertStringNotContainsString($url, $this->grupMenu($html, 'Dashboard'));
    }
}
