<?php

namespace Tests\Feature\Imunisasi;

use App\Models\Anak;
use App\Services\ImunisasiStatusService;
use App\Support\KohortImunisasi;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImunisasiRutinDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ImunisasiStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache(); // hindari cache statis id lama nempel dari test method sebelumnya.
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
        $this->service = app(ImunisasiStatusService::class);
    }

    private function anak(array $overrides = []): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create(array_merge([
            'nama' => 'Anak Uji ' . $n,
            'nik' => str_pad((string) $n, 16, '0', STR_PAD_LEFT),
            'jk' => 1,
            'tempat_lahir' => 'Bontang',
            'tgl_lahir' => now()->subMonths(5)->toDateString(),
            'status' => 1,
        ], $overrides));
    }

    public function test_sasaran_memilah_bbl_si_dan_baduta_menurut_kohort(): void
    {
        $this->anak(['tgl_lahir' => '2025-04-01']); // SI  — hari pertama kohort 2026
        $this->anak(['tgl_lahir' => '2025-09-15']); // SI
        $this->anak(['tgl_lahir' => '2026-01-31']); // SI  — hari terakhir SI
        $this->anak(['tgl_lahir' => '2026-02-01']); // BBL — hari pertama BBL
        $this->anak(['tgl_lahir' => '2026-03-31']); // BBL — hari terakhir kohort
        $this->anak(['tgl_lahir' => '2024-06-10']); // Baduta (kohort 2025)
        $this->anak(['tgl_lahir' => '2025-03-31']); // Baduta — hari terakhir kohort 2025
        $this->anak(['tgl_lahir' => '2024-03-31']); // di luar semua kelompok
        $this->anak(['tgl_lahir' => '2026-04-01']); // di luar — sudah kohort 2027

        $sasaran = $this->service->getRingkasanSasaran(KohortImunisasi::dari(2026));

        $this->assertSame(2, $sasaran['bbl']['jumlah']);
        $this->assertSame(3, $sasaran['si']['jumlah']);
        $this->assertSame(2, $sasaran['baduta']['jumlah']);
        $this->assertSame(2026, $sasaran['tahun']);
        $this->assertSame(['2026-02-01', '2026-03-31'], $sasaran['bbl']['rentang']);
    }

    public function test_tanggal_lahir_masa_depan_tidak_masuk_kohort_mana_pun(): void
    {
        // Salah ketik petugas. Tidak boleh masuk hitungan, tidak boleh error.
        $this->anak(['tgl_lahir' => '2030-01-01']);
        $this->anak(['tgl_lahir' => '2025-09-15']); // SI, pembanding

        $sasaran = $this->service->getRingkasanSasaran(KohortImunisasi::dari(2026));

        $this->assertSame(0, $sasaran['bbl']['jumlah']);
        $this->assertSame(1, $sasaran['si']['jumlah']);
        $this->assertSame(0, $sasaran['baduta']['jumlah']);
    }

    public function test_sasaran_menghormati_filter_wilayah(): void
    {
        $this->anak(['tgl_lahir' => '2025-09-15', 'id_kel' => 1]);
        $this->anak(['tgl_lahir' => '2025-09-15', 'id_kel' => 2]);

        $sasaran = $this->service->getRingkasanSasaran(KohortImunisasi::dari(2026), ['id_kelurahan' => 1]);

        $this->assertSame(1, $sasaran['si']['jumlah']);
    }

    private function beriVaksin(Anak $anak, string $kode, string $status = 'sudah', ?string $tanggal = null): void
    {
        $idVaksin = \App\Models\JenisVaksin::where('kode', $kode)->value('id');
        \App\Models\Imunisasi::create([
            'id_anak' => $anak->id,
            'id_jenis_vaksin' => $idVaksin,
            'dosis' => 1,
            'status' => $status,
            'tanggal_pemberian' => $status === 'sudah' ? ($tanggal ?? now()->toDateString()) : null,
        ]);
    }

    public function test_funnel_dosis_dihitung_atas_kohort_si(): void
    {
        $si = $this->anak(['tgl_lahir' => '2025-05-10']);
        $this->beriVaksin($si, 'HB0');
        $this->beriVaksin($si, 'DPT-HB-HIB1');

        $badutaDiabaikan = $this->anak(['tgl_lahir' => '2024-06-10']);
        $this->beriVaksin($badutaDiabaikan, 'HB0');

        $bblDiabaikan = $this->anak(['tgl_lahir' => '2026-03-01']);
        $this->beriVaksin($bblDiabaikan, 'HB0');

        $funnel = collect($this->service->getFunnelDosis(KohortImunisasi::dari(2026)))->keyBy('kode');

        $this->assertSame(1, $funnel['HB0']['jumlah'], 'Hanya anak SI yang masuk funnel.');
        $this->assertSame(1, $funnel['DPT-HB-HIB1']['jumlah']);
        $this->assertSame(0, $funnel['DPT-HB-HIB3']['jumlah']);
    }

    public function test_penyebut_antigen_mengikuti_jendela_usia_pemberian(): void
    {
        $si     = $this->anak(['tgl_lahir' => '2025-05-10']); // SI + SELURUH
        $bbl    = $this->anak(['tgl_lahir' => '2026-03-01']); // BBL + SELURUH
        $baduta = $this->anak(['tgl_lahir' => '2024-06-10']); // BADUTA

        $this->beriVaksin($si, 'HB0');
        $this->beriVaksin($bbl, 'HB0');
        $this->beriVaksin($si, 'DPT-HB-HIB1');
        $this->beriVaksin($baduta, 'PCV3');

        $cakupan = collect($this->service->getCakupanAntigen(KohortImunisasi::dari(2026)))->keyBy('kode');

        // HB0 (max 7 hr) -> seluruh kelahiran periode = BBL + SI = 2 anak.
        $this->assertSame('SELURUH', $cakupan['HB0']['kelompok']);
        $this->assertSame(2, $cakupan['HB0']['jumlah_penyebut']);
        $this->assertSame(2, $cakupan['HB0']['jumlah_sudah']);

        // DPT1 (max 90 hr) -> SI saja = 1 anak.
        $this->assertSame('SI', $cakupan['DPT-HB-HIB1']['kelompok']);
        $this->assertSame(1, $cakupan['DPT-HB-HIB1']['jumlah_penyebut']);

        // PCV3 (max 395 hr) -> Baduta = 1 anak.
        $this->assertSame('BADUTA', $cakupan['PCV3']['kelompok']);
        $this->assertSame(1, $cakupan['PCV3']['jumlah_penyebut']);
        $this->assertSame(100.0, $cakupan['PCV3']['persen']);
    }

    public function test_rv1_yang_jendelanya_melintasi_batas_masuk_si(): void
    {
        // RV1 jendelanya 42-70 hari, melintasi batas 59. Dengan aturan batas
        // ATAS ia masuk SI — benar, karena baru bisa dinilai setelah 70 hari.
        $cakupan = collect($this->service->getCakupanAntigen(KohortImunisasi::dari(2026)))->keyBy('kode');

        $this->assertSame('SI', $cakupan['RV1']['kelompok']);
    }

    public function test_pemetaan_kelompok_tepat_di_batas_59_60_364_365(): void
    {
        $peta = fn (int $maxHari) => $this->service->kelompokPenyebutAntigenUntukUji($maxHari);

        $this->assertSame('SELURUH', $peta(59));
        $this->assertSame('SI',      $peta(60));
        $this->assertSame('SI',      $peta(364));
        $this->assertSame('BADUTA',  $peta(365));
    }

    public function test_antigen_kategori_tambahan_tetap_dikecualikan(): void
    {
        $kode = collect($this->service->getCakupanAntigen(KohortImunisasi::dari(2026)))->pluck('kode');

        $this->assertNotContains('HPV1', $kode);
        $this->assertNotContains('DT', $kode);
    }

    public function test_kohort_wilayah_memilah_bbl_si_baduta_per_kelurahan(): void
    {
        // Tabel wilayah KOSONG di sirindu_testing — tanpa seeder ini
        // Kecamatan::find(1) null dan tes error sebelum menguji apa pun.
        // Di-seed di sini saja karena cuma tes ini yang butuh wilayah nyata.
        $this->seed(\Database\Seeders\KecamatanTableSeeder::class);
        $this->seed(\Database\Seeders\KelurahanTableSeeder::class);

        $this->anak(['tgl_lahir' => '2025-09-15', 'id_kec' => 1, 'id_kel' => 1]); // SI
        $this->anak(['tgl_lahir' => '2026-03-01', 'id_kec' => 1, 'id_kel' => 1]); // BBL
        $this->anak(['tgl_lahir' => '2024-06-10', 'id_kec' => 1, 'id_kel' => 1]); // Baduta
        $this->anak(['tgl_lahir' => '2024-03-31', 'id_kec' => 1, 'id_kel' => 1]); // di luar kohort

        $kohort = collect($this->service->getKohortWilayah(KohortImunisasi::dari(2026)));
        $baris  = $kohort->firstWhere('nama', \App\Models\Kecamatan::find(1)->name);

        $this->assertSame(1, $baris['bbl']);
        $this->assertSame(1, $baris['si']);
        $this->assertSame(1, $baris['baduta']);
        $this->assertSame(3, $baris['total'], 'Anak di luar kohort tidak ikut terhitung.');
    }

    public function test_sasaran_harian_besok_mengelompokkan_antigen_jatuh_tempo_per_anak(): void
    {
        // Lahir hari ini → HB0 & BCG (usia_pemberian_min=0) jatuh tempo HARI INI.
        $anakHariIni = $this->anak(['nama' => 'Anak Hari Ini', 'tgl_lahir' => now()->toDateString()]);
        $this->beriVaksin($anakHariIni, 'HB0'); // BCG sengaja belum.

        // Lahir 59 hari lalu → besok usianya 60 hari, jatuh tempo POLIO2/PCV1/DPT-HB-HIB1 (min=60).
        $anakBesok = $this->anak(['nama' => 'Anak Besok', 'tgl_lahir' => now()->subDays(59)->toDateString()]);

        // Kontrol: usia tak match jendela antigen manapun → tak boleh muncul di kedua daftar.
        $this->anak(['nama' => 'Anak Tak Relevan', 'tgl_lahir' => now()->subDays(100)->toDateString()]);

        $hasil = $this->service->getSasaranHarianBesok();

        $this->assertCount(1, $hasil['hari_ini']);
        $baris = $hasil['hari_ini'][0];
        $this->assertSame('Anak Hari Ini', $baris['anak']->nama);
        $antigenHariIni = collect($baris['antigen'])->keyBy('kode');
        $this->assertSame('sudah', $antigenHariIni['HB0']['status']);
        $this->assertSame('belum', $antigenHariIni['BCG']['status']);

        $this->assertCount(1, $hasil['besok']);
        $barisBesok = $hasil['besok'][0];
        $this->assertSame('Anak Besok', $barisBesok['anak']->nama);
        $this->assertCount(3, $barisBesok['antigen']);
        $this->assertTrue(collect($barisBesok['antigen'])->every(fn ($a) => $a['status'] === 'belum'));
    }

    public function test_rincian_puskesmas_sasarannya_kohort_si(): void
    {
        // Data wilayah (kecamatan/kelurahan/puskesmas) bukan bagian setUp() —
        // di-seed di sini karena hanya test ini yang butuh catchment nyata.
        $this->seed(\Database\Seeders\KecamatanTableSeeder::class);
        $this->seed(\Database\Seeders\KelurahanTableSeeder::class);
        $this->seed(\Database\Seeders\PuskesmasTableSeeder::class);
        \App\Support\WilkerPuskesmas::flushCache(); // hindari cache statis dari test lain di proses PHPUnit yang sama.

        $kelIds = \App\Support\WilkerPuskesmas::catchmentKelurahanIds(\App\Models\Puskesmas::orderBy('name')->first()->name);
        $idKel  = $kelIds[0];

        $lengkap = $this->anak(['tgl_lahir' => '2025-05-10', 'id_kel' => $idKel]);
        $this->lengkapiIdl($lengkap);
        $this->anak(['tgl_lahir' => '2025-06-10', 'id_kel' => $idKel]); // SI, belum lengkap
        $this->anak(['tgl_lahir' => '2026-03-01', 'id_kel' => $idKel]); // BBL — bukan sasaran
        $this->anak(['tgl_lahir' => '2024-06-10', 'id_kel' => $idKel]); // Baduta — bukan sasaran

        $rincian = collect($this->service->getRincianPuskesmas(KohortImunisasi::dari(2026)))
            ->firstWhere('nama', \App\Models\Puskesmas::orderBy('name')->first()->name);

        $this->assertSame(2, $rincian['sasaran'], 'Sasaran puskesmas = kohort SI, bukan "anak >= 12 bulan".');
        $this->assertSame(1, $rincian['capaian_idl']);
        $this->assertSame(50.0, $rincian['persen']);
    }

    private function beriSemuaVaksinKelompok(\App\Models\Anak $anak, string $kodeKelompok): void
    {
        $idKelompok = \App\Models\KelompokVaksin::where('kode', $kodeKelompok)->value('id');
        foreach (\App\Models\JenisVaksin::where('id_kelompok_vaksin', $idKelompok)->pluck('kode') as $kode) {
            $this->beriVaksin($anak, $kode);
        }
    }

    public function test_ibl_lengkap_true_saat_semua_vaksin_booster_diberikan(): void
    {
        $anak = $this->anak(['tgl_lahir' => now()->subMonths(30)->toDateString()]);
        $this->beriSemuaVaksinKelompok($anak, 'IBL');

        $anak->load('imunisasi.jenisVaksin');
        $this->assertTrue($this->service->isIblLengkap($anak));
    }

    public function test_ibl_lengkap_false_saat_ada_booster_belum_diberikan(): void
    {
        $anak = $this->anak(['tgl_lahir' => now()->subMonths(30)->toDateString()]);
        $this->beriVaksin($anak, 'PCV3');
        // MR2 & DPT-HB-HIB4 belum diberikan.

        $anak->load('imunisasi.jenisVaksin');
        $this->assertFalse($this->service->isIblLengkap($anak));
    }

    public function test_cakupan_ibl_penyebutnya_kohort_baduta(): void
    {
        $lengkap = $this->anak(['tgl_lahir' => '2024-06-10']); // Baduta 2026
        foreach (['PCV3', 'MR2', 'DPT-HB-HIB4'] as $kode) {
            $this->beriVaksin($lengkap, $kode);
        }
        $this->anak(['tgl_lahir' => '2025-03-31']); // Baduta 2026, belum lengkap
        $this->anak(['tgl_lahir' => '2025-09-15']); // SI 2026 — bukan penyebut IBL
        $this->anak(['tgl_lahir' => '2023-01-01']); // di luar Baduta 2026

        $coverage = $this->service->getIblCoverage(KohortImunisasi::dari(2026));

        $this->assertSame(2, $coverage['total'], 'Penyebut IBL = Baduta tahun terpilih, bukan "anak >= 24 bulan".');
        $this->assertSame(1, $coverage['ibl_lengkap']);
        $this->assertSame(50.0, $coverage['persen']);
    }

    private function lengkapiIdl(Anak $anak): void
    {
        foreach (['HB0', 'BCG', 'POLIO1', 'POLIO2', 'DPT-HB-HIB1', 'PCV1', 'POLIO3', 'DPT-HB-HIB2',
                  'PCV2', 'POLIO4', 'IPV1', 'DPT-HB-HIB3', 'IPV2', 'MR1', 'RV1', 'RV2'] as $kode) {
            $this->beriVaksin($anak, $kode);
        }
    }

    public function test_cakupan_idl_penyebutnya_kohort_si(): void
    {
        $siLengkap = $this->anak(['tgl_lahir' => '2025-05-10']);
        $this->lengkapiIdl($siLengkap);
        $this->anak(['tgl_lahir' => '2025-06-10']);  // SI, belum lengkap
        $this->anak(['tgl_lahir' => '2026-03-01']);  // BBL — bukan penyebut IDL
        $this->anak(['tgl_lahir' => '2024-06-10']);  // Baduta — bukan penyebut IDL

        $coverage = $this->service->getIdlCoverage(KohortImunisasi::dari(2026));

        $this->assertSame(2, $coverage['total'], 'Penyebut IDL = SI saja.');
        $this->assertSame(1, $coverage['idl_lengkap']);
        $this->assertSame(50.0, $coverage['persen']);
        $this->assertArrayNotHasKey('butuh_kejar', $coverage, 'butuh_kejar pindah ke method operasional sendiri.');
    }

    public function test_cakupan_idl_kohort_kosong_menghasilkan_nol_bukan_error(): void
    {
        $this->anak(['tgl_lahir' => '2025-06-10', 'id_kel' => 1]);

        $coverage = $this->service->getIdlCoverage(KohortImunisasi::dari(2026), ['id_kelurahan' => 99]);

        $this->assertSame(0, $coverage['total']);
        $this->assertSame(0.0, $coverage['persen']);
        $this->assertFalse(is_nan($coverage['persen']), 'Penyebut nol tidak boleh menghasilkan NAN di JSON grafik.');
    }

    public function test_butuh_kejar_tidak_berubah_saat_tahun_kohort_diganti(): void
    {
        // Angka operasional: dihitung atas tanggal berjalan, sengaja TIDAK
        // mengikuti dropdown tahun. Kartunya menaut ke halaman Proyeksi yang
        // juga memakai tanggal berjalan — kalau ikut kohort, keduanya tak
        // akan pernah cocok. CURDATE() dievaluasi MySQL, jadi tanggalnya
        // relatif (now()), bukan absolut seperti test kohort di atas.
        $this->anak(['tgl_lahir' => now()->subMonths(18)->toDateString()]);
        $this->anak(['tgl_lahir' => now()->subMonths(30)->toDateString()]);

        $acuan = $this->service->getButuhKejar();

        foreach ([2026, 2025, 2024] as $tahun) {
            $this->service->getIdlCoverage(KohortImunisasi::dari($tahun));
            $this->assertSame($acuan, $this->service->getButuhKejar(), "butuh_kejar bergeser saat kohort {$tahun} dihitung.");
        }
    }

    public function test_alasan_tidak_imunisasi_dihitung_dari_kunjungan_terakhir_per_anak(): void
    {
        $known = config('imunisasi.alasan_tidak_imunisasi', []);
        $this->assertNotEmpty($known, 'Config alasan harus ada agar bucket "Lainnya" bisa diuji.');
        $alasanDikenal = $known[0];

        $a = $this->anak();
        \App\Models\DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-01-10', 'bln' => 1, 'posisi' => 'L', 'tb' => 50, 'bb' => 4, 'lla' => 10, 'lk' => 35, 'id_user' => 1, 'alasan_tidak_imunisasi' => 'Sembarang teks lama']);
        \App\Models\DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-03-10', 'bln' => 3, 'posisi' => 'L', 'tb' => 55, 'bb' => 5, 'lla' => 11, 'lk' => 37, 'id_user' => 1, 'alasan_tidak_imunisasi' => $alasanDikenal]);
        $b = $this->anak();
        \App\Models\DataAnak::create(['id_anak' => $b->id, 'tgl_kunjungan' => '2026-02-01', 'bln' => 2, 'posisi' => 'L', 'tb' => 52, 'bb' => 4.5, 'lla' => 10, 'lk' => 36, 'id_user' => 1, 'alasan_tidak_imunisasi' => 'Teks bebas tak dikenal']);

        $hasil = $this->service->getAlasanTidakImunisasi([]);

        $this->assertSame([$alasanDikenal => 1, 'Lainnya' => 1], $hasil, 'Hanya kunjungan TERAKHIR per anak; teks tak dikenal masuk bucket Lainnya.');
    }
}
