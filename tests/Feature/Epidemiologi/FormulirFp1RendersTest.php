<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Mengunci perbaikan formulir FP-1 (AFP): dulu blade membaca kolom yang TIDAK ADA
 * (tanggal_penyelidikan, tanggal_lumpuh) sehingga selalu kosong. Sekarang:
 *  - "Tanggal Penyelidikan"        ← tanggal_penyidikan
 *  - "Tanggal mulai lemah/lumpuh"  ← tanggal_onset (untuk AFP, onset = tgl lumpuh)
 *  - "Tanggal mulai sakit sebelum lumpuh" ← tanggal_demam (prodromal AFP)
 */
class FormulirFp1RendersTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCase(array $overrides = []): SurveillanceCase
    {
        $kec = Kecamatan::factory()->create();
        $kel = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt  = Rt::factory()->create(['id_kelurahan' => $kel->id]);

        return SurveillanceCase::factory()->create(array_merge([
            'id_kec'            => $kec->id,
            'id_kel'            => $kel->id,
            'id_rt'             => $rt->id,
            'tanggal_lahir'     => '2020-01-01',
            'tanggal_demam'     => '2026-03-01',
            'tanggal_onset'     => '2026-03-05',
            'tanggal_penyidikan' => '2026-03-10',
            'instansi_pelapor'  => 'Bontang Utara 1',
        ], $overrides));
    }

    /** Potong satu baris tabel yang memuat $needle — assertion jadi terkurung di baris itu. */
    private function baris(string $html, string $needle): string
    {
        $this->assertMatchesRegularExpression(
            '/<tr>(?:(?!<\/tr>).)*' . preg_quote($needle, '/') . '.*?<\/tr>/s',
            $html,
            "Baris yang memuat '$needle' tidak ditemukan"
        );
        preg_match('/<tr>(?:(?!<\/tr>).)*' . preg_quote($needle, '/') . '.*?<\/tr>/s', $html, $m);

        return $m[0];
    }

    private function render(SurveillanceCase $case): string
    {
        return View::make('admin.epidemiologi.pdf.formulir-fp1', [
            'case' => $case->fresh(['jenisKasus', 'kecamatan', 'kelurahan', 'spesimen', 'imunisasi', 'faskesBerobat', 'petugasInput']),
        ])->render();
    }

    public function test_fp1_menampilkan_tanggal_penyidikan_dan_onset_sebagai_tgl_lumpuh(): void
    {
        $case = $this->makeCase();

        $html = View::make('admin.epidemiologi.pdf.formulir-fp1', ['case' => $case])->render();

        // tanggal_penyidikan kini tampil di sel "Tanggal Penyelidikan"
        $this->assertStringContainsString('10-Mar-2026', $html);
        // tanggal_onset kini tampil di sel "Tanggal mulai lemah/lumpuh"
        $this->assertStringContainsString('05-Mar-2026', $html);
        // tanggal_demam kini tampil di sel "mulai sakit sebelum lumpuh"
        $this->assertStringContainsString('01-Mar-2026', $html);
        // instansi pelapor tampil
        $this->assertStringContainsString('Bontang Utara 1', $html);
    }

    /** Bag G → baris kestrad & Rumah Sakit (dulu ditebak dari status_rawat). */
    public function test_pengobatan_tradisional_dan_rs_diambil_dari_bagian_g(): void
    {
        $case = $this->makeCase();
        $case->faskesBerobat()->create([
            'urutan' => 1, 'jenis_faskes' => 'pengobatan_tradisional',
            'nama_faskes' => 'Sinshe Pak Karto', 'tanggal_berobat' => '2026-03-06',
        ]);
        $case->faskesBerobat()->create([
            'urutan' => 2, 'jenis_faskes' => 'rs',
            'nama_faskes' => 'RSUD Taman Husada', 'tanggal_berobat' => '2026-03-08',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Sinshe Pak Karto', $html);
        $this->assertStringContainsString('06-Mar-2026', $html);
        $this->assertStringContainsString('RSUD Taman Husada', $html);
        $this->assertStringContainsString('08-Mar-2026', $html);
    }

    /** Bag G2 → diagnosis dokter, nama dokter, no. HP dokter. */
    public function test_diagnosis_dan_dokter_diambil_dari_bagian_g2(): void
    {
        $case = $this->makeCase([
            'diagnosis_dokter' => 'Guillain-Barré Syndrome',
            'nama_dokter'      => 'dr. Rina Sp.A',
            'no_telp_dokter'   => '08123456789',
            'no_rekam_medik'   => 'RM-77123',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Guillain-Barré Syndrome', $html);
        $this->assertStringContainsString('dr. Rina Sp.A', $html);
        $this->assertStringContainsString('08123456789', $html);
        $this->assertStringContainsString('RM-77123', $html);
    }

    /** Bag D3 → tiga pertanyaan penentu stop-investigasi. */
    public function test_sifat_kelumpuhan_diambil_dari_bagian_d3(): void
    {
        $case = $this->makeCase([
            'kelumpuhan_akut'      => 'ya',
            'kelumpuhan_flaccid'   => 'ya',
            'kelumpuhan_rudapaksa' => 'tidak',
        ]);

        $html = $this->render($case);

        $akut = $this->baris($html, 'akut (1-14 hari)?');
        $this->assertMatchesRegularExpression('/cb-checked[^>]*>[^<]*<\/span> Ya/', $akut);
        $this->assertDoesNotMatchRegularExpression('/cb-checked[^>]*>[^<]*<\/span> Tidak/', $akut);

        $rudapaksa = $this->baris($html, 'rudapaksa?');
        $this->assertMatchesRegularExpression('/cb-checked[^>]*>[^<]*<\/span> Tidak/', $rudapaksa);
        $this->assertDoesNotMatchRegularExpression('/cb-checked[^>]*>[^<]*<\/span> Ya/', $rudapaksa);
    }

    /** Tanpa data D3, tak satu pun kotak boleh tercentang (dulu selalu kosong — kini harus tetap kosong). */
    public function test_sifat_kelumpuhan_kosong_tidak_mencentang_apa_pun(): void
    {
        $html = $this->render($this->makeCase([
            'kelumpuhan_akut'      => null,
            'kelumpuhan_flaccid'   => null,
            'kelumpuhan_rudapaksa' => null,
        ]));

        $this->assertDoesNotMatchRegularExpression('/cb-checked/', $this->baris($html, 'akut (1-14 hari)?'));
        $this->assertDoesNotMatchRegularExpression('/cb-checked/', $this->baris($html, 'rudapaksa?'));
    }

    /** Bag D3 → grid anggota gerak: kelumpuhan, kekuatan otot, gangguan rasa raba. */
    public function test_grid_anggota_gerak_diambil_dari_bagian_d3(): void
    {
        $case = $this->makeCase([
            'tanda_tungkai_kanan'     => 'Lumpuh total',
            'tanda_tungkai_kiri'      => 'Lemah',
            'kekuatan_otot'           => 2,
            'rasa_raba_tungkai_kanan' => 'ya',
            'rasa_raba_tungkai_kiri'  => 'tidak',
            'lokasi_kelemahan_lain'   => 'Otot leher',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Lumpuh total', $html);
        $this->assertStringContainsString('Lemah', $html);
        $this->assertStringContainsString('Otot leher', $html);
        // kekuatan otot tampil di baris tungkai yang lumpuh, dan rasa raba ikut tercentang
        $barisTungkaiKanan = $this->baris($html, 'Tungkai kanan');
        $this->assertMatchesRegularExpression('/>\s*2\s*</', $barisTungkaiKanan);
        $this->assertStringContainsString('Lumpuh total', $barisTungkaiKanan);
        // baris lengan kanan tidak diisi apa pun -> tetap kosong
        $this->assertDoesNotMatchRegularExpression('/cb-checked/', $this->baris($html, 'Lengan kanan'));
    }

    /** Bag D3 → kontak polio oral & seluruh blok sanitasi dasar. */
    public function test_kontak_polio_dan_sanitasi_diambil_dari_bagian_d3(): void
    {
        $case = $this->makeCase([
            'kontak_polio_oral'     => 'tidak',
            'jamban_sendiri'        => 'ya',
            'jenis_jamban'          => 'leher_angsa_septic',
            'selalu_gunakan_jamban' => 'kadang_kadang',
            'jamban_saluran_kedap'  => 'ya',
            'pembuangan_diapers'    => 'dibakar',
        ]);

        $html = $this->render($case);

        $this->assertMatchesRegularExpression('/imunisasi polio oral\?<\/td>\s*<td>[^<]*<span class="cb"><\/span> Ya[^<]*<span class="cb cb-checked"/s', $html);
        $this->assertMatchesRegularExpression('/Jamban leher angsa dengan septic tank/', $html);
        $this->assertMatchesRegularExpression('/cb-checked[^>]*>[^<]*<\/span> Jamban leher angsa/', $html);
        $this->assertMatchesRegularExpression('/cb-checked[^>]*>[^<]*<\/span> Kadang/', $html);
        $this->assertMatchesRegularExpression('/cb-checked[^>]*>[^<]*<\/span> Dibakar/', $html);
    }

    /** Bag E → status imunisasi polio per antigen. */
    public function test_status_imunisasi_polio_diambil_dari_bagian_e(): void
    {
        $case = $this->makeCase();
        $case->imunisasi()->create([
            'imunisasi_ke' => 1, 'nama_antigen' => 'OPV rutin', 'diberikan' => 'ya',
            'sumber_informasi' => 'Buku KIA', 'tanggal_imunisasi' => '2025-06-10',
        ]);
        $case->imunisasi()->create([
            'imunisasi_ke' => 2, 'nama_antigen' => 'IPV rutin', 'diberikan' => 'tidak',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Buku KIA', $html);
        $this->assertStringContainsString('10-Jun-2025', $html);
        $this->assertMatchesRegularExpression('/Imunisasi rutin — IPV.*?cb-checked[^>]*>[^<]*<\/span> Belum pernah/s', $html);
    }

    /** Bag E → baris bepergian 35 hari (dulu hanya membaca teks riwayat_perjalanan). */
    public function test_riwayat_bepergian_diambil_dari_bagian_e(): void
    {
        $html = $this->render($this->makeCase([
            'riwayat_bepergian'  => 'ya',
            'lokasi_bepergian'   => 'Balikpapan',
            'riwayat_perjalanan' => null,
        ]));

        $baris = $this->baris($html, 'pernah bepergian ke luar kab/prov/negeri?');
        $this->assertStringContainsString('Balikpapan', $baris);
        $this->assertMatchesRegularExpression('/cb-checked[^>]*>[^<]*<\/span> Ya/', $baris);
    }

    /** Petugas investigasi = pelapor kasus (Bag B), bukan user yang menekan tombol export. */
    public function test_petugas_investigasi_memakai_nama_pelapor(): void
    {
        $html = $this->render($this->makeCase(['nama_pelapor' => 'Ns. Dewi Lestari']));

        $this->assertStringContainsString('Ns. Dewi Lestari', $html);
    }
}
