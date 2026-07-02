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
}
