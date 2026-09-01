<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Migration 2026_09_01_*_add_sumber_to_{anak,data_anak}_table backfill data
 * yang SUDAH ADA di server sebelum kolom sumber dibuat — data itu bisa masuk
 * lewat cara apa pun (artisan import, atau dump SQL langsung di-restore),
 * jadi backfill-nya SENGAJA tidak bergantung pada created_at (bisa NULL/tak
 * pasti bila insert bukan lewat Eloquent). Test ini mensimulasikan itu:
 * insert baris mentah via query builder (bukan model), created_at NULL,
 * lalu jalankan up() migration langsung dan pastikan hasilnya benar.
 */
class SumberBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrationAnak()
    {
        return require database_path('migrations/2026_09_01_000001_add_sumber_to_anak_table.php');
    }

    private function migrationDataAnak()
    {
        return require database_path('migrations/2026_09_01_000002_add_sumber_to_data_anak_table.php');
    }

    public function test_backfill_anak_tidak_bergantung_created_at(): void
    {
        $mAnak = $this->migrationAnak();
        $mAnak->down(); // drop kolom sumber dulu, simulasikan sebelum migration jalan

        // Insert mentah lewat query builder (bukan Eloquent) — created_at NULL,
        // meniru dump SQL yang tak melewati Eloquent::create().
        DB::table('anak')->insert([
            'nik' => '3201000000009901', 'nama' => 'Anak Raw Insert', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
            'created_at' => null, 'updated_at' => null,
        ]);

        $mAnak->up();

        $anak = DB::table('anak')->where('nik', '3201000000009901')->first();
        $this->assertSame('operasi_timbang', $anak->sumber);
    }

    public function test_backfill_data_anak_pisahkan_placeholder_imunisasi_dari_pengukuran_ot(): void
    {
        $mAnak = $this->migrationAnak();
        $mAnak->down();
        $mAnak->up();

        $mData = $this->migrationDataAnak();
        $mData->down();

        $anakId = DB::table('anak')->insertGetId([
            'nik' => '3201000000009902', 'nama' => 'Anak Raw Insert 2', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1,
            'sumber' => 'operasi_timbang', 'created_at' => null, 'updated_at' => null,
        ]);

        // Baris pengukuran OT sungguhan — bb/tb nyata, created_at NULL (raw insert).
        DB::table('data_anak')->insert([
            'id_anak' => $anakId, 'tgl_kunjungan' => '2026-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 14, 'lk' => 0,
            'id_user' => 1, 'created_at' => null, 'updated_at' => null,
        ]);

        // Baris placeholder imunisasi — signature bb=0/tb=0/lla=0/lk=0 + alasan terisi.
        DB::table('data_anak')->insert([
            'id_anak' => $anakId, 'tgl_kunjungan' => '2026-07-30', 'bln' => 25,
            'posisi' => 'berdiri', 'tb' => 0, 'bb' => 0, 'lla' => 0, 'lk' => 0,
            'alasan_tidak_imunisasi' => 'Sakit', 'id_user' => 1,
            'created_at' => null, 'updated_at' => null,
        ]);

        $mData->up();

        $ot = DB::table('data_anak')->where('id_anak', $anakId)->where('bb', 12)->first();
        $imunisasi = DB::table('data_anak')->where('id_anak', $anakId)->where('bb', 0)->first();

        $this->assertSame('operasi_timbang', $ot->sumber);
        $this->assertSame('imunisasi', $imunisasi->sumber);
    }
}
