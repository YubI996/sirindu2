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

    // ── K4 Pemantauan lengkap T&K ──────────────────────────────────────────

    public function test_pemantauan_tk_mencakup_prasekolah_dan_hanya_timbang_plus_ddtka(): void
    {
        $pra = $this->anak(65);
        $this->kunjunganBulanan($pra, 6, 1);
        $this->kunjungan($pra, '2025-07-15', ['ddtka' => 'Sesuai']);
        $this->kunjungan($pra, '2025-08-15', ['ddtka' => 'Sesuai']); // 8 timbang, 2 ddtka, tanpa vit A → lengkap

        $bayi = $this->anak(8);
        $this->kunjunganBulanan($bayi, 8, 5); // 8 timbang tanpa ddtka → tidak

        $tk = $this->svc->pemantauanTk($this->tahun2025(), []);

        $this->assertSame(2, $tk['sasaran']);
        $this->assertSame(1, $tk['lengkap']);
        $this->assertSame(50.0, $tk['persen']);
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
     * `kunjunganTerakhirSub()` mengembalikan (id_anak, max_tgl); join ke
     * `data_anak` atas kedua kolom itu memulangkan DUA baris untuk anak yang
     * dua kali ditimbang di hari yang sama — nyata terjadi saat posyandu
     * menginput ulang. Tanpa `distinct()` anak itu dihitung dua kali dan
     * `perhatian` bisa melebihi `sasaran`, tanpa error apa pun. Task 6 memakai
     * ulang subquery yang sama, jadi jaminan ini dikunci di sini.
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
     * Varian yang lebih jahat: dua baris di hari yang sama saling bertentangan.
     * Anak tetap dihitung sekali; "perhatian" menang karena satu baris pun
     * yang bertanda T sudah cukup untuk ditengok petugas. Spec diam soal ini,
     * jadi perilakunya dikunci di sini supaya tidak bergeser diam-diam.
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
}
