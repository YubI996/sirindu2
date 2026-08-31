<?php

namespace Tests\Feature\Imunisasi;

use App\Models\Anak;
use App\Services\ImunisasiStatusService;
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

    public function test_sasaran_menghitung_bayi_0_11_bulan_dan_baduta_terpisah(): void
    {
        $this->anak(['tgl_lahir' => now()->subMonths(5)->toDateString()]);   // bayi, balita
        $this->anak(['tgl_lahir' => now()->subMonths(9)->toDateString()]);   // bayi, balita
        $this->anak(['tgl_lahir' => now()->subMonths(18)->toDateString()]);  // baduta (IBL 12-23 bln), balita
        $this->anak(['tgl_lahir' => now()->subMonths(30)->toDateString()]);  // balita, bukan bayi/baduta
        $this->anak(['tgl_lahir' => now()->subMonths(70)->toDateString()]);  // di luar balita (>59 bln)

        $sasaran = $this->service->getRingkasanSasaran();

        $this->assertSame(2, $sasaran['bayi']);
        $this->assertSame(1, $sasaran['baduta']);
        $this->assertSame(12, $sasaran['baduta_min'], 'Rentang usia baduta harus ikut dikembalikan, dipakai label kartu di view.');
        $this->assertSame(23, $sasaran['baduta_max']);
        $this->assertSame(4, $sasaran['balita'], 'Balita = 0-59 bulan, mencakup bayi & baduta di dalamnya.');
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

    public function test_funnel_dosis_menghitung_jumlah_sudah_per_antigen_berurutan(): void
    {
        $anak1 = $this->anak(['tgl_lahir' => now()->subMonths(24)->toDateString()]);
        $this->beriVaksin($anak1, 'HB0');
        $this->beriVaksin($anak1, 'DPT-HB-HIB1');
        $this->beriVaksin($anak1, 'DPT-HB-HIB3');

        $anak2 = $this->anak(['tgl_lahir' => now()->subMonths(24)->toDateString()]);
        $this->beriVaksin($anak2, 'HB0');
        $this->beriVaksin($anak2, 'DPT-HB-HIB1');

        $funnel = collect($this->service->getFunnelDosis())->keyBy('kode');

        $this->assertSame(2, $funnel['HB0']['jumlah']);
        $this->assertSame(2, $funnel['DPT-HB-HIB1']['jumlah']);
        $this->assertSame(1, $funnel['DPT-HB-HIB3']['jumlah']);
    }

    public function test_cakupan_antigen_hanya_menghitung_anak_yang_sudah_lewat_jendela_dan_kecuali_kategori_tambahan(): void
    {
        // BCG: usia_pemberian_max = 30 hari. Anak 6 bulan sudah lewat jendela → eligible.
        $sudah = $this->anak(['tgl_lahir' => now()->subMonths(6)->toDateString()]);
        $this->beriVaksin($sudah, 'BCG');

        $belumWaktu = $this->anak(['tgl_lahir' => now()->toDateString()]); // baru lahir, BCG belum jatuh tempo (belum lewat 30 hari)

        $cakupan = collect($this->service->getCakupanAntigen())->keyBy('kode');

        $this->assertArrayHasKey('BCG', $cakupan);
        $this->assertSame(1, $cakupan['BCG']['jumlah_sudah']);
        $this->assertSame(1, $cakupan['BCG']['jumlah_eligible']);
        $this->assertArrayNotHasKey('HPV1', $cakupan, 'Antigen kategori Tambahan (BIAS) tidak ikut dihitung di dashboard rutin.');
    }

    public function test_kohort_wilayah_menghitung_jumlah_rt_dan_populasi_per_kelurahan(): void
    {
        $kec = \App\Models\Kecamatan::factory()->create(['name' => 'Bontang Utara']);
        $kel = \App\Models\Kelurahan::factory()->create(['id_kecamatan' => $kec->id, 'name' => 'Api-Api']);
        \App\Models\Rt::factory()->count(3)->create(['id_kelurahan' => $kel->id]);

        $this->anak(['id_kec' => $kec->id, 'id_kel' => $kel->id, 'tgl_lahir' => now()->subMonths(5)->toDateString()]); // bayi
        $this->anak(['id_kec' => $kec->id, 'id_kel' => $kel->id, 'tgl_lahir' => now()->subMonths(18)->toDateString()]); // baduta

        $kohort = collect($this->service->getKohortWilayah())->keyBy('nama');

        $this->assertArrayHasKey('Bontang Utara', $kohort);
        $kelurahanList = collect($kohort['Bontang Utara']['kelurahan'])->keyBy('nama');
        $this->assertSame(3, $kelurahanList['Api-Api']['jumlah_rt']);
        $this->assertSame(2, $kelurahanList['Api-Api']['total']);
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

    public function test_rincian_puskesmas_mengelompokkan_anak_lewat_catchment_kelurahan(): void
    {
        \App\Support\WilkerPuskesmas::flushCache(); // hindari cache statis dari test lain di proses PHPUnit yang sama.
        \App\Models\Puskesmas::factory()->create(['name' => 'Bontang Utara 1']);
        $kel = \App\Models\Kelurahan::factory()->create(['name' => 'API-API']); // nama kanonik WilkerPuskesmas

        $lengkap = $this->anak(['id_kel' => $kel->id, 'tgl_lahir' => now()->subMonths(24)->toDateString()]);
        foreach (\App\Models\JenisVaksin::where('id_kelompok_vaksin', \App\Models\KelompokVaksin::where('kode', 'IDL')->value('id'))->pluck('kode') as $kode) {
            $this->beriVaksin($lengkap, $kode);
        }
        $this->anak(['id_kel' => $kel->id, 'tgl_lahir' => now()->subMonths(24)->toDateString()]); // tidak lengkap

        $rincian = collect($this->service->getRincianPuskesmas())->keyBy('nama');

        $this->assertArrayHasKey('Bontang Utara 1', $rincian);
        $this->assertSame(2, $rincian['Bontang Utara 1']['sasaran']);
        $this->assertSame(1, $rincian['Bontang Utara 1']['capaian_idl']);
        $this->assertEqualsWithDelta(50.0, $rincian['Bontang Utara 1']['persen'], 0.01);
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

    public function test_ibl_coverage_dihitung_dari_kohort_24_bulan_ke_atas(): void
    {
        $lengkap = $this->anak(['tgl_lahir' => now()->subMonths(30)->toDateString()]);
        $this->beriSemuaVaksinKelompok($lengkap, 'IBL');

        $this->anak(['tgl_lahir' => now()->subMonths(30)->toDateString()]); // belum lengkap
        $this->anak(['tgl_lahir' => now()->subMonths(10)->toDateString()]); // belum masuk kohort (bayi)

        $coverage = $this->service->getIblCoverage();

        $this->assertSame(2, $coverage['total']);
        $this->assertSame(1, $coverage['ibl_lengkap']);
        $this->assertEqualsWithDelta(50.0, $coverage['persen'], 0.01);
    }
}
