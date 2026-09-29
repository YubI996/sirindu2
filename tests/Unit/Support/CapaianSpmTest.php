<?php

namespace Tests\Unit\Support;

use App\Support\CapaianSpm;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CapaianSpmTest extends TestCase
{
    private const AMBANG = ['sesuai' => 0.90, 'tertinggal' => 0.60];

    /** Bulan Juni 2026 = TW II berjalan. */
    private function juni2026(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 6, 15);
    }

    private function buat(?float $sasaran, array $tw, int $tahun = 2026, ?CarbonImmutable $sekarang = null): CapaianSpm
    {
        return CapaianSpm::dari($sasaran, $tw, $tahun, self::AMBANG, $sekarang ?? $this->juni2026());
    }

    public function test_kumulatif_menjumlahkan_triwulan_terisi_dan_mengabaikan_null(): void
    {
        $c = $this->buat(1000, [200, 150, null, null]);

        $this->assertSame(350.0, $c->kumulatif());
        $this->assertSame(2, $c->twTerisi());
        $this->assertSame(35.0, $c->persen());
        $this->assertSame(650.0, $c->selisih());
    }

    public function test_belum_ada_triwulan_terisi_berarti_belum_dilaporkan(): void
    {
        $c = $this->buat(1000, [null, null, null, null]);

        $this->assertNull($c->kumulatif());
        $this->assertNull($c->persen());
        $this->assertNull($c->selisih());
        $this->assertSame(0, $c->twTerisi());
        $this->assertSame(CapaianSpm::STATUS_BELUM, $c->status());
    }

    public function test_nol_adalah_laporan_yang_sah_bukan_kekosongan(): void
    {
        $c = $this->buat(1000, [0.0, null, null, null]);

        $this->assertSame(1, $c->twTerisi());
        $this->assertSame(0.0, $c->kumulatif());
        $this->assertSame(0.0, $c->persen());
        $this->assertSame(CapaianSpm::STATUS_KRITIS, $c->status());
    }

    public function test_sasaran_nol_tidak_membagi_nol(): void
    {
        $c = $this->buat(0.0, [10, null, null, null]);

        $this->assertNull($c->persen());
        $this->assertNull($c->prorata());
        $this->assertNull($c->rasioLaju());
        $this->assertSame(CapaianSpm::STATUS_TANPA_SASARAN, $c->status());
    }

    public function test_sasaran_null_diperlakukan_sebagai_tanpa_sasaran(): void
    {
        $c = $this->buat(null, [10, null, null, null]);

        $this->assertNull($c->persen());
        $this->assertSame(CapaianSpm::STATUS_TANPA_SASARAN, $c->status());
    }

    public function test_prorata_diukur_terhadap_triwulan_yang_dilaporkan(): void
    {
        // Lapor s.d. TW II: target 500 dari 1000, capaian 480 → 96% dari laju.
        $c = $this->buat(1000, [250, 230, null, null]);

        $this->assertSame(500.0, $c->prorata());
        $this->assertSame(0.96, round($c->rasioLaju(), 4));
        $this->assertSame(CapaianSpm::STATUS_SESUAI, $c->status());
        $this->assertSame(48.0, $c->persen());
    }

    public function test_persen_sama_bisa_berbeda_status_tergantung_triwulan(): void
    {
        $twII = $this->buat(1000, [250, 200, null, null]);          // 45% di TW II
        $twIV = $this->buat(1000, [150, 100, 100, 100]);            // 45% di TW IV

        $this->assertSame(45.0, $twII->persen());
        $this->assertSame(45.0, $twIV->persen());
        $this->assertSame(CapaianSpm::STATUS_SESUAI, $twII->status());
        $this->assertSame(CapaianSpm::STATUS_KRITIS, $twIV->status());
    }

    public function test_ambang_tertinggal_dan_kritis(): void
    {
        // Lapor s.d. TW II (prorata 500): 375 → rasio 0,75 = tertinggal.
        $tertinggal = $this->buat(1000, [200, 175, null, null]);
        // 250 → rasio 0,50 = kritis.
        $kritis = $this->buat(1000, [150, 100, null, null]);

        $this->assertSame(CapaianSpm::STATUS_TERTINGGAL, $tertinggal->status());
        $this->assertSame(CapaianSpm::STATUS_KRITIS, $kritis->status());
    }

    public function test_tercapai_menang_atas_laju(): void
    {
        // Sudah 100% padahal baru TW I — tetap 'tercapai', bukan 'sesuai'.
        $c = $this->buat(1000, [1000, null, null, null]);

        $this->assertSame(CapaianSpm::STATUS_TERCAPAI, $c->status());
    }

    public function test_capaian_melebihi_sasaran_dilaporkan_apa_adanya(): void
    {
        $c = $this->buat(1000, [600, 520, null, null]);

        $this->assertSame(1120.0, $c->kumulatif());
        // 1120/1000*100 = 112.00000000000001 — float, jadi bandingkan dengan toleransi.
        // Pembulatan adalah urusan tampilan (number_format), bukan urusan hitungan ini.
        $this->assertEqualsWithDelta(112.0, $c->persen(), 0.0001);
        $this->assertSame(-120.0, $c->selisih(), 'selisih negatif = melampaui sasaran');
        $this->assertSame(CapaianSpm::STATUS_TERCAPAI, $c->status());
    }

    public function test_laporan_tertinggal_saat_triwulan_kalender_lebih_maju(): void
    {
        $september = CarbonImmutable::create(2026, 9, 10); // TW III berjalan
        $c = $this->buat(1000, [250, 250, null, null], 2026, $september);

        $this->assertSame(3, $c->twKalender());
        $this->assertSame(2, $c->twTerisi());
        $this->assertTrue($c->laporanTertinggal());
        // Prorata TETAP terhadap TW II, jadi jangan dicap gagal:
        $this->assertSame(500.0, $c->prorata());
        $this->assertSame(CapaianSpm::STATUS_SESUAI, $c->status());
    }

    public function test_laporan_tidak_tertinggal_saat_triwulan_terakhir_sudah_masuk(): void
    {
        $c = $this->buat(1000, [250, 250, null, null]); // Juni = TW II, terisi s.d. TW II

        $this->assertFalse($c->laporanTertinggal());
    }

    public function test_lubang_di_tengah_terdeteksi(): void
    {
        $september = CarbonImmutable::create(2026, 9, 10);
        $c = $this->buat(1000, [200, null, 300, null], 2026, $september);

        $this->assertSame([2], $c->twKosong());
        $this->assertSame(3, $c->twTerisi());
        $this->assertSame(500.0, $c->kumulatif());
    }

    public function test_tanpa_lubang_daftar_kosong(): void
    {
        $c = $this->buat(1000, [200, 300, null, null]);

        $this->assertSame([], $c->twKosong());
    }

    public function test_tahun_lampau_memakai_triwulan_kalender_empat(): void
    {
        $c = $this->buat(1000, [200, 200, 200, null], 2025);

        $this->assertSame(4, $c->twKalender());
        $this->assertTrue($c->laporanTertinggal(), 'TW IV 2025 tidak pernah dilaporkan');
    }

    public function test_tahun_depan_belum_punya_triwulan_kalender(): void
    {
        $c = $this->buat(1000, [null, null, null, null], 2027);

        $this->assertSame(0, $c->twKalender());
        $this->assertFalse($c->laporanTertinggal());
        $this->assertSame(CapaianSpm::STATUS_BELUM, $c->status());
    }

    public function test_prorata_tw_selalu_target_penuh_untuk_grafik(): void
    {
        $c = $this->buat(1000, [250, null, null, null]);

        $this->assertSame([250.0, 500.0, 750.0, 1000.0], [
            $c->prorataTw(1), $c->prorataTw(2), $c->prorataTw(3), $c->prorataTw(4),
        ]);
    }

    public function test_kumulatif_per_tw_berhenti_di_triwulan_terakhir_terisi(): void
    {
        $c = $this->buat(1000, [200, 150, null, null]);

        $this->assertSame([200.0, 350.0, null, null], $c->kumulatifPerTw());
    }

    public function test_lubang_di_tengah_tidak_memutus_garis_kumulatif(): void
    {
        $september = CarbonImmutable::create(2026, 9, 10);
        $c = $this->buat(1000, [200, null, 300, null], 2026, $september);

        $this->assertSame([200.0, 200.0, 500.0, null], $c->kumulatifPerTw());
    }

    public function test_string_dari_database_diterima(): void
    {
        // Kolom decimal dari MySQL datang sebagai string "200.00".
        $c = $this->buat(1000, ['200.00', '', null, null]);

        $this->assertSame(200.0, $c->kumulatif());
        $this->assertSame(1, $c->twTerisi(), 'string kosong harus dianggap belum dilaporkan');
    }
}
