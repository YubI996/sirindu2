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
 * Mengunci koreksi klien atas formulir DIF-1 (reviu Agustus 2026).
 *
 * Hampir semua coretan berbunyi "isian diambil dari bagian X": datanya sudah
 * tersimpan, tapi blade membaca nama kolom yang tidak ada (tanggal_penyelidikan,
 * nama_wali, no_hp_wali, alamat_wali, alamat_kerja, antibiotik, obat_lain) atau
 * mencetak checkbox mati (status gizi, spesimen, tempat berobat, ADS, bepergian).
 */
class FormulirDif1RendersTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCase(array $overrides = []): SurveillanceCase
    {
        $kec = Kecamatan::factory()->create();
        $kel = Kelurahan::factory()->create(['id_kecamatan' => $kec->id]);
        $rt  = Rt::factory()->create(['id_kelurahan' => $kel->id]);
        $jk  = JenisKasusEpidemiologi::factory()->create([
            'kode_penyakit' => 'DIFTERI_OBS',
            'nama_penyakit' => 'Difteri',
        ]);

        return SurveillanceCase::factory()->create(array_merge([
            'id_kec'             => $kec->id,
            'id_kel'             => $kel->id,
            'id_rt'              => $rt->id,
            'id_jenis_kasus'     => $jk->id,
            'no_registrasi'      => 'D-1710260' . random_int(10, 99),
            'tanggal_lahir'      => '2013-09-15',
            'tanggal_onset'      => '2026-04-10',
            'tanggal_lapor'      => '2026-04-14',
            'tanggal_penyidikan' => '2026-04-15',
        ], $overrides));
    }

    private function render(SurveillanceCase $case): string
    {
        return View::make('admin.epidemiologi.pdf.formulir-dif1', [
            'case'    => $case->fresh(['jenisKasus', 'kecamatan', 'kelurahan', 'spesimen', 'kontakErat', 'faskesBerobat']),
            'disease' => $case->jenisKasus,
        ])->render();
    }

    /** Bagian B → "6. Tanggal Pelacakan Laporan" (dulu baca kolom hantu tanggal_penyelidikan). */
    public function test_tanggal_pelacakan_diambil_dari_tanggal_penyidikan(): void
    {
        $html = $this->render($this->makeCase());

        $this->assertStringContainsString('15-04-2026', $html);
    }

    /** Bagian A → identitas wali, tempat kerja, pekerjaan, no HP. */
    public function test_identitas_penderita_diambil_dari_bagian_a(): void
    {
        $case = $this->makeCase([
            'nama_orang_tua'       => 'Awaluddin Jamain',
            'no_hp_orang_tua'      => '081234567890',
            'no_telepon'           => '085200000001',
            'alamat_lengkap'       => 'Jalan Gendang 3 No 50',
            'tempat_kerja_sekolah' => 'SDN 001 Bontang Utara',
            'pekerjaan'            => 'Pelajar',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Awaluddin Jamain', $html);      // 2 & 15 (wali)
        $this->assertStringContainsString('081234567890', $html);          // 21 no HP wali
        $this->assertStringContainsString('085200000001', $html);          // 12 Tel/HP pasien
        $this->assertStringContainsString('Jalan Gendang 3 No 50', $html); // 8 & 16 alamat wali
        $this->assertStringContainsString('SDN 001 Bontang Utara', $html); // 14 alamat tempat kerja
        $this->assertStringContainsString('Pelajar', $html);               // 13 pekerjaan
    }

    /** Bagian D → keluhan utama dirangkai dari gejala yang dicentang. */
    public function test_keluhan_utama_dirangkai_dari_gejala_dicentang(): void
    {
        $case = $this->makeCase([
            'gejala_demam'             => true,
            'gejala_sakit_tenggorokan' => true,
            'gejala_leher_bengkak'     => true,
            'gejala_batuk'             => false,
        ]);

        $html = $this->render($case);

        $this->assertMatchesRegularExpression('/Demam[^<]*Sakit Tenggorokan[^<]*Leher Bengkak/i', $html);
    }

    /** Bagian D → checkbox gejala difteri + tanggal masing-masing. */
    public function test_gejala_difteri_tercentang_beserta_tanggalnya(): void
    {
        $case = $this->makeCase([
            'gejala_sakit_tenggorokan'  => true,
            'tanggal_sakit_tenggorokan' => '2026-04-10',
            'gejala_leher_bengkak'      => true,
            'tanggal_leher_bengkak'     => '2026-04-11',
            'gejala_pseudomembran'      => true,
            'tanggal_pseudomembran'     => '2026-04-12',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('10-04-2026', $html);
        $this->assertStringContainsString('11-04-2026', $html);
        $this->assertStringContainsString('12-04-2026', $html);
        // tiga checkbox gejala itu harus tercentang, bukan kotak kosong
        $this->assertGreaterThanOrEqual(4, substr_count($html, 'cbx-on'));
    }

    /** Bagian D2 → status gizi (klien: "minta tambahan pertanyaan jika dipilih difteri"). */
    public function test_status_gizi_dan_antropometri_tercetak(): void
    {
        $case = $this->makeCase([
            'status_gizi'  => 'kurang',
            'berat_badan'  => 32.5,
            'tinggi_badan' => 140.0,
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('32.5', $html);
        $this->assertStringContainsString('140.0', $html);
        // "b. Kurang" tercentang
        $this->assertMatchesRegularExpression('/cbx-on[^>]*>[^<]*<\/span>\s*b\. Kurang/', $html);
    }

    /** Bagian F → jenis spesimen, tanggal, dan no. kode spesimen. */
    public function test_spesimen_diambil_dari_bagian_f(): void
    {
        $case = $this->makeCase();
        $case->spesimen()->create([
            'urutan'                 => 1,
            'jenis_spesimen'         => 'Swab Tenggorokan',
            'no_kode_spesimen'       => 'SPC-2026-0042',
            'tanggal_ambil_spesimen' => '2026-04-16',
            'tanggal_kirim_sampel'   => '2026-04-17',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('16-04-2026', $html);
        $this->assertStringContainsString('17-04-2026', $html);
        $this->assertStringContainsString('SPC-2026-0042', $html);
        $this->assertMatchesRegularExpression('/cbx-on[^>]*>[^<]*<\/span>\s*a\. Tenggorokan/', $html);
    }

    /** Bagian G → penderita berobat ke mana (RS/Puskesmas/dokter), bukan tebakan status_rawat. */
    public function test_tempat_berobat_diambil_dari_bagian_g(): void
    {
        $case = $this->makeCase();
        $case->faskesBerobat()->create([
            'urutan'          => 1,
            'jenis_faskes'    => 'puskesmas',
            'nama_faskes'     => 'Puskesmas Bontang Utara 2',
            'tanggal_berobat' => '2026-04-13',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Puskesmas Bontang Utara 2', $html);
        $this->assertStringContainsString('13-04-2026', $html);
    }

    /** Bagian D3 → tracheostomi, antibiotik, ADS, obat lain. */
    public function test_riwayat_pengobatan_diambil_dari_bagian_d3(): void
    {
        $case = $this->makeCase([
            'tracheostomi'     => 'ya',
            'jenis_antibiotik' => 'Eritromisin',
            'dosis_ads'        => '40000 IU',
            'obat_lainnya'     => 'Paracetamol sirup',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Eritromisin', $html);
        $this->assertStringContainsString('40000 IU', $html);
        $this->assertStringContainsString('Paracetamol sirup', $html);
        $this->assertMatchesRegularExpression('/Tracheostomi\s*:\s*<span class="cbx cbx-on"/', $html);
    }

    /** Bagian E → riwayat bepergian terstruktur, bukan cuma teks riwayat_perjalanan. */
    public function test_riwayat_bepergian_diambil_dari_bagian_e(): void
    {
        $case = $this->makeCase([
            'riwayat_bepergian' => 'ya',
            'lokasi_bepergian'  => 'Samarinda',
            'tanggal_bepergian' => '2026-04-05',
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Samarinda', $html);
        $this->assertStringContainsString('05-04-2026', $html);
    }

    /** Bagian I → tabel kontak kasus termasuk jumlah imunisasi per kontak. */
    public function test_tabel_kontak_menampilkan_jumlah_imunisasi(): void
    {
        $case = $this->makeCase();
        $case->kontakErat()->create([
            'urutan'                          => 1,
            'nama'                            => 'Siti Aminah',
            'hubungan'                        => 'Ibu',
            'alamat'                          => 'Jalan Gendang 3 No 50',
            'jumlah_imunisasi_campak_rubella' => 3,
        ]);

        $html = $this->render($case);

        $this->assertStringContainsString('Siti Aminah', $html);
        $this->assertMatchesRegularExpression('/Siti Aminah.*?>3</s', $html);
    }
}
