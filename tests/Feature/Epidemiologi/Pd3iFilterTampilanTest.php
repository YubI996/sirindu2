<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use App\Support\FilterPd3i;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Kontrol filter Faskes Pelapor & Tgl lapor di dasbor, catatan grafik lintas
 * tahun, dan baris periode/faskes di header PDF.
 */
class Pd3iFilterTampilanTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $kec   = Kecamatan::factory()->create();
        $kel   = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt    = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $jenis = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'UJI_TAMPIL', 'nama_penyakit' => 'Uji Tampil', 'is_active' => true]);
        $this->admin = User::factory()->create(['type' => 0, 'role' => 'superadmin']);

        foreach (['PKM Uji Tampil', '', null] as $instansi) {
            SurveillanceCase::factory()->create([
                'id_jenis_kasus' => $jenis->id, 'id_kec' => $kec->id, 'id_kel' => $kel->id, 'id_rt' => $rt->id,
                'instansi_pelapor' => $instansi, 'tanggal_lapor' => '2026-06-15',
                'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
            ]);
        }
    }

    public function test_dasbor_menampilkan_filter_faskes_dan_tanggal(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pd3i.dashboard'))
            ->assertOk()
            ->assertViewHas('faskesPelapors', fn ($c) => $c->contains('PKM Uji Tampil') && !$c->contains('') && !$c->contains(null))
            ->assertSee('data-ms="faskes_pelapor"', false)
            ->assertSee('id="filter-tgl-dari"', false)
            ->assertSee('id="filter-tgl-sampai"', false)
            ->assertSee("params.append('faskes_pelapor[]'", false)
            ->assertSee("params.set('tanggal_dari'", false)
            ->assertSee('Grafik ini tidak mengikuti rentang tanggal laporan.');
    }

    /**
     * min/max input date hanya membatasi pemilih; tahun lain masih bisa diketik.
     * Server mengabaikannya, jadi layar tak boleh memperlakukannya sebagai filter
     * aktif: nilai tak sah tidak dikirim dan inputnya ditandai is-invalid.
     */
    public function test_tanggal_yang_diketik_di_luar_tahun_ditandai_dan_tak_dikirim(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pd3i.dashboard'))
            ->assertOk()
            ->assertSee('el.checkValidity()', false)
            ->assertSee("classList.toggle('is-invalid'", false);
    }

    public function test_header_pdf_mencantumkan_periode_dan_faskes(): void
    {
        $repo = app(SurveillanceRepository::class);
        $f    = new FilterPd3i(tahun: 2026, faskesPelapor: ['PKM Uji Tampil'], tanggalDari: '2026-05-01', tanggalSampai: '2026-06-30');

        $html = view('admin.epidemiologi.pdf.pd3i-dashboard', [
            'tahun' => 2026, 'wilker' => null, 'namaKabKota' => null, 'namaKelurahan' => null, 'namaJenisKasus' => null,
            'periode' => $f->labelPeriode(), 'namaFaskes' => 'PKM Uji Tampil',
            'kinerja' => $repo->getPd3iKinerja($f), 'demografi' => $repo->getPd3iDemografi($f),
            'tren' => $repo->getPd3iTren($f), 'wilayah' => $repo->getPd3iWilayah($f),
        ])->render();

        $this->assertStringContainsString('1 Mei 2026 – 30 Jun 2026', $html);
        $this->assertStringContainsString('Faskes Pelapor:</strong> PKM Uji Tampil', $html);
        // Footer tiap halaman ikut menyebut faskes (audit UX-008).
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'PKM Uji Tampil'));
        $this->assertStringContainsString('<title>Laporan Surveilans PD3I — 1 Mei 2026 – 30 Jun 2026</title>', $html);
    }

    public function test_ekspor_pdf_dengan_filter_baru_berhasil(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pd3i.exportPdf'), [
            'tahun' => 2026, 'faskes_pelapor' => ['PKM Uji Tampil'],
            'tanggal_dari' => '2026-05-01', 'tanggal_sampai' => '2026-06-30',
        ])->assertOk()
            ->assertDownload('pd3i-dashboard-2026_2026-05-01_sd_2026-06-30.pdf');
    }

    /**
     * Audit A11Y-001/UX-001/UX-004: tanggal tak sah wajib punya pesan teks (bukan hanya warna),
     * periode yang berlaku terlihat & diumumkan, catatan terhubung ke grafiknya.
     */
    public function test_umpan_balik_periode_dan_tanggal_tak_sah(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pd3i.dashboard'))
            ->assertOk()
            ->assertSee('id="periode-aktif"', false)
            ->assertSee('role="status"', false)
            ->assertSee('id="filter-tgl-dari-galat"', false)
            ->assertSee('aria-describedby="filter-tgl-dari-galat"', false)
            ->assertSee('aria-describedby="catatan-tahunan"', false)
            ->assertSee('aria-describedby="catatan-epiweek"', false)
            ->assertSee('id="catatan-bulanan"', false)
            ->assertSee('Tgl Lapor Sampai')
            ->assertSee('tundaTanggal', false);
    }
}
