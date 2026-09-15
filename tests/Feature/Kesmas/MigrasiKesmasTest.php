<?php

namespace Tests\Feature\Kesmas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mengunci keputusan spec §1.3: kolom Kesmas nullable TANPA default —
 * NULL berarti "belum diisi", bukan "Ya"/"Tidak"/"Belum" hasil tebakan.
 */
class MigrasiKesmasTest extends TestCase
{
    use RefreshDatabase;

    private const ANAK = [
        'no_id_epus', 'fktp_bpjs', 'air_bersih', 'jamban_sehat', 'merokok_keluarga', 'status_tk_paud',
        'penyakit_penyerta', 'pjb', 'riwayat_kek_ibu', 'tempat_bersalin', 'jenis_persalinan',
        'skrining_shk', 'skrining_shak', 'skrining_g6pd', 'pemeriksaan_hepatitis_b', 'komplikasi_neonatal',
    ];

    private const DATA_ANAK = [
        'tgl_penanda_ckg', 'mtbm', 'mtbs', 'skrining_atresia_bilier', 'kn1', 'kn3', 'pkat', 'oralit_zinc',
        'pemeriksaan_gigi', 'rujukan', 'mt_pangan_lokal', 'catatan_pengukuran', 'pemeriksaan_lainnya',
        'pola_makan', 'pola_asuh', 'intervensi',
    ];

    public function test_kolom_kesmas_ada_nullable_dan_tanpa_default(): void
    {
        foreach (['anak' => self::ANAK, 'data_anak' => self::DATA_ANAK] as $tabel => $daftar) {
            $kolom = collect(DB::select(
                'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$tabel]
            ))->keyBy('COLUMN_NAME');

            foreach ($daftar as $nama) {
                $this->assertTrue($kolom->has($nama), "$tabel.$nama tidak ada");
                $this->assertSame('YES', $kolom[$nama]->IS_NULLABLE, "$tabel.$nama harus nullable");
                $this->assertNull($kolom[$nama]->COLUMN_DEFAULT, "$tabel.$nama tidak boleh punya DEFAULT");
            }
        }
    }

    public function test_boolean_bertipe_tinyint_dan_skrining_bertipe_enum(): void
    {
        $kolom = collect(DB::select(
            'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['anak']
        ))->keyBy('COLUMN_NAME');

        $this->assertSame('tinyint(1)', $kolom['air_bersih']->COLUMN_TYPE);
        $this->assertSame("enum('normal','tidak_normal','belum')", $kolom['skrining_shk']->COLUMN_TYPE);
        $this->assertSame("enum('reaktif','non_reaktif','belum')", $kolom['pemeriksaan_hepatitis_b']->COLUMN_TYPE);
    }
}
