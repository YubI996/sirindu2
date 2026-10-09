<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakTautan;
use App\Services\TautanIdentitasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GabungGandaDryRunTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak', 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'manual',
            'no_kk' => '6474010101010001', 'nama_ibu' => 'Siti Aminah', 'nama_ayah' => 'Budi Santoso',
        ], $o));
    }

    /** Jalankan pindai + command, kembalikan baris CSV terindeks "idA-idB". */
    private function jalankan(): array
    {
        app(TautanIdentitasService::class)->pindai();
        $this->artisan('anak:gabung-ganda')->assertExitCode(0);

        $berkas = collect(Storage::disk('local')->files('gabung-ganda'))->sort()->last();
        $this->assertNotNull($berkas, 'CSV dry-run ditulis');
        $isi   = ltrim(Storage::disk('local')->get($berkas), "\xEF\xBB\xBF");
        $baris = array_map(fn ($l) => str_getcsv($l, ',', '"', '\\'), explode("\n", trim($isi)));
        $kepala = array_shift($baris);

        $hasil = [];
        foreach ($baris as $b) {
            $r = array_combine($kepala, $b);
            $hasil[$r['id_a'] . '-' . $r['id_b']] = $r;
        }

        return $hasil;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::disk('local')->deleteDirectory('gabung-ganda');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('gabung-ganda');
        parent::tearDown();
    }

    public function test_ganda_persis_dengan_kk_sama_masuk_aman(): void
    {
        $a = $this->anak('6474010101249001', ['nama' => 'Raka Wijaya', 'sumber' => 'operasi_timbang']);
        $b = $this->anak('6474010101240003', ['nama' => 'RAKA  WIJAYA', 'sumber' => 'import_anak']);

        $r = $this->jalankan()["{$a->id}-{$b->id}"];

        $this->assertSame('AMAN', $r['kelas']);
        $this->assertSame((string) $a->id, $r['id_dipertahankan'], 'baris OT selalu dipertahankan');
        $this->assertSame((string) $b->id, $r['id_dilebur']);
    }

    public function test_cli_menampilkan_siapa_yang_akan_digabung(): void
    {
        $a = $this->anak('6474010101249101', ['nama' => 'Nayla Putri', 'sumber' => 'operasi_timbang']);
        $b = $this->anak('6474010101240101', ['nama' => 'NAYLA PUTRI', 'sumber' => 'capil']);
        $this->anak('6474010101240102', ['nama' => 'Omar', 'no_kk' => null]);
        $this->anak('6474010101249102', ['nama' => 'Omar']);
        app(TautanIdentitasService::class)->pindai();

        $this->artisan('anak:gabung-ganda')
            ->expectsOutputToContain('Akan digabung (AMAN)')
            ->expectsTable(
                ['No', 'Dipertahankan', 'Dilebur', 'Nama', 'Tgl lahir', 'Kelurahan', 'Posyandu', 'NIK akhir', 'Data dipindah'],
                // OT dipertahankan, NIK diambil dari baris Capil; Omar (TINJAU) tidak ikut.
                [[1, "#{$a->id} (operasi_timbang)", "#{$b->id} (capil)", 'Nayla Putri', '2024-01-01', '', '', '6474010101240101', '0 kunj / 0 imun']],
            )
            ->assertExitCode(0);
    }

    public function test_tanpa_kk_sama_tidak_aman(): void
    {
        $a = $this->anak('6474010101240011', ['nama' => 'Laras']);
        $b = $this->anak('6474010101249011', ['nama' => 'Laras', 'no_kk' => null]);

        $r = $this->jalankan()["{$a->id}-{$b->id}"];

        $this->assertSame('TINJAU', $r['kelas']);
        $this->assertStringContainsString('KK', $r['alasan']);
    }

    public function test_nama_tidak_persis_sama_tidak_aman(): void
    {
        $a = $this->anak('6474010101240021', ['nama' => 'Muhammad Fajar']);
        $b = $this->anak('6474010101249021', ['nama' => 'Muhamad Fajar']);

        $r = $this->jalankan()["{$a->id}-{$b->id}"];

        $this->assertSame('TINJAU', $r['kelas']);
        $this->assertStringContainsString('nama', $r['alasan']);
    }

    public function test_ibu_jelas_berbeda_tidak_aman(): void
    {
        $a = $this->anak('6474010101240031', ['nama' => 'Dimas', 'nama_ayah' => 'Budi']);
        $b = $this->anak('6474010101249031', ['nama' => 'Dimas', 'nama_ibu' => 'Rahmawati', 'nama_ayah' => 'Budi']);

        $r = $this->jalankan()["{$a->id}-{$b->id}"];

        $this->assertSame('TINJAU', $r['kelas']);
        $this->assertStringContainsString('ibu', $r['alasan']);
    }

    public function test_dua_nik_asli_berbeda_tidak_aman(): void
    {
        $a = $this->anak('6474010101240041', ['nama' => 'Alya']);
        $b = $this->anak('6474010101240099', ['nama' => 'Alya']);

        $r = $this->jalankan()["{$a->id}-{$b->id}"];

        $this->assertSame('TINJAU', $r['kelas']);
        $this->assertStringContainsString('NIK', $r['alasan']);
    }

    public function test_anak_di_lebih_dari_satu_pasangan_tidak_aman(): void
    {
        $a = $this->anak('6474010101249051', ['nama' => 'Bima']);
        $b = $this->anak('6474010101249052', ['nama' => 'Bima']);
        $c = $this->anak('6474010101249053', ['nama' => 'Bima']);

        $hasil = $this->jalankan();

        $this->assertCount(3, $hasil);
        foreach ($hasil as $r) {
            $this->assertSame('TINJAU', $r['kelas']);
            $this->assertStringContainsString('lebih dari dua', $r['alasan']);
        }
    }

    public function test_dua_baris_ot_tidak_bisa_digabung(): void
    {
        $a = $this->anak('6474010101249061', ['nama' => 'Citra', 'sumber' => 'operasi_timbang']);
        $b = $this->anak('6474010101249062', ['nama' => 'Citra', 'sumber' => 'operasi_timbang']);
        // Pasangan lain supaya pindai tak kosong dan CSV tetap ditulis.
        $this->anak('6474010101240063', ['nama' => 'Gilang']);
        $this->anak('6474010101249063', ['nama' => 'Gilang']);

        // Pindai mengecualikan OT×OT, jadi pasangan ini memang tak pernah muncul.
        $this->assertArrayNotHasKey("{$a->id}-{$b->id}", $this->jalankan());
    }

    public function test_pasangan_yang_sudah_diputus_dilewati(): void
    {
        $a = $this->anak('6474010101240071', ['nama' => 'Dewi']);
        $b = $this->anak('6474010101249071', ['nama' => 'Dewi']);
        AnakTautan::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'keputusan' => 'beda',
            'skor' => 1, 'via' => 'kk', 'status' => 'disetujui', 'diusulkan_at' => now()]);

        $this->assertArrayNotHasKey("{$a->id}-{$b->id}", $this->jalankan());
    }

    public function test_dry_run_tidak_mengubah_apa_pun(): void
    {
        $a = $this->anak('6474010101249081', ['nama' => 'Eka']);
        $b = $this->anak('6474010101240081', ['nama' => 'Eka']);

        $this->jalankan();

        $this->assertSame(2, Anak::whereIn('id', [$a->id, $b->id])->count());
        $this->assertSame(0, AnakTautan::count());
    }

    public function test_nik_ditulis_sebagai_teks_excel(): void
    {
        $a = $this->anak('6474010101240091', ['nama' => 'Fikri']);
        $b = $this->anak('6474010101249091', ['nama' => 'Fikri']);

        $r = $this->jalankan()["{$a->id}-{$b->id}"];

        $this->assertSame('="6474010101240091"', $r['nik_a']);
    }
}
