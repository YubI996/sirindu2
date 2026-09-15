<?php

namespace Tests\Feature\Pj;

use App\Imports\PjImport;
use App\Jobs\ImportPjJob;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\ImportLog;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportPjTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_menerima_header_bebas_urutan_bom_dan_titik_koma(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pj');
        file_put_contents($path, "\xEF\xBB\xBFNama_PJ;Kelurahan;Posyandu\nKader Sari;Belimbing;\n;Belimbing;Anggrek\nBidan Rina;Kanaan;Melati II\n");

        $r = (new PjImport())->baca($path);

        $this->assertCount(2, $r['baris']);
        $this->assertSame(['kelurahan' => 'Belimbing', 'posyandu' => null, 'nama_pj' => 'Kader Sari', 'baris' => 2], $r['baris'][0]);
        $this->assertSame('Melati II', $r['baris'][1]['posyandu']);
        $this->assertStringContainsString('Baris 3', $r['gagal'][0]);
    }

    public function test_parser_menolak_header_tanpa_kolom_wajib(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pj');
        file_put_contents($path, "nama,kelurahan\nKader,Belimbing\n");

        $this->expectException(\RuntimeException::class);
        (new PjImport())->baca($path);
    }

    public function test_upload_membuat_log_jenis_pj_dan_mengantrekan_job_dengan_timpa(): void
    {
        Queue::fake();
        Storage::fake('local');
        $super = User::factory()->create(['type' => 0]);
        $file = UploadedFile::fake()->createWithContent('pj.csv', "kelurahan,posyandu,nama_pj\nBelimbing,,Kader Sari\n");

        $this->actingAs($super)->post(route('admin.importCsv.pj'), ['file_pj' => $file, 'timpa' => 1])
            ->assertRedirect()->assertSessionHas('import_queued');

        $log = ImportLog::sole();
        $this->assertSame('pj', $log->type);
        Queue::assertPushed(ImportPjJob::class, fn ($job) => $job->timpa === true);
    }

    public function test_job_mengalokasikan_dan_menulis_ringkasan_ke_log(): void
    {
        Storage::fake('local');
        $kel = Kelurahan::create(['name' => 'Belimbing', 'id_kecamatan' => 1]);
        $a = Anak::create(['nama' => 'Stunting', 'nik' => '3201000000050001', 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $kel->id]);
        DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => now()->subDays(5)->toDateString(), 'bln' => 24, 'posisi' => 'berdiri', 'tb' => 85, 'bb' => 10,
            'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => -2.5, 'zscore_bb_pb' => 0, 'sumber' => 'operasi_timbang']);
        $super = User::factory()->create(['type' => 0]);
        Storage::disk('local')->put('imports/pj/x.csv', "kelurahan,posyandu,nama_pj\nBelimbing,,Kader Sari\nAntah,,Kader X\n");
        $log = ImportLog::create(['user_id' => $super->id, 'filename' => 'x.csv', 'file_path' => 'imports/pj/x.csv', 'type' => 'pj', 'status' => 'pending']);

        (new ImportPjJob($log, false))->handle();

        $log->refresh();
        $this->assertSame('done', $log->status);
        $this->assertSame(1, (int) $log->success_count);
        $this->assertSame(1, (int) $log->failure_count);
        $this->assertSame('Kader Sari', $a->fresh()->pj_nama);
        $this->assertStringContainsString('Belimbing', implode("\n", $log->failures));
    }

    public function test_halaman_import_punya_tab_pj_dan_template(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.importCsv.index'))->assertOk()
            ->assertSee('id="tab-pj"', false)->assertSee('name="file_pj"', false)->assertSee('name="timpa"', false);
        $this->actingAs($super)->get(route('admin.importCsv.template', 'pj'))->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }
}
