<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakMergeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MergeSkemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_log_dan_kolom_sumber_gabungan_ada(): void
    {
        $this->assertTrue(Schema::hasColumns('anak_merge_log', ['id_dipertahankan', 'id_dihapus', 'id_tautan', 'snapshot', 'oleh', 'dibatalkan_oleh', 'dibatalkan_at']));
        $this->assertTrue(Schema::hasColumn('anak', 'sumber_gabungan'));
    }

    public function test_log_menyimpan_snapshot_array_dan_scope_aktif(): void
    {
        $u = User::factory()->create(['type' => 0]);
        $a = Anak::create(['nama' => 'A', 'nik' => '3201000000016001', 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'operasi_timbang', 'sumber_gabungan' => ['operasi_timbang', 'capil']]);

        $log = AnakMergeLog::create(['id_dipertahankan' => $a->id, 'id_dihapus' => 999, 'snapshot' => ['anak' => ['nik' => 'x']], 'oleh' => $u->id]);

        $this->assertSame(['nik' => 'x'], $log->fresh()->snapshot['anak']);
        $this->assertSame(['operasi_timbang', 'capil'], $a->fresh()->sumber_gabungan);
        $this->assertSame(1, AnakMergeLog::aktif()->count());
        $log->update(['dibatalkan_at' => now(), 'dibatalkan_oleh' => $u->id]);
        $this->assertSame(0, AnakMergeLog::aktif()->count());
        $this->assertSame($a->id, $log->dipertahankan->id);
    }
}
