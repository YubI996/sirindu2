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
 * TN-01 mengidap pola bug yang sama dengan tiga formulir lain (lihat CLAUDE.md):
 * blade membaca properti yang bukan kolom (`tanggal_penyelidikan`, `nama_ibu`,
 * `anak_ke`, `usia_ibu`) sehingga null senyap, dan Q1/Q4–Q8 serta Q11–Q19
 * dicetak `$rb(false)` padahal Bagian TN sudah lama menyimpan jawabannya.
 */
class FormulirTn01RendersTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCase(array $overrides = []): SurveillanceCase
    {
        $kec = Kecamatan::factory()->create();
        $kel = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt  = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $jk  = JenisKasusEpidemiologi::factory()->create([
            'kode_penyakit' => 'TETANUS_NEO',
            'nama_penyakit' => 'Tetanus Neonatorum',
        ]);

        return SurveillanceCase::factory()->create(array_merge([
            'id_kec'             => $kec->id,
            'id_kel'             => $kel->id,
            'id_rt'              => $rt->id,
            'id_jenis_kasus'     => $jk->id,
            'tanggal_lahir'      => '2026-05-01',
            'tanggal_onset'      => '2026-05-06',
            'tanggal_lapor'      => '2026-05-08',
            'tanggal_penyidikan' => '2026-05-09',
        ], $overrides));
    }

    private function render(SurveillanceCase $case): string
    {
        return View::make('admin.epidemiologi.pdf.formulir-tn01', [
            'case'    => $case->fresh(['jenisKasus', 'kecamatan', 'kelurahan', 'petugasInput']),
            'disease' => $case->jenisKasus,
        ])->render();
    }

    /** Potong satu baris tabel yang memuat $needle agar assertion tak bocor ke baris lain. */
    private function baris(string $html, string $needle): string
    {
        $pola = '/<tr>(?:(?!<\/tr>).)*' . preg_quote($needle, '/') . '.*?<\/tr>/s';
        $this->assertMatchesRegularExpression($pola, $html, "Baris '$needle' tidak ditemukan");
        preg_match($pola, $html, $m);

        return $m[0];
    }

    public function test_tanggal_pelacakan_diambil_dari_tanggal_penyidikan(): void
    {
        $html = $this->render($this->makeCase());

        $this->assertStringContainsString('09-May-2026', $html);
    }

    /** Nama ibu diambil dari Bag A "Nama Orang Tua" (dulu baca kolom hantu nama_ibu). */
    public function test_nama_ibu_diambil_dari_nama_orang_tua(): void
    {
        $html = $this->render($this->makeCase(['nama_orang_tua' => 'Siti Maimunah']));

        $this->assertStringContainsString('Siti Maimunah', $html);
    }

    public function test_lama_tinggal_di_desa_tercetak(): void
    {
        $html = $this->render($this->makeCase(['lama_tinggal_desa' => '5 tahun']));

        $this->assertStringContainsString('5 tahun', $this->baris($html, 'tinggal di desa ini?'));
    }

    /** Q1 & Q4–Q8: enum ya/tidak/tidak_tahu dari Bagian TN. */
    public function test_pertanyaan_kelahiran_bayi_diambil_dari_bagian_tn(): void
    {
        $html = $this->render($this->makeCase([
            'bayi_lahir_hidup'      => 'ya',
            'bayi_menangis_lahir'   => 'tidak',
            'tanda_kelahiran_hidup' => 'tidak_tahu',
            'bayi_bisa_menyusu'     => 'ya',
            'bayi_mulut_mencucu'    => 'ya',
            'bayi_mudah_kejang'     => 'tidak',
        ]));

        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> a\. Ya/', $this->baris($html, 'bayi lahir hidup?'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> b\. Tidak/', $this->baris($html, 'apakah bayi menangis?'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> c\. Tidak Tahu/', $this->baris($html, 'tanda kelahiran hidup'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> a\. Ya/', $this->baris($html, 'bisa menyusu/minum'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> a\. Ya/', $this->baris($html, 'mulut bayi mencucu'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> b\. Tidak/', $this->baris($html, 'mudah kejang'));
    }

    /** Tanpa data, tak satu pun radio boleh terisi. */
    public function test_pertanyaan_kosong_tidak_mengisi_radio(): void
    {
        $html = $this->render($this->makeCase([
            'bayi_lahir_hidup'    => null,
            'bayi_menangis_lahir' => null,
        ]));

        $this->assertDoesNotMatchRegularExpression('/rb-on/', $this->baris($html, 'bayi lahir hidup?'));
        $this->assertDoesNotMatchRegularExpression('/rb-on/', $this->baris($html, 'apakah bayi menangis?'));
    }

    /** Q3: umur bayi saat meninggal memakai nilai tersimpan bila ada. */
    public function test_umur_bayi_meninggal_memakai_nilai_tersimpan(): void
    {
        $html = $this->render($this->makeCase([
            'kondisi_akhir'            => 'meninggal',
            'tanggal_kondisi_akhir'    => '2026-05-10',
            'umur_bayi_meninggal_hari' => 9,
        ]));

        $this->assertStringContainsString('9 hari', $this->baris($html, 'umur (hari)'));
    }

    /** Q11–Q13: riwayat pemeriksaan kehamilan. */
    public function test_pemeriksaan_kehamilan_diambil_dari_bagian_tn(): void
    {
        $html = $this->render($this->makeCase([
            'jumlah_kunjungan_anc'     => 4,
            'tempat_pemeriksaan_hamil' => 'Puskesmas Bontang Utara 2',
            'pemeriksa_kehamilan'      => 'bidan_perawat',
        ]));

        $this->assertStringContainsString('4', $this->baris($html, 'kunjungan ibu hamil'));
        $this->assertStringContainsString('Puskesmas Bontang Utara 2', $this->baris($html, 'Tempat pemeriksaan Ibu Hamil'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> b\. Bidan\/Perawat/', $this->baris($html, 'Pemeriksaan kehamilan oleh'));
    }

    /** Q14–Q19: riwayat persalinan. */
    public function test_riwayat_persalinan_diambil_dari_bagian_tn(): void
    {
        $html = $this->render($this->makeCase([
            'tempat_persalinan'      => 'puskesmas',
            'usia_kehamilan_bulan'   => 9,
            'penolong_persalinan'    => 'dokter',
            'alat_potong_tali_pusat' => 'gunting',
            'perawatan_tali_pusat'   => 'ramuan_tradisional',
            'keadaan_ibu_saat_ini'   => 'hidup',
        ]));

        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> Puskesmas/', $this->baris($html, 'Tempat persalinan'));
        $this->assertStringContainsString('9', $this->baris($html, 'Usia kehamilan ibu saat persalinan'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> a\. Dokter/', $this->baris($html, 'Penolong persalinan'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> a\. Gunting/', $this->baris($html, 'Alat potong tali pusat'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> c\. Ramuan tradisional/', $this->baris($html, 'Perawatan tali pusat'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> a\. Hidup/', $this->baris($html, 'Keadaan ibu saat ini'));
    }

    /** Data lama berupa teks bebas (sebelum isian jadi pilihan) tetap tercentang benar. */
    public function test_teks_bebas_lama_tetap_dikenali(): void
    {
        $html = $this->render($this->makeCase([
            'penolong_persalinan'    => 'Bidan desa',
            'alat_potong_tali_pusat' => 'Silet baru',
            'keadaan_ibu_saat_ini'   => 'Meninggal saat persalinan',
        ]));

        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> b\. Bidan\/Perawat/', $this->baris($html, 'Penolong persalinan'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> b\. Silet/', $this->baris($html, 'Alat potong tali pusat'));
        $this->assertMatchesRegularExpression('/rb-on[^>]*><\/span> b\. Meninggal/', $this->baris($html, 'Keadaan ibu saat ini'));
    }

    /** Petugas pelaksana = pelapor kasus, konsisten dengan FP-1 & PERT-01. */
    public function test_petugas_pelaksana_memakai_nama_pelapor(): void
    {
        $html = $this->render($this->makeCase(['nama_pelapor' => 'Ns. Dewi Lestari']));

        $this->assertStringContainsString('( Ns. Dewi Lestari )', $html);
    }
}
