<?php

namespace Tests\Unit;

use App\Services\StatusGiziService;
use PHPUnit\Framework\TestCase;

/**
 * Uji logika klasifikasi StatusGiziService dgn referensi z_score buatan
 * (cut-point jelas) sehingga batas SD teruji deterministik tanpa DB.
 */
class StatusGiziServiceTest extends TestCase
{
    private StatusGiziService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new StatusGiziService();

        // jk=1. Referensi untuk umur 24 bln (var=2) + tinggi 90 (BB/TB var=2).
        StatusGiziService::useRefs([
            // IMT/U: m3sd=12 m2sd=13 1sd=17 2sd=18 3sd=19
            '1_1_24_2' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            // BB/U (var=1 selalu): m3sd=9 m2sd=10 1sd=15
            '2_1_24_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            // TB/U: m3sd=80 m2sd=83 3sd=97
            '3_1_24_2' => (object) ['m3sd' => 80, 'm2sd' => 83, '3sd' => 97],
            // BB/TB pada tinggi 90: m3sd=11 m2sd=12 1sd=15 2sd=16 3sd=17
            '4_1_90_2' => (object) ['m3sd' => 11, 'm2sd' => 12, '1sd' => 15, '2sd' => 16, '3sd' => 17],
            // Referensi untuk umur 12 bln (var=1) — uji koreksi posisi & var.
            '1_1_12_1' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            '2_1_12_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            '3_1_12_1' => (object) ['m3sd' => 70, 'm2sd' => 73, '3sd' => 85],
            '4_1_71_1' => (object) ['m3sd' => 8, 'm2sd' => 9, '1sd' => 12, '2sd' => 13, '3sd' => 14],
        ]);
    }

    protected function tearDown(): void
    {
        StatusGiziService::flushCache();
        parent::tearDown();
    }

    public function test_koreksi_posisi_berdiri_dibawah_24_bulan_menambah_07(): void
    {
        // <24 bln & berdiri (H) → +0.7
        $this->assertEqualsWithDelta(90.7, $this->svc->koreksiTinggi(90, 12, 'H'), 0.0001);
        // >=24 bln & terlentang (L) → -0.7
        $this->assertEqualsWithDelta(89.3, $this->svc->koreksiTinggi(90, 24, 'L'), 0.0001);
        // tidak memenuhi syarat → tetap
        $this->assertEqualsWithDelta(90.0, $this->svc->koreksiTinggi(90, 12, 'L'), 0.0001);
        $this->assertEqualsWithDelta(90.0, $this->svc->koreksiTinggi(90, 24, 'H'), 0.0001);
    }

    public function test_var_batas_tepat_24_bulan_pakai_tabel_tinggi(): void
    {
        // bln=24 harus var=2 (basis tinggi), bukan var=1. Hanya ref var=2 yg
        // disediakan; bila kode keliru pakai var=1, lookup gagal → tb_u null.
        $g = $this->svc->klasifikasi(12, 90, 24, 'X', 1);
        $this->assertNotNull($g['enum']['tb_u']);
        $this->assertSame('normal', $g['enum']['tb_u']); // 90 ∈ [m2sd=83, 3sd=97]
    }

    public function test_tb_u_klasifikasi(): void
    {
        // tinggi 79 < m3sd 80 → severely_stunted
        $this->assertSame('severely_stunted', $this->svc->klasifikasi(12, 79, 24, 'X', 1)['enum']['tb_u']);
        // tinggi 81 ∈ [80,83) → stunted
        $this->assertSame('stunted', $this->svc->klasifikasi(12, 81, 24, 'X', 1)['enum']['tb_u']);
        // tinggi 90 ∈ [83,97] → normal
        $this->assertSame('normal', $this->svc->klasifikasi(12, 90, 24, 'X', 1)['enum']['tb_u']);
        // tinggi 98 > 97 → tinggi
        $this->assertSame('tinggi', $this->svc->klasifikasi(12, 98, 24, 'X', 1)['enum']['tb_u']);
    }

    public function test_bb_u_klasifikasi(): void
    {
        $this->assertSame('severely_underweight', $this->svc->klasifikasi(8, 90, 24, 'X', 1)['enum']['bb_u']);
        $this->assertSame('underweight', $this->svc->klasifikasi(9.5, 90, 24, 'X', 1)['enum']['bb_u']);
        $this->assertSame('normal', $this->svc->klasifikasi(12, 90, 24, 'X', 1)['enum']['bb_u']);
        $this->assertSame('lebih', $this->svc->klasifikasi(16, 90, 24, 'X', 1)['enum']['bb_u']);
    }

    public function test_imt_u_klasifikasi_balita(): void
    {
        // bmi dihitung 10000*bb/tinggi^2. Pakai tinggi 90 → tinggi^2=8100.
        // Atur bb agar bmi jatuh di tiap pita: bmi = bb*10000/8100 = bb*1.2346
        // target bmi 11.5(<12)→sev; 12.5∈[12,13)→wasted; 15∈[13,17]→normal;
        // 17.5∈(17,18]→risiko; 18.5∈(18,19]→overweight; 20>19→obese
        $bbFor = fn($bmi) => round($bmi * 8100 / 10000, 3);
        $this->assertSame('severely_wasted', $this->svc->klasifikasi($bbFor(11.5), 90, 24, 'X', 1)['enum']['imt_u']);
        $this->assertSame('wasted', $this->svc->klasifikasi($bbFor(12.5), 90, 24, 'X', 1)['enum']['imt_u']);
        $this->assertSame('normal', $this->svc->klasifikasi($bbFor(15), 90, 24, 'X', 1)['enum']['imt_u']);
        $this->assertSame('risiko_lebih', $this->svc->klasifikasi($bbFor(17.5), 90, 24, 'X', 1)['enum']['imt_u']);
        $this->assertSame('overweight', $this->svc->klasifikasi($bbFor(18.5), 90, 24, 'X', 1)['enum']['imt_u']);
        $this->assertSame('obese', $this->svc->klasifikasi($bbFor(20), 90, 24, 'X', 1)['enum']['imt_u']);
    }

    public function test_label_manusiawi_selaras_show(): void
    {
        // tinggi 90 punya seluruh referensi (termasuk BB/TB) → tersedia.
        $g = $this->svc->klasifikasi(12, 90, 24, 'X', 1);
        $this->assertSame('Normal', $g['tb']);
        $this->assertSame('Berat badan normal', $g['bb']);
        $this->assertTrue($g['tersedia']);
        // Wording severely_stunted tetap teruji lewat label statis.
        $this->assertSame('Sangat pendek (severely stunted)', StatusGiziService::labelTb('severely_stunted'));
    }

    public function test_referensi_kurang_tandai_tidak_tersedia(): void
    {
        // Tinggi 120 → BB/TB key "4_1_120_2" tak ada → bb_tb null, tersedia=false,
        // tapi indikator lain tetap terisi (independen).
        $g = $this->svc->klasifikasi(12, 120, 24, 'X', 1);
        $this->assertFalse($g['tersedia']);
        $this->assertNull($g['enum']['bb_tb']);
        $this->assertSame('Data Z-Score tidak tersedia', $g['bt']);
        $this->assertNotNull($g['enum']['tb_u']); // TB/U tetap dihitung
    }

    // --- Override z-score e-PPGBM tersimpan (selaras ekspor resmi Sigizi) --------

    public function test_zscore_tersimpan_menggantikan_recompute_tb_u(): void
    {
        // Raw: tinggi 90 @24bln → recompute tb_u = normal.
        // Z-score e-PPGBM menggantikannya sesuai pita SD.
        $this->assertSame('stunted', $this->svc->klasifikasi(12, 90, 24, 'X', 1, ['tb_u' => -2.5])['enum']['tb_u']);
        $this->assertSame('severely_stunted', $this->svc->klasifikasi(12, 90, 24, 'X', 1, ['tb_u' => -3.5])['enum']['tb_u']);
        $this->assertSame('normal', $this->svc->klasifikasi(12, 90, 24, 'X', 1, ['tb_u' => 0.0])['enum']['tb_u']);
        $this->assertSame('tinggi', $this->svc->klasifikasi(12, 90, 24, 'X', 1, ['tb_u' => 3.5])['enum']['tb_u']);
    }

    public function test_zscore_batas_minus_2_tepat_bukan_stunting(): void
    {
        // WHO: stunting = z < -2. Tepat -2.00 → normal (selaras hitungan Excel).
        $this->assertSame('normal', $this->svc->klasifikasi(12, 90, 24, 'X', 1, ['tb_u' => -2.0])['enum']['tb_u']);
    }

    public function test_zscore_bb_u_dan_bb_tb_dari_zscore(): void
    {
        $g = $this->svc->klasifikasi(12, 90, 24, 'X', 1, ['bb_u' => -3.2, 'bb_tb' => 1.5]);
        $this->assertSame('severely_underweight', $g['enum']['bb_u']);
        $this->assertSame('risiko_lebih', $g['enum']['bb_tb']); // 1 < 1.5 <= 2
    }

    public function test_zscore_null_per_indikator_fallback_recompute(): void
    {
        // tb_u null → recompute (normal); bb_tb dari z-score → wasted.
        $g = $this->svc->klasifikasi(12, 90, 24, 'X', 1, ['tb_u' => null, 'bb_tb' => -2.5]);
        $this->assertSame('normal', $g['enum']['tb_u']);   // recompute
        $this->assertSame('wasted', $g['enum']['bb_tb']);  // dari z-score
    }

    public function test_imt_u_tidak_terpengaruh_zscore_tersimpan(): void
    {
        // IMT/U tak punya z-score tersimpan → tetap recompute apa pun z lain.
        $bbFor = fn($bmi) => round($bmi * 8100 / 10000, 3);
        $g = $this->svc->klasifikasi($bbFor(15), 90, 24, 'X', 1, ['tb_u' => -2.5]);
        $this->assertSame('normal', $g['enum']['imt_u']);
    }

    public function test_label_ikut_zscore_tersimpan(): void
    {
        $g = $this->svc->klasifikasi(12, 90, 24, 'X', 1, ['tb_u' => -2.5]);
        $this->assertSame('Pendek (stunted)', $g['tb']);
    }

    public function test_tanpa_argumen_zscore_perilaku_lama(): void
    {
        // Pemanggil lama (tanpa arg z-score) → recompute persis seperti dulu.
        $this->assertSame('normal', $this->svc->klasifikasi(12, 90, 24, 'X', 1)['enum']['tb_u']);
    }

    // --- enumEppgbm: dashboard OT murni dari z-score tersimpan -------------------

    public function test_enum_eppgbm_klasifikasi_independen_tanpa_sentinel(): void
    {
        $g = $this->svc->enumEppgbm(-2.5, -1.0, 1.5);
        $this->assertSame('underweight', $g['bb_u']);
        $this->assertSame('normal', $g['tb_u']);
        $this->assertSame('risiko_lebih', $g['bb_tb']);
    }

    /**
     * Baris nyata (kasus produksi, kelurahan Bontang Lestari): bb=1.82kg,
     * tb=43cm — implausibel untuk balita mana pun. Sumber data sendiri gagal
     * menghitung z-score BB/TB dan menulis sentinel 999.99, tapi BB/U (-3.69)
     * dan TB/U (-3.64) TETAP tampak seperti angka wajar walau berasal dari
     * pengukuran yang sama-sama tak masuk akal. Ketiganya harus dianggap
     * gagal dihitung bersama — bukan cuma kolom yang kena sentinel.
     */
    public function test_enum_eppgbm_sentinel_di_satu_indikator_membatalkan_ketiganya(): void
    {
        $g = $this->svc->enumEppgbm(-3.690, -3.640, 999.990);
        $this->assertNull($g['bb_u']);
        $this->assertNull($g['tb_u']);
        $this->assertNull($g['bb_tb']);
    }

    /** Sentinel bisa muncul di kolom mana pun, bukan cuma BB/TB. */
    public function test_enum_eppgbm_sentinel_di_bb_u_atau_tb_u_juga_membatalkan_ketiganya(): void
    {
        $g1 = $this->svc->enumEppgbm(999.99, -1.0, 0.0);
        $this->assertNull($g1['bb_u']);
        $this->assertNull($g1['tb_u']);
        $this->assertNull($g1['bb_tb']);

        $g2 = $this->svc->enumEppgbm(-1.0, -999.99, 0.0);
        $this->assertNull($g2['bb_u']);
        $this->assertNull($g2['tb_u']);
        $this->assertNull($g2['bb_tb']);
    }

    /** Nilai ekstrem tapi masuk akal (bukan sentinel) tetap dihitung per indikator. */
    public function test_enum_eppgbm_ekstrem_wajar_tetap_dihitung_bukan_sentinel(): void
    {
        // Kasus nyata: ALFATUNISA zs_tbu=-7.12, MUHAMMAD ALGHIFARI zs_tbu=-6.38 —
        // ekstrem tapi bukan sentinel, tetap diproses (dan dikeluarkan oleh
        // ambang outlier -6.01 yang sudah ada, bukan oleh pembatalan sentinel).
        $g = $this->svc->enumEppgbm(-3.58, -7.12, -1.42);
        $this->assertSame('severely_underweight', $g['bb_u']);
        $this->assertNull($g['tb_u']); // outlier plausibilitas TB/U (<= -6.01), bukan sentinel
        $this->assertSame('normal', $g['bb_tb']);
    }
}
