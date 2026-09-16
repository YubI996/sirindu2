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
        file_put_contents($path, "\xEF\xBB\xBFNama_PJ;NIP_PJ\nKader Sari;198501012010012001\n;198501012010012001\nBidan Rina;198602022011012002\n");

        $r = (new PjImport())->baca($path);

        $this->assertCount(2, $r['baris']);
        $this->assertSame(['nama_pj' => 'Kader Sari', 'nip_pj' => '198501012010012001', 'baris' => 2], $r['baris'][0]);
        $this->assertSame('Bidan Rina', $r['baris'][1]['nama_pj']);
        $this->assertStringContainsString('Baris 3', $r['gagal'][0]);
    }

    public function test_parser_menolak_header_tanpa_kolom_wajib(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pj');
        file_put_contents($path, "nama_pj\nKader\n");

        $this->expectException(\RuntimeException::class);
        (new PjImport())->baca($path);
    }

    public function test_upload_membuat_log_jenis_pj_dan_mengantrekan_job_dengan_timpa(): void
    {
        Queue::fake();
        Storage::fake('local');
        $super = User::factory()->create(['type' => 0]);
        $file = UploadedFile::fake()->createWithContent('pj.csv', "nip_pj,nama_pj\n198501012010012001,Kader Sari\n");

        $this->actingAs($super)->post(route('admin.importCsv.pj'), ['file_pj' => $file, 'timpa' => 1])
            ->assertRedirect()->assertSessionHas('import_queued');

        $log = ImportLog::sole();
        $this->assertSame('pj', $log->type);
        Queue::assertPushed(ImportPjJob::class, fn ($job) => $job->timpa === true);
    }

    public function test_job_mengalokasikan_dan_menulis_ringkasan_ke_log(): void
    {
        Storage::fake('local');
        $kel = Kelurahan::create(['name' => 'Bontang Lestari', 'id_kecamatan' => 3]);
        $a = Anak::create(['nama' => 'Stunting', 'nik' => '3201000000050001', 'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $kel->id]);
        DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => now()->subDays(5)->toDateString(), 'bln' => 24, 'posisi' => 'berdiri', 'tb' => 85, 'bb' => 10,
            'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => -2.5, 'zscore_bb_pb' => 0, 'sumber' => 'operasi_timbang']);
        $super = User::factory()->create(['type' => 0]);
        Storage::disk('local')->put('imports/pj/x.csv', "nip_pj,nama_pj\n198501012010012001,Kader Sari\nNIP RUSAK,Kader X\n");
        $log = ImportLog::create(['user_id' => $super->id, 'filename' => 'x.csv', 'file_path' => 'imports/pj/x.csv', 'type' => 'pj', 'status' => 'pending']);

        (new ImportPjJob($log, false))->handle();

        $log->refresh();
        $this->assertSame('done', $log->status);
        $this->assertSame(1, (int) $log->success_count);
        $this->assertSame(1, (int) $log->failure_count);
        $this->assertSame('Kader Sari', $a->fresh()->pj_nama);
        $this->assertSame('198501012010012001', $a->fresh()->pj_nip);
        $this->assertStringContainsString('1 PJ, 1 anak sasaran di Kel. Bontang Lestari, 1 dialokasikan', implode("\n", $log->failures));
    }

    public function test_halaman_import_punya_tab_pj_dan_template(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super)->get(route('admin.importCsv.index'))->assertOk()
            ->assertSee('id="tab-pj"', false)->assertSee('name="file_pj"', false)->assertSee('name="timpa"', false)
            ->assertSee('hanya anak di Kelurahan <strong>Bontang Lestari</strong>', false); // petugas tahu kelurahan lain tak kebagian
        $template = $this->actingAs($super)->get(route('admin.importCsv.template', 'pj'))->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $path = $template->baseResponse->getFile()->getPathname();
        $header = strtok(file_get_contents($path), "\r\n");
        $this->assertSame('nip_pj,nama_pj', $header);
        $parsed = (new PjImport())->baca($path);
        $this->assertCount(2, $parsed['baris']);
        $this->assertSame([], $parsed['gagal']);
    }

    public function test_nip_utuh_disimpan_sebagai_teks_dan_nip_rusak_dilaporkan(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pj');
        file_put_contents($path, "nip_pj,nama_pj\n001234567890123456,Sari\n1.98501012010012E+17,Rina\n,Amir\n");
        try {
            $hasil = (new PjImport())->baca($path);
            $this->assertCount(1, $hasil['baris']);
            $this->assertSame('001234567890123456', $hasil['baris'][0]['nip_pj']);
            $this->assertCount(2, $hasil['gagal']);
            $this->assertStringContainsString('Baris 3', $hasil['gagal'][0]);
            $this->assertStringContainsString('Baris 4', $hasil['gagal'][1]);
        } finally {
            unlink($path);
        }
    }

    public function test_pesan_error_hanya_menyebut_kolom_pj_yang_bermasalah(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pj');
        file_put_contents($path, "nip_pj,nama_pj\nrusak,Sari\n198501012010012001,\n198501012010012001,".str_repeat('A', 101)."\n,Amir\nrusak,\n");
        try {
            $hasil = (new PjImport())->baca($path);
            $this->assertSame([], $hasil['baris']);
            $this->assertCount(5, $hasil['gagal']);
            $this->assertSame('Baris 2: Kolom nip_pj harus 18 digit utuh. Simpan kolom NIP sebagai teks.', $hasil['gagal'][0]);
            $this->assertSame('Baris 3: Kolom nama_pj wajib diisi.', $hasil['gagal'][1]);
            $this->assertSame('Baris 4: Kolom nama_pj maksimal 100 karakter.', $hasil['gagal'][2]);
            $this->assertSame('Baris 5: Kolom nip_pj wajib diisi.', $hasil['gagal'][3]);
            $this->assertSame('Baris 6: Kolom nip_pj harus 18 digit utuh. Simpan kolom NIP sebagai teks. Kolom nama_pj wajib diisi.', $hasil['gagal'][4]);
        } finally {
            unlink($path);
        }
    }
}
