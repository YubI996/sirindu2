<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman detail anak (spec §4): kartu Kesmas & Riwayat Kelahiran, kolom Layanan Kesmas
 * per kunjungan. NULL tampil "—" (bukan "Tidak"); kartu kosong bilang "Belum diisi".
 */
class DetailAnakKesmasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['type' => 1]);
    }

    private function anak(array $extra = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak Detail', 'nik' => '6474010101250009', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2025-01-10', 'status' => 1, 'sumber' => 'manual', 'no' => '1',
        ], $extra));
    }

    private function kunjungan(Anak $anak, string $tgl, int $bln, array $extra = []): DataAnak
    {
        return DataAnak::create(array_merge([
            'id_anak' => $anak->id, 'tgl_kunjungan' => $tgl, 'bln' => $bln, 'posisi' => 'L',
            'tb' => 55, 'bb' => 4.5, 'lla' => 11, 'lk' => 38, 'id_user' => $this->admin->id,
        ], $extra));
    }

    private function render(Anak $anak): string
    {
        return $this->actingAs($this->admin)->get(route('admin.showAnak', $anak->hashid))->assertOk()->getContent();
    }

    /** Kurung assertion pada satu <article> agar tidak cocok ke kartu lain. */
    private function kartu(string $html, string $judulId): string
    {
        $re = '/<article[^>]*>(?:(?!<\/article>).)*id="' . $judulId . '".*?<\/article>/s';
        $this->assertMatchesRegularExpression($re, $html, "Kartu $judulId tidak ditemukan");
        preg_match($re, $html, $m);

        return $m[0];
    }

    /** Kurung assertion pada satu <tr> (pola FormulirFp1RendersTest::baris). */
    private function baris(string $html, string $needle): string
    {
        $re = '/<tr>(?:(?!<\/tr>).)*' . preg_quote($needle, '/') . '.*?<\/tr>/s';
        $this->assertMatchesRegularExpression($re, $html, "Baris yang memuat '$needle' tidak ditemukan");
        preg_match($re, $html, $m);

        return $m[0];
    }

    public function test_kartu_kesmas_dan_riwayat_lahir_menampilkan_nilai(): void
    {
        $anak = $this->anak([
            'no_id_epus' => 'EP-123', 'air_bersih' => 1, 'jamban_sehat' => 0,
            'bbl' => 3.1, 'penolong_lahir' => 'Bidan', 'skrining_shk' => 'tidak_normal',
            'pemeriksaan_hepatitis_b' => 'non_reaktif',
        ]);

        $html = $this->render($anak);

        $kesmas = $this->kartu($html, 'kesmas-info-title');
        $this->assertStringContainsString('EP-123', $kesmas);
        $this->assertMatchesRegularExpression('/Air bersih<\/dt>\s*<dd[^>]*>\s*Ya\s*</', $kesmas);
        $this->assertMatchesRegularExpression('/Jamban sehat<\/dt>\s*<dd[^>]*>\s*Tidak\s*</', $kesmas);
        $this->assertMatchesRegularExpression('/Perokok serumah<\/dt>\s*<dd[^>]*>\s*—\s*</', $kesmas);  // NULL → —, bukan Tidak

        $lahir = $this->kartu($html, 'lahir-info-title');
        $this->assertStringContainsString('Bidan', $lahir);
        $this->assertMatchesRegularExpression('/SHK<\/dt>\s*<dd[^>]*>\s*<span class="badge badge-accessible-danger">Tidak normal<\/span>/', $lahir);
        $this->assertMatchesRegularExpression('/Hepatitis B<\/dt>\s*<dd[^>]*>\s*<span class="badge badge-accessible-success">Non reaktif<\/span>/', $lahir);
        $this->assertMatchesRegularExpression('/SHAK<\/dt>\s*<dd[^>]*>\s*—\s*</', $lahir);
    }

    public function test_kartu_kosong_menampilkan_belum_diisi(): void
    {
        $html = $this->render($this->anak());

        $this->assertStringContainsString('Belum diisi — lengkapi lewat Edit Anak', $this->kartu($html, 'kesmas-info-title'));
        $this->assertStringContainsString('Belum diisi — lengkapi lewat Edit Anak', $this->kartu($html, 'lahir-info-title'));
    }

    public function test_kolom_layanan_kesmas_per_kunjungan(): void
    {
        $anak = $this->anak();
        $this->kunjungan($anak, '2025-02-10', 1, ['kn1' => 1, 'kn3' => 0, 'mtbs' => 1, 'tgl_penanda_ckg' => '2025-02-10', 'pemeriksaan_gigi' => 'Karies']);
        $this->kunjungan($anak, '2025-03-10', 2);

        $html = $this->render($anak);

        $this->assertStringContainsString('Layanan Kesmas</th>', $html);

        $b1 = $this->baris($html, '10/02/2025');
        $this->assertStringContainsString('>KN1<', $b1);
        $this->assertStringContainsString('>MTBS<', $b1);
        $this->assertStringNotContainsString('>KN3<', $b1);
        $this->assertStringContainsString('>CKG 10/02<', $b1);
        $this->assertStringContainsString('Gigi: Karies', $b1);

        $b2 = $this->baris($html, '10/03/2025');
        $this->assertStringNotContainsString('>KN1<', $b2);
        $this->assertMatchesRegularExpression('/<td[^>]*>\s*—\s*<\/td>\s*<\/tr>/s', $b2);
    }
}
