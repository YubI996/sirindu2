<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\PeriodeKesmas;
use App\Support\WilkerPuskesmas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agregat dasbor Kesmas (spec 2026-09-21 §2–3). Semua tes memakai periode TETAP
 * (tahun 2025 / triwulan 2025) supaya tidak bergantung tanggal hari ini; umur anak
 * dihitung pada AKHIR periode (31 Des 2025 untuk 'tahun').
 */
class KesmasDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private KesmasDashboardService $svc;
    private Kecamatan $kec;
    private Kelurahan $kel;
    private Kelurahan $kelLain;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->svc = app(KesmasDashboardService::class);
        $this->kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $this->kec->id]);
        $this->kelLain = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $this->kec->id]);
    }

    private function tahun2025(): PeriodeKesmas
    {
        return PeriodeKesmas::dari(2025, 'tahun');
    }

    /** Anak yang berumur $umurBln bulan tepat pada 31 Des 2025. */
    private function anak(int $umurBln, array $extra = []): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create(array_merge([
            'nama' => 'Anak Uji ' . $n, 'nik' => str_pad((string) $n, 16, '0', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => Carbon::create(2025, 12, 31)->subMonths($umurBln)->toDateString(),
            'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tgl, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => 1, 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'id_user' => 1, 'sumber' => 'manual',
        ], $extra));
    }

    /** $n kunjungan tanggal 15 tiap bulan, mulai bulan $mulai tahun 2025. */
    private function kunjunganBulanan(Anak $anak, int $n, int $mulai = 1, array $extra = []): void
    {
        for ($i = 0; $i < $n; $i++) {
            $this->kunjungan($anak, sprintf('2025-%02d-15', $mulai + $i), $extra);
        }
    }

    // ── Sasaran ────────────────────────────────────────────────────────────

    public function test_sasaran_memakai_umur_pada_akhir_periode(): void
    {
        $this->anak(5);    // bayi
        $this->anak(11);   // bayi (batas atas)
        $this->anak(12);   // baduta / anak balita
        $this->anak(30);   // balita 24–59
        $this->anak(65);   // prasekolah
        $this->anak(80);   // umur tahun 3–6 saja
        Anak::create(['nama' => 'Lahir tahun depan', 'nik' => '9999999999999999', 'jk' => 2, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2026-01-15', 'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id]);

        $s = $this->svc->sasaran($this->tahun2025(), []);

        $this->assertSame(2, $s['bayi']);
        $this->assertSame(1, $s['baduta']);
        $this->assertSame(2, $s['anak_balita'], '12–59 bulan');
        $this->assertSame(4, $s['balita'], '0–59 bulan');
        $this->assertSame(1, $s['prasekolah']);
        $this->assertSame(5, $s['semua'], '0–72 bulan; anak 80 bln & yang lahir setelah akhir periode tidak ikut');
        $this->assertSame(2, $s['tahun_0']);
        $this->assertSame(1, $s['tahun_1']);
        $this->assertSame(1, $s['tahun_2']);
        $this->assertSame(2, $s['tahun_3_6'], '36–83 bulan: anak 65 & 80');
    }

    public function test_sasaran_tahun_lalu_menghitung_anak_yang_kini_sudah_lebih_tua(): void
    {
        // Lahir 1 Jan 2024: pada 31 Des 2024 berumur 11 bln (bayi), pada 31 Des 2025 berumur 23 bln.
        Anak::create(['nama' => 'Anak 2024', 'nik' => '1111111111111111', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id]);

        $this->assertSame(1, $this->svc->sasaran(PeriodeKesmas::dari(2024, 'tahun'), [])['bayi']);
        $this->assertSame(0, $this->svc->sasaran($this->tahun2025(), [])['bayi']);
        $this->assertSame(1, $this->svc->sasaran($this->tahun2025(), [])['baduta']);
    }

    public function test_sasaran_mengikuti_filter_wilayah(): void
    {
        $this->anak(5);
        $this->anak(5, ['id_kel' => $this->kelLain->id]);

        $this->assertSame(2, $this->svc->sasaran($this->tahun2025(), [])['bayi']);
        $this->assertSame(1, $this->svc->sasaran($this->tahun2025(), ['id_kelurahan' => $this->kel->id])['bayi']);
        $this->assertSame(0, $this->svc->sasaran($this->tahun2025(), ['id_kecamatan' => $this->kec->id + 1000])['bayi']);
    }

    // ── SPM kohort bayi (K2) ───────────────────────────────────────────────

    public function test_bayi_lengkap_bila_8_timbang_2_ddtka_vit_a_dan_lk(): void
    {
        $lengkap = $this->anak(8);
        $this->kunjunganBulanan($lengkap, 6, 5);                                   // Mei–Okt, ddtka kosong
        $this->kunjungan($lengkap, '2025-11-15', ['ddtka' => 'Sesuai']);           // 7
        $this->kunjungan($lengkap, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // 8

        $timbang7 = $this->anak(8);
        $this->kunjunganBulanan($timbang7, 5, 5);
        $this->kunjungan($timbang7, '2025-10-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($timbang7, '2025-11-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // hanya 7 timbang

        $ddtka1 = $this->anak(8);
        $this->kunjunganBulanan($ddtka1, 7, 5);
        $this->kunjungan($ddtka1, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // 8 timbang, 1 ddtka

        $tanpaVitA = $this->anak(8);
        $this->kunjunganBulanan($tanpaVitA, 6, 5);
        $this->kunjungan($tanpaVitA, '2025-11-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($tanpaVitA, '2025-12-15', ['ddtka' => 'Sesuai']); // umur 8 ≥ 6 → Vit A wajib

        $lkNol = $this->anak(8);
        $this->kunjunganBulanan($lkNol, 6, 5, ['lk' => 0]);
        $this->kunjungan($lkNol, '2025-11-15', ['ddtka' => 'Sesuai', 'lk' => 0]);
        $this->kunjungan($lkNol, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1, 'lk' => 0]);

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(['timbang' => 8, 'ddtka' => 2, 'vita' => 2], $spm['syarat']);
        $this->assertSame(5, $spm['bayi']['sasaran']);
        $this->assertSame(1, $spm['bayi']['lengkap']);
        $this->assertSame(20.0, $spm['bayi']['persen']);
        $this->assertSame(4, $spm['bayi']['sisa']);
        $this->assertSame(4, $spm['bayi']['sub']['timbang']['n'], '≥8 timbang: lengkap, ddtka1, tanpaVitA, lkNol');
        $this->assertSame(4, $spm['bayi']['sub']['ddtka']['n'], '≥2 ddtka: lengkap, timbang7, tanpaVitA, lkNol');
        $this->assertSame(4, $spm['bayi']['sub']['vita']['n'], 'Vit A: lengkap, timbang7, ddtka1, lkNol');
        $this->assertSame(5, $spm['bayi']['sub']['vita']['sasaran'], 'Pembagi Vit A bayi = 6–11 bln');
        $this->assertSame(4, $spm['bayi']['sub']['lk']['n']);
    }

    public function test_bayi_di_bawah_6_bulan_dibebaskan_dari_vit_a(): void
    {
        $bayi4 = $this->anak(4);
        $this->kunjunganBulanan($bayi4, 6, 5);
        $this->kunjungan($bayi4, '2025-11-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($bayi4, '2025-12-15', ['ddtka' => 'Sesuai']); // tanpa vit_a

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(1, $spm['bayi']['lengkap']);
        $this->assertSame(0, $spm['bayi']['sub']['vita']['sasaran'], 'Bayi <6 bln tidak masuk pembagi Vit A');
        $this->assertNull($spm['bayi']['sub']['vita']['persen'], 'Pembagi 0 → persen null, bukan 0.0');
    }

    public function test_ddtka_kosong_atau_spasi_tidak_dihitung(): void
    {
        $a = $this->anak(8);
        $this->kunjunganBulanan($a, 6, 5);
        $this->kunjungan($a, '2025-11-15', ['ddtka' => '   ']);
        $this->kunjungan($a, '2025-12-15', ['ddtka' => '', 'vit_a' => 1]);

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(0, $spm['bayi']['sub']['ddtka']['n']);
        $this->assertSame(0, $spm['bayi']['lengkap']);
    }

    // ── SPM kohort anak balita (K3) & gabungan (K1) ────────────────────────

    public function test_anak_balita_lengkap_bila_8_timbang_2_ddtka_2_vit_a(): void
    {
        $lengkap = $this->anak(30);
        $this->kunjunganBulanan($lengkap, 6, 1);
        $this->kunjungan($lengkap, '2025-02-20', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->kunjungan($lengkap, '2025-08-20', ['ddtka' => 'Meragukan', 'vit_a' => 1]);

        $vitA1 = $this->anak(40);
        $this->kunjunganBulanan($vitA1, 7, 1);
        $this->kunjungan($vitA1, '2025-08-20', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->kunjungan($vitA1, '2025-09-20', ['ddtka' => 'Sesuai']); // 9 timbang, 2 ddtka, 1 vit A

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(2, $spm['anak_balita']['sasaran']);
        $this->assertSame(1, $spm['anak_balita']['lengkap']);
        $this->assertSame(50.0, $spm['anak_balita']['persen']);
        $this->assertSame(1, $spm['anak_balita']['gap']);
        $this->assertSame(2, $spm['anak_balita']['sub']['timbang']['n']);
        $this->assertSame(2, $spm['anak_balita']['sub']['ddtka']['n']);
        $this->assertSame(1, $spm['anak_balita']['sub']['vita']['n']);
        $this->assertSame(2, $spm['anak_balita']['sub']['vita']['sasaran']);

        // K1 = gabungan bayi + anak balita (di sini tidak ada bayi).
        $this->assertSame(2, $spm['balita']['sasaran']);
        $this->assertSame(1, $spm['balita']['lengkap']);
        $this->assertSame(50.0, $spm['balita']['persen']);
    }

    public function test_k1_menggabungkan_bayi_dan_anak_balita(): void
    {
        $bayi = $this->anak(8);
        $this->kunjunganBulanan($bayi, 6, 5);
        $this->kunjungan($bayi, '2025-11-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($bayi, '2025-12-15', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->anak(30); // anak balita tanpa kunjungan
        $this->anak(65); // prasekolah — bukan sasaran K1

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(2, $spm['balita']['sasaran'], '0–59 bln saja');
        $this->assertSame(1, $spm['balita']['lengkap']);
    }

    public function test_triwulan_memprorata_syarat_dan_menilai_ddtka_pada_semester_induk(): void
    {
        $tw3 = PeriodeKesmas::dari(2025, 'tw3'); // T8=2, T2=1; semester induk Jul–Des

        $lengkap = $this->anak(30);
        $this->kunjungan($lengkap, '2025-07-10');
        $this->kunjungan($lengkap, '2025-08-10');
        $this->kunjungan($lengkap, '2025-11-10', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // di luar TW3 tapi masih semester II

        $ddtkaSemesterLain = $this->anak(30);
        $this->kunjungan($ddtkaSemesterLain, '2025-02-10', ['ddtka' => 'Sesuai', 'vit_a' => 1]); // semester I
        $this->kunjungan($ddtkaSemesterLain, '2025-07-10');
        $this->kunjungan($ddtkaSemesterLain, '2025-08-10');

        $timbang1 = $this->anak(30);
        $this->kunjungan($timbang1, '2025-07-10', ['ddtka' => 'Sesuai', 'vit_a' => 1]);

        $spm = $this->svc->spmKohort($tw3, []);

        $this->assertSame(['timbang' => 2, 'ddtka' => 1, 'vita' => 1], $spm['syarat']);
        $this->assertSame(3, $spm['anak_balita']['sasaran']);
        $this->assertSame(1, $spm['anak_balita']['lengkap']);
        $this->assertSame(2, $spm['anak_balita']['sub']['timbang']['n']);
        $this->assertSame(2, $spm['anak_balita']['sub']['ddtka']['n'], 'DDTKA Nov (semester II) dihitung; Feb (semester I) tidak');
    }

    public function test_kunjungan_di_luar_periode_tidak_dihitung(): void
    {
        $a = $this->anak(30);
        $this->kunjunganBulanan($a, 8, 1);
        $this->kunjungan($a, '2024-12-20', ['ddtka' => 'Sesuai', 'vit_a' => 1]);
        $this->kunjungan($a, '2026-01-05', ['ddtka' => 'Sesuai', 'vit_a' => 1]);

        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(1, $spm['anak_balita']['sub']['timbang']['n']);
        $this->assertSame(0, $spm['anak_balita']['sub']['ddtka']['n']);
        $this->assertSame(0, $spm['anak_balita']['sub']['vita']['n']);
    }

    public function test_tanpa_anak_semua_nol_dan_persen_null(): void
    {
        $spm = $this->svc->spmKohort($this->tahun2025(), []);

        $this->assertSame(0, $spm['balita']['sasaran']);
        $this->assertNull($spm['balita']['persen']);
        $this->assertNull($spm['bayi']['sub']['timbang']['persen']);
    }
}
