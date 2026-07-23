<?php

namespace Tests\Feature\Imports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportOtFinalCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Buat fixture .xlsx mungil (header + 1 baris) di direktori temp.
     *
     * SENGAJA tidak memakai berkas asli 24 MB / 9.884 baris: mem-parsingnya di
     * setiap tes membuat suite lambat dan boros memori.
     */
    private function berkasContoh(): string
    {
        $path = sys_get_temp_dir() . '/ot_final_fixture.xlsx';

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([
            ['Nama Anak', 'Jenis Kelamin', 'Tanggal Lahir', 'NIK', 'Kecamatan', 'Kelurahan',
                'Tanggal Pengukuran', 'Berat (kg)', 'Tinggi (cm)'],
            ['FARAH NUR SEPTIANA PUTRI', 'P', '2025-09-12', '6474025209250001',
                'Bontang Barat', 'Gunung Telihan', '2026-06-11', '7.02', '68'],
        ], null, 'A1');

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($path);
        $ss->disconnectWorksheets();

        return $path;
    }

    protected function tearDown(): void
    {
        @unlink(sys_get_temp_dir() . '/ot_final_fixture.xlsx');
        parent::tearDown();
    }

    public function test_commit_tanpa_connection_ditolak(): void
    {
        $this->artisan('import:ot-final', [
            'file'     => $this->berkasContoh(),
            '--commit' => true,
        ])
            ->expectsOutputToContain('--connection wajib diisi')
            ->assertExitCode(1);
    }

    public function test_koneksi_staging_terdaftar(): void
    {
        $this->assertIsArray(config('database.connections.staging'));
        $this->assertSame('mysql', config('database.connections.staging.driver'));
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $this->artisan('import:ot-final', ['file' => $this->berkasContoh()])
            ->assertExitCode(0);

        $this->assertSame(0, \App\Models\Anak::count());
        $this->assertSame(0, \App\Models\DataAnak::count());
    }

    public function test_koneksi_tak_dikenal_ditolak(): void
    {
        $this->artisan('import:ot-final', [
            'file'         => $this->berkasContoh(),
            '--commit'     => true,
            '--connection' => 'ngawur',
        ])
            ->expectsOutputToContain('tidak terdaftar')
            ->assertExitCode(1);
    }

    /**
     * Koneksi 'mysql' pada env testing menunjuk sirindu_testing (phpunit.xml),
     * jadi commit di sini aman dan tidak menyentuh staging.
     */
    public function test_commit_menulis_dan_lolos_verifikasi(): void
    {
        $this->artisan('import:ot-final', [
            'file'         => $this->berkasContoh(),
            '--commit'     => true,
            '--connection' => 'mysql',
        ])
            ->expectsOutputToContain('Verifikasi OK')
            ->assertExitCode(0);

        $this->assertSame(1, \App\Models\Anak::count());
        $this->assertSame(1, \App\Models\DataAnak::count());
    }
}
