<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmGrafikTest extends TestCase
{
    use RefreshDatabase;

    private function kategori(string $nama, array $angka): SpmKategori
    {
        $kategori = SpmKategori::create(['nama' => $nama, 'satuan' => 'orang']);
        SpmCapaian::create(array_merge(['id_kategori' => $kategori->id, 'tahun' => now()->year], $angka));

        return $kategori;
    }

    private function buka()
    {
        return $this->actingAs(User::factory()->create(['type' => 0]))
            ->get(route('admin.spm.dashboard'))
            ->assertStatus(200);
    }

    public function test_batang_diurutkan_paling_tertinggal_di_atas(): void
    {
        $this->kategori('Pelayanan Tinggi', ['sasaran' => 100, 'tw1' => 90]);   // 90 %
        $this->kategori('Pelayanan Rendah', ['sasaran' => 100, 'tw1' => 10]);   // 10 %
        $this->kategori('Pelayanan Sedang', ['sasaran' => 100, 'tw1' => 50]);   // 50 %

        $batang = $this->buka()->viewData('grafik')['batang'];

        $this->assertSame(['Pelayanan Rendah', 'Pelayanan Sedang', 'Pelayanan Tinggi'], array_column($batang, 'nama'));
    }

    public function test_urutan_batang_mengikuti_laju_bukan_persen_mentah(): void
    {
        // Persen tanpa triwulan acuannya tidak bermakna — itu seluruh dasar
        // modul ini. Panel "paling tertinggal di atas" tidak boleh menaruh
        // kategori merah DI BAWAH kategori kuning hanya karena persennya lebih besar.
        $september = \Carbon\CarbonImmutable::create(now()->year, 9, 10);
        \Carbon\CarbonImmutable::setTestNow($september);
        \Carbon\Carbon::setTestNow($september);

        try {
            // Baru lapor TW I: 20/100 = 20%, prorata 25 → rasio 0,80 = tertinggal.
            $this->kategori('Lapor TW I saja', ['sasaran' => 100, 'tw1' => 20]);
            // Lapor sampai TW IV: 50/100 = 50%, prorata 100 → rasio 0,50 = kritis.
            $this->kategori('Lapor sampai TW IV', [
                'sasaran' => 100, 'tw1' => 20, 'tw2' => 10, 'tw3' => 10, 'tw4' => 10,
            ]);

            $batang = $this->buka()->viewData('grafik')['batang'];

            $this->assertSame(
                ['Lapor sampai TW IV', 'Lapor TW I saja'],
                array_column($batang, 'nama'),
                'yang kritis (rasio 0,50) harus di atas yang tertinggal (rasio 0,80) walau persennya lebih besar',
            );
        } finally {
            \Carbon\CarbonImmutable::setTestNow();
            \Carbon\Carbon::setTestNow();
        }
    }

    public function test_kategori_tanpa_persen_ditaruh_terakhir(): void
    {
        $this->kategori('Pelayanan Rendah', ['sasaran' => 100, 'tw1' => 10]);
        $this->kategori('Pelayanan Belum', ['sasaran' => 100]);

        $batang = $this->buka()->viewData('grafik')['batang'];

        $this->assertSame('Pelayanan Rendah', $batang[0]['nama']);
        $this->assertNull($batang[1]['persen']);
        $this->assertSame('Pelayanan Belum', $batang[1]['nama']);
    }

    public function test_garis_membawa_kumulatif_dan_prorata_penuh(): void
    {
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 200, 'tw2' => 150]);

        $garis = $this->buka()->viewData('grafik')['garis'];

        $this->assertSame([200.0, 350.0, null, null], $garis[0]['kumulatif']);
        $this->assertSame([250.0, 500.0, 750.0, 1000.0], $garis[0]['prorata'], 'garis target selalu penuh 4 titik');
    }

    public function test_halaman_memuat_kedua_kanvas_dan_pemilih_kategori(): void
    {
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 200]);

        $this->buka()
            ->assertSee('spmBatang', false)
            ->assertSee('spmGaris', false)
            ->assertSee('spmPilihKategori', false)
            ->assertSee('cdn.jsdelivr.net/npm/chart.js', false);
    }

    public function test_kedua_kanvas_dibungkus_kontainer_bertinggi_tetap(): void
    {
        // maintainAspectRatio:false membuat Chart.js MENGABAIKAN atribut height
        // pada <canvas> dan mengikuti tinggi kontainernya. Tanpa kontainer
        // bertinggi tetap, grafik memanjang melebihi satu layar.
        $this->kategori('Pelayanan A', ['sasaran' => 1000, 'tw1' => 200]);

        $html = $this->buka()->getContent();

        $this->assertMatchesRegularExpression(
            '/<div[^>]*class="spm-kanvas"[^>]*style="[^"]*height:\s*\d+px[^"]*"[^>]*>\s*<canvas id="spmBatang"/',
            $html,
            'kanvas batang harus dibungkus .spm-kanvas bertinggi tetap',
        );
        $this->assertMatchesRegularExpression(
            '/<div[^>]*class="spm-kanvas"[^>]*style="[^"]*height:\s*\d+px[^"]*"[^>]*>\s*<canvas id="spmGaris"/',
            $html,
            'kanvas garis harus dibungkus .spm-kanvas bertinggi tetap',
        );
    }

    public function test_tinggi_kanvas_batang_mengikuti_jumlah_kategori(): void
    {
        foreach (range(1, 12) as $i) {
            $this->kategori('Pelayanan ' . $i, ['sasaran' => 100, 'tw1' => 10]);
        }

        $html = $this->buka()->getContent();

        preg_match('/<div[^>]*class="spm-kanvas"[^>]*style="[^"]*height:\s*(\d+)px[^"]*"[^>]*>\s*<canvas id="spmBatang"/', $html, $m);

        $this->assertNotEmpty($m, 'kontainer kanvas batang tidak ditemukan');
        $this->assertGreaterThan(300, (int) $m[1], '12 kategori butuh kanvas lebih tinggi dari minimumnya');
    }

    public function test_tanpa_kategori_tidak_memuat_chart_js(): void
    {
        $this->buka()->assertDontSee('cdn.jsdelivr.net/npm/chart.js', false);
    }

    public function test_nama_kategori_berisi_html_ter_escape_di_payload_json(): void
    {
        $this->kategori('<script>alert(1)</script>', ['sasaran' => 100, 'tw1' => 10]);

        $html = $this->buka()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}
