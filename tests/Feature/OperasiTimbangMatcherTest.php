<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Services\OperasiTimbangMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperasiTimbangMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function buatAnak(array $override = []): Anak
    {
        return Anak::create(array_merge([
            'nik'       => (string) random_int(1000000000000000, 9999999999999999),
            'nama'      => 'ANAK UJI',
            'jk'        => 1,
            'tgl_lahir' => '2024-02-02',
            'nama_ibu'  => 'IBU UJI',
            'nama_ayah' => 'AYAH UJI',
            'no'        => 'REG-' . random_int(1, 999999),
            'status'    => 1,
        ], $override));
    }

    public function test_cocok_saat_satu_kandidat_lolos_ambang(): void
    {
        $anak = $this->buatAnak(['nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02']);

        $m = new OperasiTimbangMatcher(88);
        // Nama file beda tipis (huruf besar/kecil + spasi)
        $r = $m->match('Muhammad Abizar', '2024-02-02', 'L', 'AGIL ANASTASYA F');

        $this->assertSame('COCOK', $r['status']);
        $this->assertNotNull($r['anak']);
        $this->assertEquals($anak->id, $r['anak']->id);
    }

    public function test_tak_cocok_saat_tidak_ada_kandidat_tgl_jk(): void
    {
        $this->buatAnak(['nama' => 'MUHAMMAD ABIZAR', 'jk' => 1, 'tgl_lahir' => '2024-02-02']);

        $m = new OperasiTimbangMatcher(88);
        $r = $m->match('MUHAMMAD ABIZAR', '2020-01-01', 'L'); // tgl beda

        $this->assertSame('TAK_COCOK', $r['status']);
        $this->assertNull($r['anak']);
    }

    public function test_tak_cocok_saat_tgl_lahir_kosong(): void
    {
        $m = new OperasiTimbangMatcher(88);
        $r = $m->match('SIAPA SAJA', null, 'L');

        $this->assertSame('TAK_COCOK', $r['status']);
        $this->assertStringContainsString('Tanggal lahir', (string) $r['alasan']);
    }

    public function test_ambigu_saat_dua_kembar_nama_tgl_jk_tanpa_pembeda_ortu(): void
    {
        $this->buatAnak(['nama' => 'SITI AISYAH', 'jk' => 2, 'tgl_lahir' => '2023-05-05', 'nama_ibu' => 'MARIA', 'nama_ayah' => 'YUSUF']);
        $this->buatAnak(['nama' => 'SITI AISYAH', 'jk' => 2, 'tgl_lahir' => '2023-05-05', 'nama_ibu' => 'FATIMAH', 'nama_ayah' => 'ALI']);

        $m = new OperasiTimbangMatcher(88);
        $r = $m->match('SITI AISYAH', '2023-05-05', 'P', null); // tanpa ortu → tak bisa dibedakan

        $this->assertSame('AMBIGU', $r['status']);
        $this->assertCount(2, $r['kandidat']);
    }

    public function test_kembar_dibedakan_oleh_nama_ortu(): void
    {
        $target = $this->buatAnak(['nama' => 'SITI AISYAH', 'jk' => 2, 'tgl_lahir' => '2023-05-05', 'nama_ibu' => 'MARIA', 'nama_ayah' => 'YUSUF']);
        $this->buatAnak(['nama' => 'SITI AISYAH', 'jk' => 2, 'tgl_lahir' => '2023-05-05', 'nama_ibu' => 'FATIMAH', 'nama_ayah' => 'ALI']);

        $m = new OperasiTimbangMatcher(88);
        // Nama Ortu e-PPGBM format "AYAH / IBU"
        $r = $m->match('SITI AISYAH', '2023-05-05', 'P', 'YUSUF / MARIA');

        $this->assertSame('COCOK', $r['status']);
        $this->assertEquals($target->id, $r['anak']->id);
    }

    public function test_ortu_tertukar_ibu_ayah_tetap_cocok(): void
    {
        // File: "IBU / AYAH"; DB: nama_ibu=MARIA, nama_ayah=YUSUF (urutan tertukar)
        $target = $this->buatAnak(['nama' => 'RAKA', 'jk' => 1, 'tgl_lahir' => '2023-03-03', 'nama_ibu' => 'MARIA', 'nama_ayah' => 'YUSUF']);
        $this->buatAnak(['nama' => 'RAKA', 'jk' => 1, 'tgl_lahir' => '2023-03-03', 'nama_ibu' => 'FATIMAH', 'nama_ayah' => 'ALI']);

        $m = new OperasiTimbangMatcher(75);
        $r = $m->match('RAKA', '2023-03-03', 'L', 'MARIA / YUSUF');

        $this->assertSame('COCOK', $r['status']);
        $this->assertEquals($target->id, $r['anak']->id);
    }

    public function test_kembar_ortu_sama_dibedakan_oleh_kelurahan(): void
    {
        $kelA = \App\Models\Kelurahan::create(['id_kecamatan' => 1, 'name' => 'LOK TUAN']);
        $kelB = \App\Models\Kelurahan::create(['id_kecamatan' => 1, 'name' => 'TANJUNG LAUT']);

        // Dua anak: nama+tgl+jk+ortu sama; hanya kelurahan beda.
        $target = $this->buatAnak(['nama' => 'DINDA', 'jk' => 2, 'tgl_lahir' => '2022-07-07', 'nama_ibu' => 'SARI', 'nama_ayah' => 'ANTO', 'id_kel' => $kelA->id]);
        $this->buatAnak(['nama' => 'DINDA', 'jk' => 2, 'tgl_lahir' => '2022-07-07', 'nama_ibu' => 'SARI', 'nama_ayah' => 'ANTO', 'id_kel' => $kelB->id]);

        $m = new OperasiTimbangMatcher(75);
        $r = $m->match('DINDA', '2022-07-07', 'P', 'ANTO / SARI', 'Lok Tuan');

        $this->assertSame('COCOK', $r['status']);
        $this->assertEquals($target->id, $r['anak']->id);
    }

    public function test_nama_file_subset_dari_nama_db_cocok(): void
    {
        $target = $this->buatAnak(['nama' => 'MAUREEN BEA KHALIQA', 'jk' => 2, 'tgl_lahir' => '2022-05-08']);

        $m = new OperasiTimbangMatcher(75);
        // Nama file lebih pendek (prefix), similar_text ~73% < 75 → hanya lolos via substring.
        $r = $m->match('MAUREEN BEA', '2022-05-08', 'P');

        $this->assertSame('COCOK', $r['status']);
        $this->assertEquals($target->id, $r['anak']->id);
    }

    public function test_bayi_belum_bernama_cocok_via_nama_ibu_dari_placeholder(): void
    {
        // Nama anak di DB sudah terisi asli; file masih placeholder "BY NY <ibu>".
        $target = $this->buatAnak(['nama' => 'AISYAH PUTRI', 'jk' => 2, 'tgl_lahir' => '2026-06-26', 'nama_ibu' => 'MEIRINA DWI FUJIASTUTI', 'nama_ayah' => 'BUDI']);
        $this->buatAnak(['nama' => 'ANAK LAIN', 'jk' => 2, 'tgl_lahir' => '2026-06-26', 'nama_ibu' => 'SITI LAINNYA', 'nama_ayah' => 'JOKO']);

        $m = new OperasiTimbangMatcher(75);
        // Nama placeholder, kolom Nama Ortu kosong → ibu diambil dari sisa "BY NY ...".
        $r = $m->match('BY NY MEIRINA DWI FUJIASTUTI', '2026-06-26', 'P', null);

        $this->assertSame('COCOK', $r['status']);
        $this->assertEquals($target->id, $r['anak']->id);
    }

    public function test_bayi_belum_bernama_tanpa_ortu_cocok_tetap_tak_cocok(): void
    {
        $this->buatAnak(['nama' => 'AISYAH PUTRI', 'jk' => 2, 'tgl_lahir' => '2026-06-26', 'nama_ibu' => 'MEIRINA', 'nama_ayah' => 'BUDI']);

        $m = new OperasiTimbangMatcher(75);
        $r = $m->match('BY NY ORANG BERBEDA', '2026-06-26', 'P', null);

        $this->assertSame('TAK_COCOK', $r['status']);
    }
}
