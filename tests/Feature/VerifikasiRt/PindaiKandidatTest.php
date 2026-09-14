<?php

namespace Tests\Feature\VerifikasiRt;

use App\Jobs\PindaiIdentitasJob;
use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Services\TautanIdentitasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PindaiKandidatTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Anak', 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'nama_ibu' => 'Siti Aminah', 'nama_ayah' => 'Budi Santoso',
        ], $o));
    }

    public function test_pindai_mengisi_anak_kandidat_dan_mengganti_hasil_lama(): void
    {
        $svc = app(TautanIdentitasService::class);
        $a = $this->anak('3201000000012001', ['nama' => 'Raka Wijaya', 'sumber' => 'operasi_timbang']);
        $b = $this->anak('3201000000012002', ['nama' => 'Raka Wijaya']);
        $this->anak('3201000000012003', ['nama' => 'Bunga', 'nama_ibu' => 'X', 'nama_ayah' => 'Y']);
        AnakKandidat::create(['id_anak_a' => $a->id, 'id_anak_b' => $b->id, 'skor' => 1, 'via' => 'lama',
            'child_sim' => 1, 'parent_sim' => 1, 'dipindai_at' => now()->subDay()]);

        $ringkas = $svc->pindai();

        $this->assertSame(1, $ringkas['pasangan']);
        $this->assertSame(['ortu' => 1], $ringkas['via']);
        $k = AnakKandidat::sole();
        $this->assertSame([$a->id, $b->id], [(int) $k->id_anak_a, (int) $k->id_anak_b]);
        $this->assertSame('ortu', $k->via, 'hasil lama diganti');
        $this->assertSame(1, $svc->ringkasanKandidat()['jumlah']);
    }

    public function test_command_identitas_pindai_mencetak_ringkasan(): void
    {
        $this->anak('3201000000012004', ['nama' => 'Dimas']);
        $this->anak('3201000000012005', ['nama' => 'Dimas']);

        $this->artisan('identitas:pindai')
            ->expectsOutputToContain('1 pasangan')
            ->assertExitCode(0);
    }

    public function test_job_pindai_menjalankan_service(): void
    {
        $this->anak('3201000000012006', ['nama' => 'Laras']);
        $this->anak('3201000000012007', ['nama' => 'Laras']);

        (new PindaiIdentitasJob())->handle(app(TautanIdentitasService::class));

        $this->assertSame(1, AnakKandidat::count());
    }

    public function test_import_job_memanggil_pindai_setelah_sukses(): void
    {
        $src = file_get_contents(app_path('Jobs/ImportCapilJob.php'));
        $this->assertStringContainsString('TautanIdentitasService::class)->pindai()', $src);
        $srcAnak = file_get_contents(app_path('Jobs/ImportAnakJob.php'));
        $this->assertStringContainsString('TautanIdentitasService::class)->pindai()', $srcAnak);
    }
}
