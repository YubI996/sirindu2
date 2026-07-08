<?php

namespace Tests\Feature\PrioritasGizi;

use App\Jobs\ImportPengukuranJob;
use App\Models\Anak;
use App\Models\ImportLog;
use App\Models\User;
use App\Services\PrioritasGiziService;
use App\Services\StatusGiziService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Regresi untuk Issue 1 (whole-branch review): job import massal (ImportPengukuranJob,
 * dkk) sekarang wajib membisukan PrioritasGiziService::$muted selama Excel::import lalu
 * memulihkannya + refreshAll() sekali di akhir — bukan refresh per-baris via observer.
 */
class ImportMutesPrioritasRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Refs identik dengan PrioritasGiziRefreshTest agar bb=12/tb=90/bln=24 -> gizi_buruk (P1).
        StatusGiziService::useRefs([
            '1_1_24_2' => (object) ['m3sd' => 12, 'm2sd' => 13, '1sd' => 17, '2sd' => 18, '3sd' => 19],
            '2_1_24_1' => (object) ['m3sd' => 9, 'm2sd' => 10, '1sd' => 15],
            '3_1_24_2' => (object) ['m3sd' => 80, 'm2sd' => 83, '3sd' => 97],
            '4_1_90_2' => (object) ['m3sd' => 15, 'm2sd' => 16, '1sd' => 20, '2sd' => 22, '3sd' => 24],
        ]);
    }

    protected function tearDown(): void
    {
        StatusGiziService::flushCache();
        parent::tearDown();
    }

    public function test_import_pengukuran_job_membisukan_refresh_lalu_menulis_snapshot_sekali(): void
    {
        $user = User::factory()->create(['type' => 1]);
        $anak = Anak::create([
            'nama' => 'Kartika Wulan', 'nik' => '3201011501200099', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
        ]);

        // Bangun file xlsx nyata (header sesuai kolom yang dibaca PengukuranImport).
        $relPath = 'imports/pengukuran/test_' . uniqid() . '.xlsx';
        $absPath = Storage::path($relPath);
        Storage::makeDirectory('imports/pengukuran');

        // Dua baris kunjungan untuk anak yang SAMA — inilah yang memicu "refresh storm"
        // per-baris jika observer tidak dibisukan (2x DataAnak::updateOrCreate = 2x
        // PrioritasGiziService::refreshAnak via observer, alih-alih 1x via refreshAll).
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([
            'nik_anak', 'nama_anak', 'tgl_lahir_anak', 'tgl_kunjungan', 'posisi', 'bb_kg', 'tb_cm', 'lla_cm', 'lk_cm',
        ], null, 'A1');
        $sheet->fromArray([
            '3201011501200099', 'Kartika Wulan', '2022-06-01', '2024-05-01', 'berdiri', '13', '87', '0', '0',
        ], null, 'A2');
        $sheet->fromArray([
            '3201011501200099', 'Kartika Wulan', '2022-06-01', '2024-06-01', 'berdiri', '12', '90', '0', '0',
        ], null, 'A3');
        (new Xlsx($ss))->save($absPath);

        $importLog = ImportLog::create([
            'user_id'   => $user->id,
            'filename'  => basename($relPath),
            'file_path' => $relPath,
            'type'      => 'pengukuran',
            'status'    => 'pending',
        ]);

        // Precondition: Anak::create() di atas sudah memicu 1 baris snapshot (prioritas
        // null, belum ada DataAnak) via AnakObserver — itu perilaku normal & tak dibisukan,
        // di luar cakupan pengukuran ini (jendela DB::listen dipasang SETELAH baris ini).
        $this->assertDatabaseCount('prioritas_gizi', 1);

        // Hitung query tulis (INSERT/UPDATE) ke prioritas_gizi selama job berjalan.
        // Sebelum fix: observer AnakObserver/DataAnakObserver tidak dibisukan selama
        // Excel::import → tiap DataAnak::updateOrCreate memicu 1 tulis ke prioritas_gizi
        // (2 baris = 2 tulis). Sesudah fix: hanya refreshAll() pasca-import yang menulis
        // (1 anak = 1 tulis), berapa pun jumlah baris kunjungannya.
        $writesToPrioritasGizi = 0;
        DB::listen(function ($query) use (&$writesToPrioritasGizi) {
            if (preg_match('/^(insert into|update)\s+[`"]?prioritas_gizi[`"]?/i', trim($query->sql))) {
                $writesToPrioritasGizi++;
            }
        });

        (new ImportPengukuranJob($importLog))->handle();

        // Flag harus terpulihkan setelah job selesai (inti Issue 1: duringMutedImport
        // wajib set+restore $muted di sekitar Excel::import).
        $this->assertFalse(PrioritasGiziService::$muted);

        $importLog->refresh();
        $this->assertSame('done', $importLog->status);
        $this->assertSame(2, $importLog->success_count);

        $this->assertDatabaseHas('data_anak', [
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bb' => 12, 'tb' => 90,
        ]);
        $this->assertDatabaseCount('data_anak', 2);

        // Snapshot ditulis TEPAT SEKALI walau ada 2 baris kunjungan untuk anak yang sama
        // — bukti observer di-mute selama import (bukan storm per-baris).
        $this->assertSame(1, $writesToPrioritasGizi, 'prioritas_gizi seharusnya hanya ditulis via refreshAll() pasca-import, bukan per-baris via observer.');

        // Snapshot mencerminkan data hasil import (kunjungan terakhir bb=12/tb=90 -> gizi buruk).
        $this->assertDatabaseCount('prioritas_gizi', 1);
        $this->assertDatabaseHas('prioritas_gizi', [
            'id_anak' => $anak->id, 'prioritas' => 1, 'gizi_buruk' => 1,
        ]);
    }

    public function test_during_muted_import_set_dan_pulihkan_flag_termasuk_saat_exception(): void
    {
        $svc = app(PrioritasGiziService::class);

        $this->assertFalse(PrioritasGiziService::$muted);

        $sawMutedInside = null;
        $svc->duringMutedImport(function () use (&$sawMutedInside) {
            $sawMutedInside = PrioritasGiziService::$muted;
        });

        $this->assertTrue($sawMutedInside);
        $this->assertFalse(PrioritasGiziService::$muted);

        try {
            $svc->duringMutedImport(function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('Exception seharusnya diteruskan.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertFalse(PrioritasGiziService::$muted);
    }
}
