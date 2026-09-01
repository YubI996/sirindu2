<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\Kelurahan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RekonsiliasiKelurahanOtTest extends TestCase
{
    use RefreshDatabase;

    private function csv(array $rows): string
    {
        $lines = ["Nama,Desa/Kel"];
        foreach ($rows as [$nama, $kel]) {
            $lines[] = "{$nama},{$kel}";
        }
        $path = tempnam(sys_get_temp_dir(), 'kel_') . '.csv';
        file_put_contents($path, implode("\n", $lines));
        return $path;
    }

    public function test_dry_run_tidak_menulis_apapun(): void
    {
        Storage::fake('local');
        $lestari = Kelurahan::factory()->create(['name' => 'Bontang Lestari']);
        $loktuan = Kelurahan::factory()->create(['name' => 'Loktuan']);
        $anak = Anak::create(['nama' => 'ALTI MIDI', 'nik' => '1111111111111111', 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $loktuan->id]);

        $csv = $this->csv([['ALTI MIDI', 'BONTANG LESTARI']]);

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $csv])
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        $this->assertSame($loktuan->id, $anak->fresh()->id_kel);
    }

    public function test_commit_mengoreksi_anak_kelurahan_berbeda(): void
    {
        Storage::fake('local');
        $lestari = Kelurahan::factory()->create(['name' => 'Bontang Lestari']);
        $loktuan = Kelurahan::factory()->create(['name' => 'Loktuan']);
        $anak = Anak::create(['nama' => 'ALTI MIDI', 'nik' => '1111111111111111', 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $loktuan->id]);

        $csv = $this->csv([['ALTI MIDI', 'BONTANG LESTARI']]);

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $csv, '--commit' => true])
            ->assertExitCode(0);

        $this->assertSame($lestari->id, $anak->fresh()->id_kel);
    }

    public function test_anak_kelurahan_sudah_benar_tidak_disentuh(): void
    {
        Storage::fake('local');
        $lestari = Kelurahan::factory()->create(['name' => 'Bontang Lestari']);
        $anak = Anak::create(['nama' => 'ALTI MIDI', 'nik' => '1111111111111111', 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $lestari->id]);

        $csv = $this->csv([['ALTI MIDI', 'BONTANG LESTARI']]);

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $csv, '--commit' => true])
            ->expectsOutputToContain('SUDAH BENAR      : 1')
            ->assertExitCode(0);

        $this->assertSame($lestari->id, $anak->fresh()->id_kel);
    }

    public function test_nama_dobel_di_anak_dilewati_tidak_ditebak(): void
    {
        Storage::fake('local');
        $lestari = Kelurahan::factory()->create(['name' => 'Bontang Lestari']);
        $loktuan = Kelurahan::factory()->create(['name' => 'Loktuan']);
        $a1 = Anak::create(['nama' => 'MUHAMMAD ABIZAR', 'nik' => '1111111111111111', 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $loktuan->id]);
        $a2 = Anak::create(['nama' => 'MUHAMMAD ABIZAR', 'nik' => '2222222222222222', 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $loktuan->id]);

        $csv = $this->csv([['MUHAMMAD ABIZAR', 'BONTANG LESTARI']]);

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $csv, '--commit' => true])
            ->expectsOutputToContain('AMBIGU (anak)    : 1')
            ->assertExitCode(0);

        // Tak satu pun disentuh — ambigu tidak pernah ditebak.
        $this->assertSame($loktuan->id, $a1->fresh()->id_kel);
        $this->assertSame($loktuan->id, $a2->fresh()->id_kel);
    }

    public function test_nama_dobel_di_csv_dengan_kelurahan_beda_dilewati(): void
    {
        Storage::fake('local');
        $lestari = Kelurahan::factory()->create(['name' => 'Bontang Lestari']);
        $loktuan = Kelurahan::factory()->create(['name' => 'Loktuan']);
        $anak = Anak::create(['nama' => 'ALTI MIDI', 'nik' => '1111111111111111', 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $loktuan->id]);

        // Nama sama, kelurahan beda di CSV -> ambigu-sheet, jangan ditebak.
        $csv = $this->csv([
            ['ALTI MIDI', 'BONTANG LESTARI'],
            ['ALTI MIDI', 'LOKTUAN'],
        ]);

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $csv, '--commit' => true])
            ->expectsOutputToContain('AMBIGU (sheet)   : 1')
            ->assertExitCode(0);

        $this->assertSame($loktuan->id, $anak->fresh()->id_kel);
    }

    public function test_nama_csv_tak_ditemukan_di_anak_dilaporkan_tanpa_error(): void
    {
        Storage::fake('local');
        Kelurahan::factory()->create(['name' => 'Bontang Lestari']);

        $csv = $this->csv([['ANAK TIDAK ADA', 'BONTANG LESTARI']]);

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $csv, '--commit' => true])
            ->expectsOutputToContain('TAK DITEMUKAN    : 1')
            ->assertExitCode(0);
    }

    public function test_kelurahan_csv_tak_cocok_master_dilaporkan_tanpa_diterapkan(): void
    {
        Storage::fake('local');
        $loktuan = Kelurahan::factory()->create(['name' => 'Loktuan']);
        $anak = Anak::create(['nama' => 'ALTI MIDI', 'nik' => '1111111111111111', 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $loktuan->id]);

        $csv = $this->csv([['ALTI MIDI', 'KELURAHAN NGAWUR TIDAK ADA']]);

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $csv, '--commit' => true])
            ->expectsOutputToContain('KELURAHAN GAGAL  : 1')
            ->assertExitCode(0);

        $this->assertSame($loktuan->id, $anak->fresh()->id_kel);
    }

    public function test_anak_bukan_sumber_operasi_timbang_tidak_disentuh(): void
    {
        Storage::fake('local');
        $lestari = Kelurahan::factory()->create(['name' => 'Bontang Lestari']);
        $loktuan = Kelurahan::factory()->create(['name' => 'Loktuan']);
        $anak = Anak::create(['nama' => 'ALTI MIDI', 'nik' => '1111111111111111', 'jk' => 2, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1, 'sumber' => 'manual', 'id_kel' => $loktuan->id]);

        $csv = $this->csv([['ALTI MIDI', 'BONTANG LESTARI']]);

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $csv, '--commit' => true])
            ->expectsOutputToContain('TAK DITEMUKAN    : 1')
            ->assertExitCode(0);

        $this->assertSame($loktuan->id, $anak->fresh()->id_kel);
    }

    public function test_csv_harus_punya_kolom_nama_dan_desa_kel(): void
    {
        Storage::fake('local');
        $path = tempnam(sys_get_temp_dir(), 'kel_') . '.csv';
        file_put_contents($path, "Kolom1,Kolom2\nA,B");

        $this->artisan('wilayah:rekonsiliasi-kelurahan', ['csv' => $path])
            ->expectsOutputToContain('wajib punya kolom')
            ->assertExitCode(1);
    }
}
