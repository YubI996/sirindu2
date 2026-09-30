<?php
// tests/Feature/Kesmas/KesmasDashboardMemoriTest.php

namespace Tests\Feature\Kesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\PeriodeKesmas;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prod ±10 rb anak, memory_limit 128 MB (insiden dasbor imunisasi 16 Sep 2026).
 * Mengunci bahwa agregat dasbor Kesmas tidak memuat populasi anak ke PHP:
 * memori puncak tidak tumbuh sebanding jumlah anak, dan jumlah query tetap (bukan per anak).
 */
class KesmasDashboardMemoriTest extends TestCase
{
    use RefreshDatabase;

    private const JUMLAH_ANAK = 2000;
    private const KUNJUNGAN_PER_ANAK = 6;
    private const BATAS_MB = 16;
    private const BATAS_QUERY = 20;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
    }

    public function test_agregat_tidak_memuat_seluruh_populasi_dan_jumlah_query_tetap(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'API-API', 'id_kecamatan' => $kec->id]);
        $this->seedMassal(self::JUMLAH_ANAK, $kec->id, $kel->id);

        $svc = app(KesmasDashboardService::class);
        $p   = PeriodeKesmas::dari(2025, 'tahun');

        gc_collect_cycles();
        memory_reset_peak_usage();
        $memoriAwal = memory_get_usage();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $sasaran = $svc->sasaran($p, []);
        $spm     = $svc->spmKohort($p, []);
        $tk      = $svc->pemantauanTk($p, []);
        $sdidtk  = $svc->sdidtk($p, []);
        $ckg     = $svc->ckg($p, []);
        $layanan = $svc->layananLingkungan($p, []);

        $jumlahQuery = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();
        $kenaikanMb = (memory_get_peak_usage() - $memoriAwal) / 1048576;

        // Populasi benar-benar diproses, bukan dilewati.
        $this->assertSame(self::JUMLAH_ANAK, $sasaran['semua']);
        $this->assertSame(self::JUMLAH_ANAK, $spm['anak_balita']['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $spm['anak_balita']['sub']['timbang']['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $tk['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $sdidtk['total']['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $ckg['kelompok']['t2']['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $layanan['sasaran']);
        $this->assertSame(self::JUMLAH_ANAK, $layanan['layanan']['baris']['kn1']['terisi']);

        $this->assertLessThan(self::BATAS_MB, $kenaikanMb,
            sprintf('Memori puncak naik %.1f MB untuk %d anak — agregat memuat populasi ke PHP.', $kenaikanMb, self::JUMLAH_ANAK));
        $this->assertLessThanOrEqual(self::BATAS_QUERY, $jumlahQuery,
            sprintf('%d query untuk enam agregat — ada query per anak/per kelompok yang seharusnya digabung.', $jumlahQuery));
    }

    public function test_registri_hanya_memuat_satu_halaman(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'API-API', 'id_kecamatan' => $kec->id]);
        $this->seed(\Database\Seeders\JenisVaksinSeeder::class);
        $this->seed(\Database\Seeders\KelompokVaksinSeeder::class);
        $this->seedMassal(self::JUMLAH_ANAK, $kec->id, $kel->id);

        gc_collect_cycles();
        memory_reset_peak_usage();
        $memoriAwal = memory_get_usage();

        $r = app(KesmasDashboardService::class)->registri(PeriodeKesmas::dari(2025, 'tahun'), [], 'semua', '', 'semua', 3);

        $kenaikanMb = (memory_get_peak_usage() - $memoriAwal) / 1048576;
        $this->assertSame(self::JUMLAH_ANAK, $r['total']);
        $this->assertCount(20, $r['data']);
        $this->assertSame(41, $r['data'][0]['no']);
        $this->assertLessThan(self::BATAS_MB, $kenaikanMb, sprintf('Registri naik %.1f MB — memuat lebih dari satu halaman.', $kenaikanMb));
    }

    public function test_seluruh_halaman_tetap_di_bawah_batas_memori(): void
    {
        $this->seed(\Database\Seeders\JenisVaksinSeeder::class);
        $this->seed(\Database\Seeders\KelompokVaksinSeeder::class);
        $admin = User::factory()->create(['type' => 1]);
        $url = route('admin.kesmas.dashboard', ['tahun' => 2025]);
        // Hangatkan framework/view tanpa populasi, seperti request setelah worker siap.
        $this->actingAs($admin)->get($url)->assertOk();

        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'API-API', 'id_kecamatan' => $kec->id]);
        $this->seedMassal(self::JUMLAH_ANAK, $kec->id, $kel->id);
        gc_collect_cycles();
        memory_reset_peak_usage();
        $memoriAwal = memory_get_usage();

        $response = $this->get($url);
        $kenaikanMb = (memory_get_peak_usage() - $memoriAwal) / 1048576;

        $response->assertOk()->assertViewHas('sasaran', fn ($s) => $s['semua'] === self::JUMLAH_ANAK);
        $response->assertSee('2.000')->assertSee('data-blok="layanan"', false)->assertSee('data-blok="registri"', false);
        $this->assertLessThan(self::BATAS_MB, $kenaikanMb,
            sprintf('Seluruh halaman naik %.1f MB untuk %d anak.', $kenaikanMb, self::JUMLAH_ANAK));
    }

    /** Anak 30 bln (lahir 30 Jun 2023) dengan 6 kunjungan bulanan 2025 (kn1 terisi, ddtka di 2 kunjungan), insert massal. */
    private function seedMassal(int $jumlah, int $idKec, int $idKel): void
    {
        $now = now()->toDateTimeString();
        $idUser = User::factory()->create(['type' => 1])->id;
        foreach (array_chunk(range(1, $jumlah), 500) as $potongan) {
            $anak = [];
            foreach ($potongan as $i) {
                $anak[] = [
                    'nama' => 'Anak Massal ' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'nik' => '3' . str_pad((string) $i, 15, '0', STR_PAD_LEFT), 'jk' => 1, 'tempat_lahir' => 'Bontang',
                    'tgl_lahir' => '2023-06-30', 'status' => 1, 'no' => '1', 'sumber' => 'manual',
                    'id_kec' => $idKec, 'id_kel' => $idKel, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('anak')->insert($anak);
        }

        $ids = DB::table('anak')->where('nama', 'like', 'Anak Massal %')->pluck('id');
        foreach ($ids->chunk(250) as $potongan) {
            $rows = [];
            foreach ($potongan as $idAnak) {
                for ($b = 1; $b <= self::KUNJUNGAN_PER_ANAK; $b++) {
                    $rows[] = [
                        'id_anak' => $idAnak, 'tgl_kunjungan' => sprintf('2025-%02d-10', $b), 'bln' => 24 + $b, 'posisi' => 'B',
                        'tb' => 85, 'bb' => 11, 'lla' => 14, 'lk' => 47, 'id_user' => $idUser, 'sumber' => 'manual',
                        'ddtka' => $b <= 2 ? 'Sesuai' : null, 'kn1' => 1, 'vit_a' => $b === 2 ? 1 : 0,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            DB::table('data_anak')->insert($rows);
        }
        $this->assertDatabaseCount('anak', self::JUMLAH_ANAK);
        $this->assertDatabaseCount('data_anak', self::JUMLAH_ANAK * self::KUNJUNGAN_PER_ANAK);
    }
}
