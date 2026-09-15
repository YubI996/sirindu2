<?php

namespace Tests\Feature\Pj;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\ImportLog;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportPjAlurTest extends TestCase
{
    use RefreshDatabase;

    private const QUEUE = 'uji-alur-pj';
    private const SARI = '198501012010012001';
    private const RINA = '198602022011012002';

    private function anak(string $kode, int $kel, float $bbU, float $tbU, float $bbTb): Anak
    {
        $anak = Anak::create([
            'nik' => 'UJI_PJ_ALUR_'.$kode, 'nama' => 'Anak Uji '.$kode, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1, 'sumber' => 'operasi_timbang', 'id_kel' => $kel,
        ]);
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 85, 'bb' => 10, 'lla' => 0, 'lk' => 0, 'id_user' => auth()->id(),
            'zscore_bb_u' => $bbU, 'zscore_pb_u' => $tbU, 'zscore_bb_pb' => $bbTb, 'sumber' => 'operasi_timbang',
        ]);
        return $anak;
    }

    private function unggahDanProses(string $csv, string $timpa): ImportLog
    {
        $response = $this->postJson(route('admin.importCsv.pj'), [
            'file_pj' => UploadedFile::fake()->createWithContent('uji-alur-pj.csv', $csv),
            'timpa' => $timpa,
        ])->assertOk()->assertJsonPath('ok', true);

        $log = ImportLog::findOrFail($response->json('log_id'));
        $this->assertSame('pending', $log->status);
        Storage::assertExists($log->file_path);
        $this->assertSame(1, DB::table('jobs')->where('queue', self::QUEUE)->count());

        // Jalankan job database sebenarnya, dibatasi hanya antrean uji ini.
        $this->assertSame(0, Artisan::call('queue:work', [
            'connection' => 'database', '--queue' => self::QUEUE,
            '--once' => true, '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1,
        ]));
        $this->assertSame(0, DB::table('jobs')->where('queue', self::QUEUE)->count());
        $this->assertSame('done', $log->refresh()->status, implode('; ', $log->failures ?? []));
        return $log;
    }

    public function test_unggah_antrean_alokasi_tunjuk_manual_dan_timpa_sampai_daftar_anak(): void
    {
        Storage::fake('local');
        config(['queue.default' => 'database', 'queue.connections.database.queue' => self::QUEUE]);
        $super = User::factory()->create(['type' => 0]);
        $this->actingAs($super);
        $kelA = Kelurahan::factory()->create();
        $kelB = Kelurahan::factory()->create();
        $sasaran = [
            $this->anak('A', $kelA->id, 0, -2.5, 0),
            $this->anak('B', $kelA->id, 0, 0, -2.5),
            $this->anak('C', $kelB->id, -2.5, 0, 0),
            $this->anak('D', $kelB->id, -2.5, -2.5, -2.5),
            $this->anak('E', $kelB->id, 0, -2.5, 0),
        ];
        $normal = $this->anak('NORMAL', $kelA->id, 0, 0, 0);
        $lama = $this->anak('LAMA', $kelB->id, 0, -2.5, 0);
        $lama->update(['pj_nip' => '197001012000011001', 'pj_nama' => 'PJ Lama']);

        $template = $this->get(route('admin.importCsv.template', 'pj'))->assertOk();
        $csv = file_get_contents($template->baseResponse->getFile()->getPathname());
        $log = $this->unggahDanProses($csv, '0');
        $this->assertSame(5, (int) $log->success_count);
        $this->assertSame(0, (int) $log->failure_count);
        $this->assertStringContainsString('2 PJ, 6 anak sasaran, 5 dialokasikan, 1 dilewati', implode('; ', $log->failures));
        $this->getJson(route('admin.importCsv.status', ['type' => 'pj']))->assertOk()
            ->assertJsonFragment(['id' => $log->id, 'status' => 'done', 'success_count' => 5]);

        foreach ($sasaran as $i => $anak) {
            $this->assertSame($i % 2 ? self::RINA : self::SARI, $anak->fresh()->pj_nip);
            $this->assertSame($i % 2 ? 'Rina' : 'Sari', $anak->fresh()->pj_nama);
            $this->assertSame($super->id, (int) $anak->fresh()->pj_updated_by);
        }
        $this->assertSame('PJ Lama', $lama->fresh()->pj_nama);
        $this->assertNull($normal->fresh()->pj_nip);

        // Anak D muncul di tiga daftar dengan PJ yang sama.
        foreach (['stunting', 'wasting', 'underweight'] as $kategori) {
            $rows = $this->getJson(route('admin.timbang.daftar', ['kategori' => $kategori]))->assertOk()->json('rows');
            $baris = collect($rows)->firstWhere('id', $sasaran[3]->hashid);
            $this->assertSame(self::RINA, $baris['pj_nip']);
            $this->assertSame('Rina', $baris['pj_nama']);
        }

        // Tunjuk ulang PJ anak B lalu baca ulang lewat endpoint yang dipakai UI.
        $this->putJson(route('admin.timbang.pj', $sasaran[1]), ['pj_nip' => self::SARI, 'pj_nama' => 'Sari'])
            ->assertOk()->assertJsonPath('pj_nip', self::SARI)->assertJsonPath('pj_nama', 'Sari');
        $rows = $this->getJson(route('admin.timbang.daftar', ['kategori' => 'wasting']))->assertOk()->json('rows');
        $this->assertSame(self::SARI, collect($rows)->firstWhere('id', $sasaran[1]->hashid)['pj_nip']);
        $this->assertSame(self::RINA, $sasaran[3]->fresh()->pj_nip);

        $ulang = $this->unggahDanProses($csv, '0');
        $this->assertSame(0, (int) $ulang->success_count);
        $this->assertStringContainsString('6 dilewati', implode('; ', $ulang->failures));
        $this->assertSame(self::SARI, $sasaran[1]->fresh()->pj_nip);

        $timpa = $this->unggahDanProses("nip_pj,nama_pj\n198703032012011003,Amir\n", '1');
        $this->assertSame(6, (int) $timpa->success_count);
        foreach ([...$sasaran, $lama] as $anak) {
            $this->assertSame('198703032012011003', $anak->fresh()->pj_nip);
            $this->assertSame('Amir', $anak->fresh()->pj_nama);
        }
        $this->assertNull($normal->fresh()->pj_nama);
        $this->assertNull($normal->fresh()->pj_nip);
    }
}
