<?php

namespace Tests\Feature\Kesmas;

use App\Models\SasaranKesmasLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Spec 2026-10-02 §4: dua kolom anak nullable TANPA DEFAULT + tabel audit penandaan. */
class MigrasiSasaranHbigTest extends TestCase
{
    use RefreshDatabase;

    private function kolom(string $tabel): Collection
    {
        return collect(DB::select(
            'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tabel]
        ))->keyBy('COLUMN_NAME');
    }

    public function test_kolom_anak_baru_nullable_tanpa_default(): void
    {
        $k = $this->kolom('anak');
        foreach (['tgl_hbig' => 'date', 'sasaran_balita_kesmas' => 'tinyint(1)'] as $nama => $tipe) {
            $this->assertTrue($k->has($nama), "anak.$nama tidak ada");
            $this->assertSame('YES', $k[$nama]->IS_NULLABLE, "anak.$nama harus nullable");
            $this->assertNull($k[$nama]->COLUMN_DEFAULT, "anak.$nama tidak boleh punya DEFAULT — NULL = belum diisi/ditandai");
            $this->assertSame($tipe, $k[$nama]->COLUMN_TYPE);
        }
    }

    public function test_tabel_log_penandaan(): void
    {
        $this->assertTrue(Schema::hasTable('sasaran_kesmas_log'));
        $k = $this->kolom('sasaran_kesmas_log');
        foreach (['id', 'id_anak', 'nilai_lama', 'nilai_baru', 'sumber', 'batch', 'alasan', 'id_user', 'created_at'] as $nama) {
            $this->assertTrue($k->has($nama), "sasaran_kesmas_log.$nama tidak ada");
        }
        $this->assertFalse($k->has('updated_at'), 'Log audit hanya ditambah, tidak pernah diubah');
        $this->assertSame("enum('form_tambah','form_edit','perintah','batal')", $k['sumber']->COLUMN_TYPE);
    }

    public function test_model_log_mengisi_created_at_tanpa_updated_at(): void
    {
        $log = SasaranKesmasLog::create(['id_anak' => 1, 'nilai_lama' => null, 'nilai_baru' => 1, 'sumber' => 'form_tambah']);

        $this->assertNotNull($log->fresh()->created_at);
        $this->assertNull($log->fresh()->nilai_lama);
    }
}
