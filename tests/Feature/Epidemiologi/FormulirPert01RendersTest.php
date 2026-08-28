<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Mengunci koreksi klien atas formulir PERT-01 (reviu Agustus 2026):
 * Tanggal Pelacakan ← Bag B, No. Kontak Ortu ← Bag A, Apnea & Batuk rejan ← Bag D,
 * No. Rekam Medik ← Bag G, Riwayat Vaksinasi ← Bag E, keluarga sakit sama ← Bag I.
 */
class FormulirPert01RendersTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCase(array $overrides = []): SurveillanceCase
    {
        $kec = Kecamatan::factory()->create();
        $kel = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt  = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $jk  = JenisKasusEpidemiologi::factory()->create([
            'kode_penyakit' => 'PERTUSIS',
            'nama_penyakit' => 'Pertusis',
        ]);

        return SurveillanceCase::factory()->create(array_merge([
            'id_kec'             => $kec->id,
            'id_kel'             => $kel->id,
            'id_rt'              => $rt->id,
            'id_jenis_kasus'     => $jk->id,
            'tanggal_lahir'      => '2024-01-26',
            'tanggal_onset'      => '2026-01-08',
            'tanggal_lapor'      => '2026-02-05',
            'tanggal_penyidikan' => '2026-02-06',
        ], $overrides));
    }

    private function render(SurveillanceCase $case): string
    {
        return View::make('admin.epidemiologi.pdf.formulir-pert01', [
            'case'    => $case->fresh(['jenisKasus', 'kecamatan', 'kelurahan', 'spesimen', 'imunisasi', 'kontakErat']),
            'disease' => $case->jenisKasus,
        ])->render();
    }

    public function test_tanggal_pelacakan_diambil_dari_tanggal_penyidikan(): void
    {
        $html = $this->render($this->makeCase());

        $this->assertStringContainsString('06-Feb-2026', $html);
    }

    public function test_no_kontak_orangtua_diambil_dari_no_hp_orang_tua(): void
    {
        $case = $this->makeCase([
            'nama_orang_tua'  => 'M. Yusransyah',
            'no_hp_orang_tua' => '081355512345',
            'no_telepon'      => '085299998888',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('M. Yusransyah', $html);
        $this->assertStringContainsString('081355512345', $html);
    }

    public function test_apnea_dan_batuk_rejan_diambil_dari_bagian_d(): void
    {
        $case = $this->makeCase([
            'gejala_apnea'       => true,
            'tanggal_apnea'      => '2026-01-12',
            'gejala_batuk_rejan' => true,
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('12-Jan-2026', $html);
        // "Ya" pada baris Apnea terisi, dan checkbox batuk rejan tercentang
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span>\s*Ya\s*&nbsp;\s*<span class="rb"><\/span>\s*Tidak\s*<\/td>\s*<td class="lbl">Tanggal Mulai Apnea/', $html);
        $this->assertMatchesRegularExpression('/cb-on[^>]*>[^<]*<\/span>\s*Batuk rejan/', $html);
    }

    public function test_nomor_rekam_medik_diambil_dari_bagian_g(): void
    {
        $case = $this->makeCase(['no_rekam_medik' => 'RM-00123456']);

        $html = $this->render($case);

        $this->assertStringContainsString('RM-00123456', $html);
    }

    public function test_riwayat_vaksinasi_diambil_dari_bagian_e(): void
    {
        $case = $this->makeCase();
        $case->imunisasi()->create([
            'imunisasi_ke'      => 1,
            'nama_antigen'      => 'DPT-HB-Hib 1',
            'diberikan'         => 'ya',
            'sumber_informasi'  => 'Buku KIA',
            'tanggal_imunisasi' => '2024-03-26',
        ]);
        $case->imunisasi()->create([
            'imunisasi_ke'     => 5,
            'nama_antigen'     => 'ORI',
            'diberikan'        => 'tidak',
            'sumber_informasi' => 'Ingatan responden',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Buku KIA', $html);
        $this->assertStringContainsString('Ingatan responden', $html);
        $this->assertStringContainsString('26-Mar-2024', $html);
    }

    public function test_keluarga_sakit_sama_diambil_dari_kontak_erat(): void
    {
        $case = $this->makeCase();
        $case->kontakErat()->create(['urutan' => 1, 'nama' => 'Ani', 'ada_gejala' => true]);
        $case->kontakErat()->create(['urutan' => 2, 'nama' => 'Budi', 'ada_gejala' => true]);
        $case->kontakErat()->create(['urutan' => 3, 'nama' => 'Cici', 'ada_gejala' => false]);

        $html = $this->render($case);

        // Ya karena ada kontak bergejala, jumlahnya 2 (bukan 3 — hanya yang bergejala)
        $this->assertMatchesRegularExpression('/mengalami sakit sama\?.*?rb-on.*?Ya/s', $html);
        $this->assertMatchesRegularExpression('/Jumlah<\/td>\s*<td[^>]*>\s*2\s*</s', $html);
    }

    public function test_bepergian_diambil_dari_bagian_e(): void
    {
        $case = $this->makeCase([
            'riwayat_bepergian' => 'ya',
            'lokasi_bepergian'  => 'Kutai Timur',
            'tanggal_bepergian' => '2026-01-02',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Kutai Timur', $html);
        $this->assertStringContainsString('02-Jan-2026', $html);
    }

    /** Klien: "nama petugas pelapor" — bukan nama admin yang membuka export. */
    public function test_pelaksana_investigasi_memakai_nama_pelapor(): void
    {
        $html = $this->render($this->makeCase(['nama_pelapor' => 'Ns. Dewi Lestari']));

        $baris = '/Pelaksana investigasi<\/td>\s*<td>Ns\. Dewi Lestari<\/td>/';
        $this->assertMatchesRegularExpression($baris, $html);
    }
}
