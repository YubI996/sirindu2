<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Operasi Timbang khusus balita (<60 bln); status wasting ("Gizi
 * Kurang"/"Gizi Buruk") harus mengikuti indikator BB/TB, bukan IMT/U.
 *
 * Referensi z_score disuntik via StatusGiziService::useRefs() agar batas SD
 * deterministik tanpa menyeed tabel z_score.
 */
class TimbangGiziBbTbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Referensi jk=1, umur 24 bln (var=2), tinggi 90 (BB/TB var=2).
        // Sengaja dibuat agar BB/TB dan IMT/U BERBEDA untuk bb=12, tb=90:
        //   - IMT/U bmi=10000*12/8100=14.81 ∈ [m2sd 13, 1sd 17] → normal
        //   - BB/TB bb=12 < m3sd 15 → severely_wasted
        // Sehingga endpoint hanya menghitung gizi_buruk bila memakai BB/TB.
        StatusGiziService::useRefs([
            '1_1_24_2' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            '2_1_24_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            '3_1_24_2' => (object) ['m3sd' => 80, 'm2sd' => 83, '3sd' => 97],
            '4_1_90_2' => (object) ['m3sd' => 15, 'm2sd' => 16, '1sd' => 20, '2sd' => 22, '3sd' => 24],
        ]);
    }

    protected function tearDown(): void
    {
        StatusGiziService::flushCache();
        parent::tearDown();
    }

    public function test_gizi_buruk_balita_dihitung_dari_bb_tb_bukan_imt_u(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);

        $anak = Anak::create([
            'nama' => 'Balita Wasted', 'nik' => '3201000000009001', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'sumber' => 'operasi_timbang',
        ]);
        // Wasting bersumber dari z-score BB/TB tersimpan (indikator BB/TB, bukan IMT/U).
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
            'zscore_bb_u' => -1.0, 'zscore_pb_u' => -1.0, 'zscore_bb_pb' => -3.5,
        ]);

        $data = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.gizi'))
            ->assertStatus(200)
            ->json();

        // Status wasting bersumber dari BB/TB, bukan IMT/U.
        $this->assertArrayHasKey('bb_tb', $data);
        $this->assertArrayNotHasKey('imt_u', $data);

        // z BB/TB -3.5 → severely_wasted → gizi_buruk; ikut total wasting.
        $this->assertSame(1, $data['gizi_buruk']);
        $this->assertSame(0, $data['gizi_kurang']);
        $this->assertSame(1, $data['wasting']);        // total <= -2SD
        $this->assertSame(1, $data['bb_tb']['buruk']);
        $this->assertSame(0, $data['bb_tb']['normal']);
    }

    /**
     * Dashboard harus memakai z-score e-PPGBM tersimpan (selaras ekspor resmi
     * Dinkes), bukan recompute WHO lokal, bila z-score tersedia.
     */
    public function test_gizi_dashboard_memakai_zscore_tersimpan_bukan_recompute(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);

        // Recompute (via refs setUp) untuk tb=90,bb=12:
        //   - tb_u: 90 >= m2sd 83 → normal
        //   - bb_tb: 12 < m3sd 15 → severely_wasted (buruk)
        // Z-score tersimpan sengaja BEDA:
        //   - zscore_pb_u=-2.5 → stunted (pendek)
        //   - zscore_bb_pb=0.0 → normal
        $anak = Anak::create([
            'nama' => 'Balita ZScore', 'nik' => '3201000000009002', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'sumber' => 'operasi_timbang',
        ]);
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
            'zscore_bb_u' => -1.0, 'zscore_pb_u' => -2.5, 'zscore_bb_pb' => 0.0,
        ]);

        $data = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.gizi'))
            ->assertStatus(200)
            ->json();

        // tb_u ikut z-score → pendek (bukan normal hasil recompute).
        $this->assertSame(1, $data['tb_u']['pendek']);
        $this->assertSame(0, $data['tb_u']['normal']);
        $this->assertSame(1, $data['stunting']);
        // bb_tb ikut z-score → normal (bukan buruk hasil recompute).
        $this->assertSame(0, $data['gizi_buruk']);
        $this->assertSame(1, $data['bb_tb']['normal']);
    }

    /**
     * Kartu "Underweight" (BB/U < -2SD) — daftar modalnya harus memuat HANYA
     * anak dgn z-score BB/U di bawah -2, memakai z-score tersimpan.
     */
    public function test_daftar_underweight_hanya_memuat_anak_bb_u_di_bawah_min2sd(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);

        // Underweight: zscore_bb_u = -2.5 (< -2).
        $uw = Anak::create([
            'nama' => 'Balita Underweight', 'nik' => '3201000000009101', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'sumber' => 'operasi_timbang',
        ]);
        DataAnak::create([
            'id_anak' => $uw->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
            'zscore_bb_u' => -2.5, 'zscore_pb_u' => 0.0, 'zscore_bb_pb' => 0.0,
        ]);

        // BB/U normal: zscore_bb_u = -1.0 → tak boleh muncul.
        $ok = Anak::create([
            'nama' => 'Balita Normal', 'nik' => '3201000000009102', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'sumber' => 'operasi_timbang',
        ]);
        DataAnak::create([
            'id_anak' => $ok->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
            'zscore_bb_u' => -1.0, 'zscore_pb_u' => 0.0, 'zscore_bb_pb' => 0.0,
        ]);

        $data = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.daftar', ['kategori' => 'underweight']))
            ->assertStatus(200)
            ->json();

        $this->assertSame('underweight', $data['kategori']);
        $this->assertCount(1, $data['rows']);
        $this->assertSame('3201000000009101', $data['rows'][0]['nik']);
    }

    /**
     * Stunting PERSIS rumus Dinkes: -6.01 < TB/U <= -2.01.
     * Outlier (z <= -6.01) & z = -2.00 tepat TIDAK dihitung.
     */
    public function test_stunting_ikuti_ambang_dan_batas_outlier_dinkes(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);

        $kasus = [
            ['3201000000009201', -2.5],  // stunted   → dihitung
            ['3201000000009202', -6.5],  // outlier   → TIDAK dihitung
            ['3201000000009203', -2.0],  // -2.00 tepat = normal → TIDAK dihitung
        ];
        foreach ($kasus as [$nik, $zTb]) {
            $a = Anak::create([
                'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1,
                'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'sumber' => 'operasi_timbang',
            ]);
            DataAnak::create([
                'id_anak' => $a->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
                'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
                'zscore_bb_u' => 0.0, 'zscore_pb_u' => $zTb, 'zscore_bb_pb' => 0.0,
            ]);
        }

        $data = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.gizi'))->assertStatus(200)->json();

        $this->assertSame(1, $data['stunting']);            // hanya yang -2.5
        $this->assertSame(3, $data['total']);
    }

    /** Kartu Wasting = TOTAL BB/TB <= -2SD (moderat + buruk), sesuai Dinkes. */
    public function test_wasting_total_gabung_moderat_dan_buruk(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);

        foreach ([['3201000000009301', -2.5], ['3201000000009302', -3.5]] as [$nik, $zBt]) {
            $a = Anak::create([
                'nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1,
                'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'sumber' => 'operasi_timbang',
            ]);
            DataAnak::create([
                'id_anak' => $a->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
                'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
                'zscore_bb_u' => 0.0, 'zscore_pb_u' => 0.0, 'zscore_bb_pb' => $zBt,
            ]);
        }

        $data = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.gizi'))->assertStatus(200)->json();

        $this->assertSame(2, $data['wasting']);      // -2.5 + -3.5
        $this->assertSame(1, $data['gizi_kurang']);  // -2.5
        $this->assertSame(1, $data['gizi_buruk']);   // -3.5
    }

    /**
     * Anak SD (>60 bln) TIDAK ikut dihitung di dashboard OT (hybrid):
     *  - sasaran/ringkasan pakai umur SAAT INI dari tgl_lahir,
     *  - kunjungan/gizi pakai umur saat ditimbang (da.bln).
     */
    public function test_anak_sd_di_atas_60_bulan_dikeluarkan_dari_dashboard(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);

        // Balita: umur skrg ~24 bln, ditimbang pada bln=24.
        $balita = Anak::create([
            'nama' => 'Balita Aktif', 'nik' => '3201000000009501', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(), 'status' => 1, 'sumber' => 'operasi_timbang',
        ]);
        DataAnak::create([
            'id_anak' => $balita->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
            'zscore_bb_u' => -2.5, 'zscore_pb_u' => -2.5, 'zscore_bb_pb' => -2.5,
        ]);

        // Anak SD: umur skrg ~90 bln, ditimbang pada bln=90. z-score sengaja
        // ekstrem — kalau bocor ikut dihitung, angka kartu pasti berubah.
        $sd = Anak::create([
            'nama' => 'Anak SD', 'nik' => '3201000000009502', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(90)->toDateString(), 'status' => 1, 'sumber' => 'operasi_timbang',
        ]);
        DataAnak::create([
            'id_anak' => $sd->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 90,
            'posisi' => 'berdiri', 'tb' => 110, 'bb' => 15, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
            'zscore_bb_u' => -3.0, 'zscore_pb_u' => -3.0, 'zscore_bb_pb' => -3.0,
        ]);

        // Kartu gizi: hanya balita yg dihitung.
        $gizi = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.gizi'))->assertStatus(200)->json();
        $this->assertSame(1, $gizi['total']);
        $this->assertSame(1, $gizi['stunting']);
        $this->assertSame(1, $gizi['underweight']);
        $this->assertSame(1, $gizi['wasting']);

        // Ringkasan: sasaran & kunjungan hanya balita.
        $ring = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.ringkasan'))->assertStatus(200)->json();
        $this->assertSame(1, $ring['total_anak']);
        $this->assertSame(1, $ring['total_ditimbang']);
        $this->assertSame(1, $ring['total_kunjungan']);

        // Daftar sasaran: anak SD tak muncul.
        $daftar = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.daftar', ['kategori' => 'sasaran']))->assertStatus(200)->json();
        $this->assertCount(1, $daftar['rows']);
        $this->assertSame('3201000000009501', $daftar['rows'][0]['nik']);
    }

    /** Baris tanpa z-score tersimpan TIDAK dihitung (persis COUNTIFS Dinkes). */
    public function test_baris_zscore_kosong_tidak_dihitung(): void
    {
        $superAdmin = User::factory()->create(['type' => 0]);

        $a = Anak::create([
            'nama' => 'Tanpa ZScore', 'nik' => '3201000000009401', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'sumber' => 'operasi_timbang',
        ]);
        DataAnak::create([
            'id_anak' => $a->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
            // z-score sengaja NULL → tak boleh diklasifikasi/di-recompute.
        ]);

        $data = $this->actingAs($superAdmin)
            ->getJson(route('admin.timbang.gizi'))->assertStatus(200)->json();

        $this->assertSame(0, $data['stunting']);
        $this->assertSame(0, $data['wasting']);
        $this->assertSame(0, $data['underweight']);
        $this->assertSame(1, $data['total']); // tetap masuk penyebut (diukur)
    }
}
