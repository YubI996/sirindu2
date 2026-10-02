<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\Rt;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\PeriodeKesmas;
use App\Support\WilkerPuskesmas;
use Carbon\Carbon;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /**
     * Anak yang berumur $umurBln bulan tepat pada 31 Des 2025.
     *
     * WAJIB `subMonthsNoOverflow`: `subMonths` meluber ke bulan berikutnya bila
     * bulan tujuan lebih pendek dari 31 hari, jadi 31 Des dikurangi 3 bulan
     * jatuh di 1 Okt — TIMESTAMPDIFF-nya 2 bulan, bukan 3. Umur yang meleset
     * satu bulan tidak pernah memunculkan error; ia cuma memindahkan anak ke
     * sisi lain sebuah ambang (mis. pembebasan Vit A untuk bayi < 6 bulan) dan
     * membuat tes lulus atas alasan yang salah. Terdampak: n = 1, 3, 6, 8, 10,
     * 15, dst. Batas kelompok (11, 12, 23, 24, 35, 36, 59, 60, 72, 83) kebetulan
     * jatuh di bulan 31 hari sehingga aman — jangan bersandar pada kebetulan itu.
     */
    private function anak(int $umurBln, array $extra = []): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create(array_merge([
            'nama' => 'Anak Uji ' . $n, 'nik' => str_pad((string) $n, 16, '0', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => Carbon::create(2025, 12, 31)->subMonthsNoOverflow($umurBln)->toDateString(),
            'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
            // Dasbor Kesmas opt-in (spec 2026-10-02 §6.3): fixture harus bertanda, kalau tidak populasinya kosong.
            'sasaran_balita_kesmas' => 1,
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
            'tgl_lahir' => '2026-01-15', 'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id, 'sasaran_balita_kesmas' => 1]);

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
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id, 'sasaran_balita_kesmas' => 1]);

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

    // ── K4 Pemantauan lengkap T&K ──────────────────────────────────────────

    public function test_pemantauan_tk_hanya_0_59_bulan_dan_hanya_timbang_plus_ddtka(): void
    {
        // K4 = "Balita Dilayani Tumbuh Kembang (0–60 bln)" di lembar klien, dibaca < 60 bulan (spec 2026-10-02 §6.3).
        $batas = $this->anak(59);
        $this->kunjunganBulanan($batas, 6, 1);
        $this->kunjungan($batas, '2025-07-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($batas, '2025-08-15', ['ddtka' => 'Sesuai']); // 8 timbang, 2 ddtka, tanpa vit A → lengkap

        $pra = $this->anak(60); // syarat lengkap, tetapi di luar 0–59
        $this->kunjunganBulanan($pra, 6, 1);
        $this->kunjungan($pra, '2025-07-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($pra, '2025-08-15', ['ddtka' => 'Sesuai']);

        $bayi = $this->anak(8);
        $this->kunjunganBulanan($bayi, 8, 5); // 8 timbang tanpa ddtka → tidak

        $tk = $this->svc->pemantauanTk($this->tahun2025(), []);

        $this->assertSame(2, $tk['sasaran'], 'anak 59 & 8 bln; anak 60 bln tidak');
        $this->assertSame(1, $tk['lengkap']);
        $this->assertSame(50.0, $tk['persen']);
    }

    public function test_perlu_perhatian_k4_hanya_0_59_bulan_dan_sama_dengan_registri(): void
    {
        $this->seedVaksin();
        $this->kunjungan($this->anak(59), '2025-06-15', ['ntob' => 'T']);
        $this->kunjungan($this->anak(65), '2025-06-15', ['ntob' => 'T']);

        $this->assertSame(1, $this->svc->pemantauanTk($this->tahun2025(), [])['perhatian']);
        $this->assertSame(1, $this->svc->registri($this->tahun2025(), [], 'balita_0_59', '', 'perhatian')['total']);
    }

    public function test_hbig_dihitung_per_periode_hanya_anak_bertanda(): void
    {
        $this->anak(3, ['tgl_hbig' => '2025-09-30']);                                   // lahir 30 Sep 2025 → dalam periode
        $this->anak(13, ['tgl_hbig' => '2024-11-30']);                                  // tahun lalu
        $this->anak(4, ['tgl_hbig' => '2025-08-31', 'sasaran_balita_kesmas' => null]);  // belum ditandai
        $this->anak(2);                                                                 // tanpa HBIG

        $this->assertSame(1, $this->svc->layananLingkungan($this->tahun2025(), [])['hbig']);
        $this->assertSame(0, $this->svc->layananLingkungan(PeriodeKesmas::dari(2025, 'tw1'), [])['hbig']);
    }

    public function test_perlu_perhatian_dari_kunjungan_terakhir_dalam_periode(): void
    {
        $ntobT = $this->anak(20);
        $this->kunjungan($ntobT, '2025-03-15', ['ntob' => 'N']);
        $this->kunjungan($ntobT, '2025-06-15', ['ntob' => ' t ']); // terakhir: T (huruf kecil + spasi)

        $sembuh = $this->anak(20);
        $this->kunjungan($sembuh, '2025-03-15', ['ntob' => 'T']);
        $this->kunjungan($sembuh, '2025-06-15', ['ntob' => 'N']); // terakhir N → tidak

        $underweight = $this->anak(20);
        $this->kunjungan($underweight, '2025-06-15', ['zscore_bb_u' => -2.5]);

        $batas = $this->anak(20);
        $this->kunjungan($batas, '2025-06-15', ['zscore_bb_u' => -2.0]); // > -2.01 → normal

        $lama = $this->anak(20);
        $this->kunjungan($lama, '2024-06-15', ['ntob' => 'T']); // di luar periode

        $tk = $this->svc->pemantauanTk($this->tahun2025(), []);

        $this->assertSame(2, $tk['perhatian'], 'ntobT & underweight');
    }

    /**
     * Penginputan ulang pada tanggal sama tidak boleh menggandakan sasaran
     * maupun jumlah perhatian. K4 dan registri memakai satu entri dengan ID terbesar.
     */
    public function test_dua_kunjungan_di_hari_sama_tidak_menghitung_anak_dua_kali(): void
    {
        $anak = $this->anak(20);
        $this->kunjungan($anak, '2025-06-15', ['ntob' => 'T']);
        $this->kunjungan($anak, '2025-06-15', ['ntob' => 'T']); // entri ganda hari yang sama

        $tk = $this->svc->pemantauanTk($this->tahun2025(), []);

        $this->assertSame(1, $tk['sasaran']);
        $this->assertSame(1, $tk['perhatian'], 'satu anak tetap satu, walau dua baris kunjungan');
    }

    /**
     * Dua baris setanggal saling bertentangan: entri koreksi terakhir bertanda T
     * sehingga anak masih perlu perhatian. Arah koreksi T ke N diuji suite swarm.
     */
    public function test_dua_kunjungan_hari_sama_yang_bertentangan_tetap_satu_anak(): void
    {
        $anak = $this->anak(20);
        $this->kunjungan($anak, '2025-06-15', ['ntob' => 'N']);
        $this->kunjungan($anak, '2025-06-15', ['ntob' => 'T']);

        $tk = $this->svc->pemantauanTk($this->tahun2025(), []);

        $this->assertSame(1, $tk['sasaran']);
        $this->assertSame(1, $tk['perhatian']);
    }

    // ── SDIDTK ─────────────────────────────────────────────────────────────

    public function test_sdidtk_per_kelompok_umur_dengan_fokus_gap_terbesar(): void
    {
        $b1 = $this->anak(5);  $this->kunjungan($b1, '2025-03-01', ['ddtka' => 'Sesuai']);
        $b2 = $this->anak(9);  $this->kunjungan($b2, '2025-03-01'); // tanpa ddtka
        $d1 = $this->anak(15); $this->kunjungan($d1, '2025-04-01', ['ddtka' => 'Meragukan']);
        $this->kunjungan($d1, '2025-05-01', ['ddtka' => 'Sesuai']); // 2 kunjungan = tetap 1 anak
        $l1 = $this->anak(30);
        $l2 = $this->anak(40);
        $l3 = $this->anak(50); $this->kunjungan($l3, '2024-12-01', ['ddtka' => 'Sesuai']); // di luar periode
        $p1 = $this->anak(65); $this->kunjungan($p1, '2025-09-01', ['ddtka' => 'Sesuai']);

        $s = $this->svc->sdidtk($this->tahun2025(), []);

        $this->assertSame([2, 1, 50.0], [$s['kelompok']['bayi']['sasaran'], $s['kelompok']['bayi']['realisasi'], $s['kelompok']['bayi']['persen']]);
        $this->assertSame([1, 1, 100.0], [$s['kelompok']['baduta']['sasaran'], $s['kelompok']['baduta']['realisasi'], $s['kelompok']['baduta']['persen']]);
        $this->assertSame([3, 0, 0.0], [$s['kelompok']['balita']['sasaran'], $s['kelompok']['balita']['realisasi'], $s['kelompok']['balita']['persen']]);
        $this->assertSame([1, 1, 100.0], [$s['kelompok']['prasekolah']['sasaran'], $s['kelompok']['prasekolah']['realisasi'], $s['kelompok']['prasekolah']['persen']]);
        $this->assertSame('Kemandirian & KPSP', $s['kelompok']['baduta']['domain']);
        $this->assertSame(['sasaran' => 7, 'realisasi' => 3, 'persen' => 42.9], $s['total']);
        $this->assertSame('balita', $s['fokus']['kelompok']);
        $this->assertSame(100.0, $s['fokus']['gap']);
        $this->assertSame([24, 59], [$s['fokus']['min'], $s['fokus']['max']]);
    }

    public function test_sdidtk_tanpa_sasaran_fokus_null(): void
    {
        $s = $this->svc->sdidtk($this->tahun2025(), []);

        $this->assertNull($s['fokus']);
        $this->assertNull($s['total']['persen']);
    }

    // ── CKG ────────────────────────────────────────────────────────────────

    // ── Layanan & Lingkungan ───────────────────────────────────────────────

    public function test_layanan_per_kunjungan_pembagi_hanya_anak_yang_terisi(): void
    {
        $ya = $this->anak(20);
        $this->kunjungan($ya, '2025-02-01', ['kn1' => 0, 'mbg' => 1]);
        $this->kunjungan($ya, '2025-05-01', ['kn1' => 1]);            // pernah 1 → ya
        $tidak = $this->anak(20);
        $this->kunjungan($tidak, '2025-02-01', ['kn1' => 0]);          // terisi, tidak
        $kosong = $this->anak(20);
        $this->kunjungan($kosong, '2025-02-01');                       // kn1 NULL → belum diisi
        $tanpaKunjungan = $this->anak(20);
        $lama = $this->anak(20);
        $this->kunjungan($lama, '2024-02-01', ['kn1' => 1]);           // di luar periode → belum diisi

        $l = $this->svc->layananLingkungan($this->tahun2025(), []);

        $this->assertSame(5, $l['sasaran']);
        $kn1 = $l['layanan']['baris']['kn1'];
        $this->assertSame(['ya' => 1, 'terisi' => 2, 'persen' => 50.0, 'belum_diisi' => 3], array_intersect_key($kn1, array_flip(['ya', 'terisi', 'persen', 'belum_diisi'])));
        $this->assertSame('KN1', $kn1['badge']);
        $this->assertSame(['ya' => 1, 'terisi' => 1, 'belum_diisi' => 4], array_intersect_key($l['layanan']['baris']['mbg'], array_flip(['ya', 'terisi', 'belum_diisi'])));
        $this->assertNull($l['layanan']['baris']['pkat']['persen']);
        $this->assertTrue($l['layanan']['ada_data']);
        $this->assertCount(9, $l['layanan']['baris']);
    }

    public function test_skrining_neonatal_pada_bayi_dan_sanitasi_pada_0_72(): void
    {
        $this->anak(3, ['skrining_shk' => 'normal', 'pemeriksaan_hepatitis_b' => 'reaktif', 'air_bersih' => 1, 'jamban_sehat' => 0, 'merokok_keluarga' => 1]);
        $this->anak(9, ['skrining_shk' => 'tidak_normal', 'air_bersih' => 1]);
        $this->anak(11, ['skrining_shk' => 'belum']);
        $this->anak(30, ['skrining_shk' => 'normal', 'merokok_keluarga' => 0]); // bukan bayi → skrining tak dihitung, sanitasi ya
        $this->anak(5);                                                          // semua NULL
        $this->anak(80, ['air_bersih' => 1]);                                    // > 72 bln → tidak ikut sanitasi

        $l = $this->svc->layananLingkungan($this->tahun2025(), []);

        $this->assertSame(4, $l['bayi']);
        $shk = $l['skrining']['baris']['skrining_shk'];
        $this->assertSame(3, $shk['terisi']);
        $this->assertSame(1, $shk['belum_diisi']);
        $this->assertSame(1, $shk['sebaran']['normal']['n']);
        $this->assertSame(1, $shk['sebaran']['tidak_normal']['n']);
        $this->assertSame(1, $shk['sebaran']['belum']['n']);
        $this->assertSame(33.3, $shk['sebaran']['normal']['persen']);
        $this->assertSame('Tidak normal', $shk['sebaran']['tidak_normal']['label']);
        $this->assertSame(1, $l['skrining']['baris']['pemeriksaan_hepatitis_b']['sebaran']['reaktif']['n']);
        $this->assertSame(0, $l['skrining']['baris']['skrining_g6pd']['terisi']);
        $this->assertTrue($l['skrining']['ada_data']);

        $this->assertSame(5, $l['sasaran'], '0–72 bln');
        $air = $l['sanitasi']['baris']['air_bersih'];
        $this->assertSame(['ya' => 2, 'terisi' => 2, 'persen' => 100.0, 'belum_diisi' => 3], array_intersect_key($air, array_flip(['ya', 'terisi', 'persen', 'belum_diisi'])));
        $rokok = $l['sanitasi']['baris']['merokok_keluarga'];
        $this->assertSame([1, 2, 50.0, true], [$rokok['ya'], $rokok['terisi'], $rokok['persen'], $rokok['terbalik']]);
        $this->assertSame(0, $l['sanitasi']['baris']['jamban_sehat']['ya']);
        $this->assertSame(1, $l['sanitasi']['baris']['jamban_sehat']['terisi']);
    }

    public function test_layanan_lingkungan_tanpa_data_ada_data_false(): void
    {
        $this->anak(20);
        $this->kunjungan($this->anak(5), '2025-03-01');

        $l = $this->svc->layananLingkungan($this->tahun2025(), []);

        $this->assertFalse($l['layanan']['ada_data']);
        $this->assertFalse($l['skrining']['ada_data']);
        $this->assertFalse($l['sanitasi']['ada_data']);
        $this->assertSame(2, $l['sanitasi']['baris']['air_bersih']['belum_diisi']);
    }

    /**
     * Ketiga seksi harus menghormati filter wilayah. Kalau salah satu jalur
     * lupa meneruskan $filters, petugas puskesmas akan melihat angka SE-KOTA
     * di halaman yang seharusnya hanya wilayahnya — salah, meyakinkan, dan
     * tanpa satu pun error. Layanan datang dari data_anak lewat join, skrining
     * dan sanitasi dari kolom anak; ketiganya diuji terpisah karena jalur
     * query-nya berbeda.
     */
    public function test_layanan_lingkungan_mengikuti_filter_wilayah(): void
    {
        $sini = $this->anak(5, ['air_bersih' => 1, 'skrining_shk' => 'normal']);
        $this->kunjungan($sini, '2025-06-15', ['kn1' => 1]);

        $sana = $this->anak(5, ['id_kel' => $this->kelLain->id, 'air_bersih' => 1, 'skrining_shk' => 'normal']);
        $this->kunjungan($sana, '2025-06-15', ['kn1' => 1]);

        $semua = $this->svc->layananLingkungan($this->tahun2025(), []);
        $this->assertSame(2, $semua['sasaran']);
        $this->assertSame(2, $semua['bayi']);
        $this->assertSame(2, $semua['layanan']['baris']['kn1']['ya']);
        $this->assertSame(2, $semua['skrining']['baris']['skrining_shk']['terisi']);
        $this->assertSame(2, $semua['sanitasi']['baris']['air_bersih']['ya']);

        $satu = $this->svc->layananLingkungan($this->tahun2025(), ['id_kelurahan' => $this->kel->id]);
        $this->assertSame(1, $satu['sasaran']);
        $this->assertSame(1, $satu['bayi']);
        $this->assertSame(1, $satu['layanan']['baris']['kn1']['ya'], 'layanan ikut filter');
        $this->assertSame(1, $satu['skrining']['baris']['skrining_shk']['terisi'], 'skrining ikut filter');
        $this->assertSame(1, $satu['sanitasi']['baris']['air_bersih']['ya'], 'sanitasi ikut filter');
    }

    /**
     * Wilayah tanpa satu anak pun: semua pembagi 0, jadi setiap persen WAJIB
     * null (tampil "—"), bukan 0.0 — 0 % berarti "diukur, hasilnya nol", dan
     * itu bukan yang terjadi di sini.
     */
    public function test_layanan_lingkungan_tanpa_sasaran_semua_persen_null(): void
    {
        $l = $this->svc->layananLingkungan($this->tahun2025(), ['id_kecamatan' => $this->kec->id + 1000]);

        $this->assertSame(0, $l['sasaran']);
        $this->assertSame(0, $l['bayi']);
        $this->assertNull($l['layanan']['baris']['kn1']['persen']);
        $this->assertNull($l['skrining']['baris']['skrining_shk']['sebaran']['normal']['persen']);
        $this->assertNull($l['sanitasi']['baris']['air_bersih']['persen']);
        $this->assertSame(0, $l['sanitasi']['baris']['air_bersih']['belum_diisi']);
    }

    public function test_ckg_per_umur_tahun_dan_footer_gigi(): void
    {
        $t0 = $this->anak(3);  $this->kunjungan($t0, '2025-10-01', ['tgl_penanda_ckg' => '2025-10-01', 'pemeriksaan_gigi' => 'Sehat']);
        $t0b = $this->anak(10); // tanpa CKG
        $t1 = $this->anak(15); $this->kunjungan($t1, '2025-02-01', ['tgl_penanda_ckg' => '2024-12-20']); // CKG tahun lalu
        $t2 = $this->anak(30); $this->kunjungan($t2, '2025-05-01', ['tgl_penanda_ckg' => '2025-05-01', 'pemeriksaan_gigi' => 'Karies', 'rujukan' => 'Dokter gigi']);
        $this->kunjungan($t2, '2025-11-01', ['pemeriksaan_gigi' => 'Karies', 'rujukan' => 'Dokter gigi']); // anak yang sama → rujuk tetap 1
        $t3 = $this->anak(70); $this->kunjungan($t3, '2025-06-01', ['tgl_penanda_ckg' => '2025-06-01', 'rujukan' => 'Rumah sakit']);
        $t3b = $this->anak(80); $this->kunjungan($t3b, '2025-06-01');

        $c = $this->svc->ckg($this->tahun2025(), []);

        $this->assertSame([2, 1], [$c['kelompok']['t0']['sasaran'], $c['kelompok']['t0']['realisasi']]);
        $this->assertSame([1, 0], [$c['kelompok']['t1']['sasaran'], $c['kelompok']['t1']['realisasi']]);
        $this->assertSame([1, 1], [$c['kelompok']['t2']['sasaran'], $c['kelompok']['t2']['realisasi']]);
        $this->assertSame([2, 1], [$c['kelompok']['t3']['sasaran'], $c['kelompok']['t3']['realisasi']], '36–83 bln: anak 70 & 80');
        $this->assertSame('Usia 3–6 tahun', $c['kelompok']['t3']['label']);
        $this->assertSame(['terisi' => 3, 'sehat' => 1, 'persen_sehat' => 33.3], $c['gigi']);
        $this->assertSame(1, $c['rujuk_gigi']);
    }

    // ── Tanda Sasaran Balita Kesmas (spec 2026-10-02 §6.3) ─────────────────

    public function test_anak_belum_ditandai_dan_dilepas_tidak_masuk_agregat_mana_pun(): void
    {
        $this->seedVaksin();
        foreach ([1, null, 0] as $tanda) {
            $a = $this->anak(30, ['sasaran_balita_kesmas' => $tanda, 'air_bersih' => 1]);
            $this->kunjunganBulanan($a, 8, 1, ['ddtka' => 'Sesuai', 'vit_a' => 1, 'kn1' => 1, 'tgl_penanda_ckg' => '2025-01-15']);
        }
        $p = $this->tahun2025();

        $this->assertSame(1, $this->svc->sasaran($p, [])['semua']);
        $this->assertSame(1, $this->svc->spmKohort($p, [])['anak_balita']['sasaran']);
        $this->assertSame(1, $this->svc->pemantauanTk($p, [])['sasaran']);
        $this->assertSame(1, $this->svc->sdidtk($p, [])['total']['sasaran']);
        $this->assertSame(1, $this->svc->ckg($p, [])['kelompok']['t2']['sasaran']);
        $l = $this->svc->layananLingkungan($p, []);
        $this->assertSame(1, $l['sasaran']);
        $this->assertSame(1, $l['layanan']['baris']['kn1']['terisi']);
        $this->assertSame(1, $l['sanitasi']['baris']['air_bersih']['terisi']);
        $this->assertSame(1, $this->svc->registri($p, [])['total']);
    }

    public function test_penandaan_sasaran_dihitung_tanpa_filter_tanda(): void
    {
        $this->anak(5);                                                             // bertanda (bawaan helper)
        $this->anak(30, ['sasaran_balita_kesmas' => null]);
        $this->anak(40, ['sasaran_balita_kesmas' => null]);
        $this->anak(60, ['sasaran_balita_kesmas' => 0]);
        $this->anak(80, ['sasaran_balita_kesmas' => null]);                         // > 72 bln — di luar dasbor
        $this->anak(20, ['sasaran_balita_kesmas' => null, 'id_kel' => $this->kelLain->id]);

        $this->assertSame(['total' => 5, 'bertanda' => 1, 'dilepas' => 1, 'belum' => 3],
            $this->svc->penandaanSasaran($this->tahun2025(), []));
        $this->assertSame(['total' => 4, 'bertanda' => 1, 'dilepas' => 1, 'belum' => 2],
            $this->svc->penandaanSasaran($this->tahun2025(), ['id_kelurahan' => $this->kel->id]));
    }

    public function test_penandaan_tanpa_anak_semua_nol(): void
    {
        $this->assertSame(['total' => 0, 'bertanda' => 0, 'dilepas' => 0, 'belum' => 0],
            $this->svc->penandaanSasaran($this->tahun2025(), []));
    }

    // ── Registri per anak (spec §3.1) ─────────────────────────────────────

    /** Master vaksin untuk isIdlLengkap()/isIblLengkap() — hanya tes registri yang butuh. */
    private function seedVaksin(): void
    {
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
        ImunisasiStatusService::flushCache();
    }

    /** Anak lengkap dengan nama, orang tua, dan wilayah sampai RT/posyandu. */
    private function anakRegistri(string $nama, int $umurBln, array $extra = []): Anak
    {
        static $wilayah = null;
        if ($wilayah === null || ! Rt::find($wilayah['rt'])) {
            $pkm = Puskesmas::create(['name' => 'Bontang Selatan 1', 'id_kecamatan' => $this->kec->id]);
            $pos = Posyandu::create(['name' => 'Melati I', 'id_puskesmas' => $pkm->id]);
            $rt  = Rt::create(['name' => '05', 'id_kelurahan' => $this->kel->id, 'id_posyandu' => $pos->id]);
            $wilayah = ['rt' => $rt->id, 'pos' => $pos->id];
        }

        return $this->anak($umurBln, array_merge([
            'nama' => $nama, 'nama_ibu' => 'Ibu ' . $nama, 'nama_ayah' => 'Ayah ' . $nama,
            'id_rt' => $wilayah['rt'], 'id_posyandu' => $wilayah['pos'],
        ], $extra));
    }

    public function test_registri_baris_memuat_identitas_wilayah_kunjungan_terakhir_dan_status_imunisasi(): void
    {
        $this->seedVaksin();
        $a = $this->anakRegistri('Budi', 30);
        $this->kunjungan($a, '2025-03-10', ['bb' => 9, 'zscore_pb_u' => -2.5]);
        $this->kunjungan($a, '2025-09-10', ['bb' => 10.2, 'tb' => 81, 'lk' => 46.5, 'ntob' => 'N',
            'zscore_bb_u' => -1.0, 'zscore_pb_u' => -2.2, 'zscore_bb_pb' => 0.3, 'catatan_pengukuran' => 'Nafsu makan baik']);
        $this->kunjungan($a, '2026-01-05', ['bb' => 99]); // di luar periode → bukan "terakhir"

        $r = $this->svc->registri($this->tahun2025(), []);

        $this->assertSame(1, $r['total']);
        $b = $r['data'][0];
        $this->assertSame(1, $b['no']);
        $this->assertSame('Budi', $b['nama']);
        $this->assertSame('L', $b['jk']);
        $this->assertSame(30, $b['umur_bln']);
        $this->assertSame('Ibu Budi', $b['nama_ibu']);
        $this->assertSame(['Berbas Tengah', '05', 'Melati I'], [$b['kelurahan'], $b['rt'], $b['posyandu']]);
        $this->assertSame('2025-09-10', $b['kunjungan']['tgl']);
        $this->assertEquals(10.2, $b['kunjungan']['bb']);
        $this->assertSame('stunted', $b['kunjungan']['gizi']['kode']);
        $this->assertSame('Pendek', $b['kunjungan']['gizi']['label']);
        $this->assertFalse($b['kunjungan']['bb_tidak_naik']);
        $this->assertSame('Nafsu makan baik', $b['catatan']);
        $this->assertSame('belum', $b['idl'], '30 bln tanpa vaksin → IDL belum');
        $this->assertSame('belum', $b['ibl']);
        $this->assertSame(route('admin.showAnak', $a->hashid), $b['url_detail']);
    }

    public function test_registri_belum_masuk_usia_dan_anak_tanpa_kunjungan(): void
    {
        $this->seedVaksin();
        $this->anakRegistri('Bayi', 5);     // <12 → IDL belum_usia, <24 → IBL belum_usia
        $this->anakRegistri('Baduta', 15);

        $r = $this->svc->registri($this->tahun2025(), []);

        $bayi = collect($r['data'])->firstWhere('nama', 'Bayi');
        $this->assertSame(['belum_usia', 'belum_usia'], [$bayi['idl'], $bayi['ibl']]);
        $this->assertNull($bayi['kunjungan'], 'tanpa kunjungan = null, bukan objek berisi nol');
        $baduta = collect($r['data'])->firstWhere('nama', 'Baduta');
        $this->assertSame(['belum', 'belum_usia'], [$baduta['idl'], $baduta['ibl']]);
    }

    public function test_registri_paginasi_20_per_halaman_urut_nama(): void
    {
        $this->seedVaksin();
        for ($i = 1; $i <= 23; $i++) {
            $this->anakRegistri(sprintf('Anak %02d', $i), 20);
        }

        $h1 = $this->svc->registri($this->tahun2025(), [], 'semua', '', 'semua', 1);
        $h2 = $this->svc->registri($this->tahun2025(), [], 'semua', '', 'semua', 2);

        $this->assertSame([23, 20, 2, 1], [$h1['total'], $h1['per_page'], $h1['last_page'], $h1['page']]);
        $this->assertCount(20, $h1['data']);
        $this->assertSame('Anak 01', $h1['data'][0]['nama']);
        $this->assertCount(3, $h2['data']);
        $this->assertSame(21, $h2['data'][0]['no']);
        $this->assertSame('Anak 21', $h2['data'][0]['nama']);
    }

    public function test_registri_kunjungan_setanggal_tidak_menggandakan_anak_dan_id_terbesar_menang(): void
    {
        $this->seedVaksin();
        $a = $this->anakRegistri('Ganda', 30);
        $this->kunjungan($a, '2025-09-10', ['bb' => 9.1, 'zscore_bb_u' => 0, 'catatan_pengukuran' => 'entri pertama']);
        $this->kunjungan($a, '2025-09-10', ['bb' => 9.9, 'zscore_bb_u' => -2.5, 'catatan_pengukuran' => 'entri koreksi']); // id lebih besar
        $this->anakRegistri('Zeta', 30);

        $r = $this->svc->registri($this->tahun2025(), []);

        $this->assertSame(2, $r['total'], 'dihitung per anak, bukan per kunjungan');
        $this->assertCount(2, $r['data']);
        $this->assertSame([1, 2], array_column($r['data'], 'no'));
        $g = collect($r['data'])->firstWhere('nama', 'Ganda');
        $this->assertEquals(9.9, $g['kunjungan']['bb'], 'kunjungan setanggal: id terbesar (entri koreksi) yang menang');
        $this->assertSame('entri koreksi', $g['catatan']);
        $this->assertSame('underweight', $g['kunjungan']['gizi']['kode']);

        // Dengan halaman kecil, duplikat tak menggeser halaman berikutnya.
        $h2 = $this->svc->registri($this->tahun2025(), [], 'semua', '', 'semua', 2, 1);
        $this->assertSame(2, $h2['last_page']);
        $this->assertSame('Zeta', $h2['data'][0]['nama']);
    }

    public function test_registri_cari_nama_nik_dan_orang_tua(): void
    {
        $this->seedVaksin();
        $this->anakRegistri('Citra Dewi', 20, ['nik' => '6474012345678901', 'nama_ibu' => 'Ratna']);
        $this->anakRegistri('Dani', 20, ['nik' => '6474099999999999', 'nama_ayah' => 'Bambang Ratnadi']);
        $this->anakRegistri('Eka', 20, ['nik' => '6474088888888888']);

        $this->assertSame(1, $this->svc->registri($this->tahun2025(), [], 'semua', 'citra')['total']);
        $this->assertSame(1, $this->svc->registri($this->tahun2025(), [], 'semua', '2345678')['total']);
        $this->assertSame(2, $this->svc->registri($this->tahun2025(), [], 'semua', 'Ratna')['total'], 'nama ibu & nama ayah');
        $this->assertSame(0, $this->svc->registri($this->tahun2025(), [], 'semua', 'zzz')['total']);
    }

    public function test_registri_cari_tidak_rentan_injeksi_dan_wildcard_dibaca_harfiah(): void
    {
        $this->seedVaksin();
        $this->anakRegistri('Aman', 20);

        $injeksi = "x' OR '1'='1";
        $this->assertSame(0, $this->svc->registri($this->tahun2025(), [], 'semua', $injeksi)['total']);
        $this->assertSame(0, $this->svc->registri($this->tahun2025(), [], 'semua', '%')['total'], '% bukan wildcard');
        $this->assertSame(0, $this->svc->registri($this->tahun2025(), [], 'semua', '_')['total'], '_ bukan wildcard');
        $this->assertSame(0, $this->svc->registri($this->tahun2025(), [], 'semua', '\\')['total'], 'backslash = karakter escape LIKE, harus ikut di-escape');
        $this->assertSame(0, $this->svc->registri($this->tahun2025(), [], 'semua', '\\%')['total'], 'backslash+% tetap harfiah');
        $this->assertSame(1, $this->svc->registri($this->tahun2025(), [], 'semua', 'Aman')['total'], 'tabel & data utuh');
    }

    /**
     * Nama yang sama persis adalah hal biasa di data ini. `ORDER BY a.nama`
     * saja bukan urutan total: MySQL boleh memulangkan baris seri dalam urutan
     * berbeda untuk jendela LIMIT/OFFSET yang berbeda, sehingga satu anak bisa
     * muncul di dua halaman sementara anak lain tak pernah muncul sama sekali.
     * Tidak ada error, tidak ada angka yang mencurigakan — cuma anak yang
     * terlewat. Pemecah seri `a.id` sudah ada; tes ini yang menjaganya.
     */
    public function test_registri_nama_kembar_tidak_ada_anak_hilang_atau_ganda_antar_halaman(): void
    {
        $this->seedVaksin();
        $idSeharusnya = [];
        for ($i = 0; $i < 13; $i++) {
            $idSeharusnya[] = $this->anakRegistri('Nama Kembar', 30)->id;
        }
        sort($idSeharusnya);

        foreach ([1, 2, 5, 13] as $perPage) {
            $terkumpul = [];
            $lastPage = $this->svc->registri($this->tahun2025(), [], 'semua', '', 'semua', 1, $perPage)['last_page'];
            for ($hal = 1; $hal <= $lastPage; $hal++) {
                foreach ($this->svc->registri($this->tahun2025(), [], 'semua', '', 'semua', $hal, $perPage)['data'] as $baris) {
                    $terkumpul[] = $baris['id'];
                }
            }
            sort($terkumpul);

            $this->assertSame($idSeharusnya, $terkumpul, "perPage {$perPage}: tiap anak muncul tepat sekali");
            $this->assertSame(13, count(array_unique($terkumpul)), "perPage {$perPage}: tak ada id ganda");
        }

        // Kontrak pemecah seri yang terlihat dari luar: di antara nama yang sama,
        // urutannya menaik menurut id. Catatan jujur — tes ini MENJAGA akibatnya
        // (tak ada anak hilang/ganda), bukan MEMBUKTIKAN pemecah serinya masih
        // ada: tanpa ORDER BY kedua, urutannya jadi tak terdefinisi, dan MySQL
        // masih boleh memulangkan urutan yang kebetulan sama untuk tabel sekecil
        // ini. Yang pasti tertangkap adalah kerusakan yang benar-benar merugikan.
        $urut = array_column($this->svc->registri($this->tahun2025(), [], 'semua', '', 'semua', 1, 13)['data'], 'id');
        $this->assertSame($idSeharusnya, $urut, 'nama seri diurutkan menaik menurut id');
    }

    /**
     * `eppgbmTb()` memulangkan null untuk z <= -6.01 (outlier implausibel),
     * jadi anak yang satu-satunya z-score terisinya outlier punya badge KOSONG.
     * Ia tidak boleh ikut filter "Normal" — kalau ikut, filter dan badge
     * bercerita beda tentang anak yang sama: barisnya muncul di daftar "gizi
     * normal" tanpa satu pun penanda gizi.
     */
    public function test_registri_z_score_outlier_bukan_normal(): void
    {
        $this->seedVaksin();
        $outlier = $this->anakRegistri('Outlier', 30);
        $this->kunjungan($outlier, '2025-05-01', ['zscore_pb_u' => -7.5]); // di luar batas plausibel
        $normal = $this->anakRegistri('Sungguh Normal', 30);
        $this->kunjungan($normal, '2025-05-01', ['zscore_pb_u' => 0]);

        $r = $this->svc->registri($this->tahun2025(), [], 'semua', '', 'normal');

        $this->assertSame(['Sungguh Normal'], collect($r['data'])->pluck('nama')->all());

        $baris = collect($this->svc->registri($this->tahun2025(), [])['data'])->firstWhere('nama', 'Outlier');
        $this->assertNull($baris['kunjungan']['gizi'], 'badge kosong — konsisten dengan tidak masuk filter normal');
    }

    public function test_registri_filter_usia_dan_wilayah(): void
    {
        $this->seedVaksin();
        $this->anakRegistri('Bayi', 5);
        $this->anakRegistri('Balita', 30);
        $this->anak(30, ['nama' => 'Lain', 'id_kel' => $this->kelLain->id]);
        $this->anakRegistri('Tua', 80);

        $this->assertSame(3, $this->svc->registri($this->tahun2025(), [])['total'], '0–72 saja');
        $this->assertSame(1, $this->svc->registri($this->tahun2025(), [], 'bayi')['total']);
        $this->assertSame(2, $this->svc->registri($this->tahun2025(), [], 'balita')['total']);
        $this->assertSame(1, $this->svc->registri($this->tahun2025(), ['id_kelurahan' => $this->kelLain->id], 'balita')['total']);
        $lain = $this->svc->registri($this->tahun2025(), ['id_kelurahan' => $this->kelLain->id])['data'][0];
        $this->assertNull($lain['rt']);
        $this->assertNull($lain['posyandu']);
    }

    public function test_registri_filter_status_gizi(): void
    {
        $this->seedVaksin();
        $stunted = $this->anakRegistri('Stunted', 30);
        $this->kunjungan($stunted, '2025-05-01', ['zscore_pb_u' => -2.3, 'zscore_bb_u' => -1, 'zscore_bb_pb' => 0]);
        $under = $this->anakRegistri('Underweight', 30);
        $this->kunjungan($under, '2025-05-01', ['zscore_pb_u' => -1, 'zscore_bb_u' => -2.2, 'zscore_bb_pb' => -1]);
        $wasted = $this->anakRegistri('Wasted', 30);
        $this->kunjungan($wasted, '2025-05-01', ['zscore_pb_u' => -1, 'zscore_bb_u' => -1, 'zscore_bb_pb' => -3.5]);
        $ntob = $this->anakRegistri('Tidak naik', 30);
        $this->kunjungan($ntob, '2025-05-01', ['zscore_pb_u' => 0, 'zscore_bb_u' => 0, 'zscore_bb_pb' => 0, 'ntob' => 'T']);
        $normal = $this->anakRegistri('Normal', 30);
        $this->kunjungan($normal, '2025-05-01', ['zscore_pb_u' => 0, 'zscore_bb_u' => 0, 'zscore_bb_pb' => 0, 'ntob' => 'N']);
        $tanpaZ = $this->anakRegistri('Tanpa z-score', 30);
        $this->kunjungan($tanpaZ, '2025-05-01', ['ntob' => 'N']); // kunjungan ada, belum ada z-score
        $this->anakRegistri('Tanpa kunjungan', 30);

        $nama = fn (string $status) => collect($this->svc->registri($this->tahun2025(), [], 'semua', '', $status)['data'])->pluck('nama')->sort()->values()->all();

        $this->assertSame(['Stunted'], $nama('stunted'));
        $this->assertSame(['Underweight'], $nama('underweight'));
        $this->assertSame(['Wasted'], $nama('wasted'));
        $this->assertSame(['Tidak naik', 'Underweight'], $nama('perhatian'));
        $this->assertSame(['Normal'], $nama('normal'), 'z-score kosong ≠ "normal": belum diisi bukan berarti sehat');
        $this->assertCount(7, $this->svc->registri($this->tahun2025(), [])['data'], 'semua = termasuk yang belum ada kunjungan');

        $w = collect($this->svc->registri($this->tahun2025(), [])['data'])->firstWhere('nama', 'Wasted');
        $this->assertSame(['severely_wasted', 'Gizi buruk', 'bad'], array_values($w['kunjungan']['gizi']));
        $t = collect($this->svc->registri($this->tahun2025(), [])['data'])->firstWhere('nama', 'Tidak naik');
        $this->assertTrue($t['kunjungan']['bb_tidak_naik']);
        $this->assertSame('normal', $t['kunjungan']['gizi']['kode']);
        $z = collect($this->svc->registri($this->tahun2025(), [])['data'])->firstWhere('nama', 'Tanpa z-score');
        $this->assertNull($z['kunjungan']['gizi'], 'tanpa z-score → gizi null (belum diisi), bukan "normal"');
    }

    public function test_registri_jumlah_query_tetap_tidak_bergantung_ukuran_halaman(): void
    {
        $this->seedVaksin();
        for ($i = 1; $i <= 12; $i++) {
            $a = $this->anakRegistri(sprintf('Q %02d', $i), 30);
            $this->kunjungan($a, '2025-05-01', ['zscore_bb_u' => 0]);
        }
        $this->svc->registri($this->tahun2025(), [], 'semua', '', 'semua', 1, 2); // hangatkan cache statis

        $hitung = function (int $perPage): int {
            $n = 0;
            DB::listen(function () use (&$n) { $n++; });
            $this->svc->registri($this->tahun2025(), [], 'semua', '', 'semua', 1, $perPage);

            return $n;
        };

        $kecil = $hitung(2);
        $besar = $hitung(12);
        $this->assertSame($kecil, $besar, 'tidak ada N+1');
        $this->assertLessThanOrEqual(8, $besar);
    }

    public function test_kategori_gizi_memilih_yang_terburuk(): void
    {
        $this->assertSame('wasted', KesmasDashboardService::kategoriGizi(['bb_u' => 'underweight', 'tb_u' => 'stunted', 'bb_tb' => 'wasted'])['kode']);
        $this->assertSame('underweight', KesmasDashboardService::kategoriGizi(['bb_u' => 'underweight', 'tb_u' => 'stunted', 'bb_tb' => 'normal'])['kode']);
        $this->assertSame('overweight', KesmasDashboardService::kategoriGizi(['bb_u' => 'normal', 'tb_u' => 'normal', 'bb_tb' => 'overweight'])['kode']);
        $this->assertSame(['normal', 'Gizi baik', 'ok'], array_values(KesmasDashboardService::kategoriGizi(['bb_u' => 'normal', 'tb_u' => 'tinggi', 'bb_tb' => 'normal'])));
        $this->assertNull(KesmasDashboardService::kategoriGizi(['bb_u' => null, 'tb_u' => null, 'bb_tb' => null]));
    }
}
