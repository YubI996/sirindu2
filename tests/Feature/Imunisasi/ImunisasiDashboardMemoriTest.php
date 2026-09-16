<?php

namespace Tests\Feature\Imunisasi;

use App\Models\JenisVaksin;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Puskesmas;
use App\Services\ImunisasiStatusService;
use App\Support\WilkerPuskesmas;
use Database\Seeders\JenisVaksinSeeder;
use Database\Seeders\KelompokVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Insiden prod 16 Sep 2026: dasbor imunisasi mati "Allowed memory size of 134217728
 * bytes exhausted" karena tiap agregat memuat SELURUH populasi anak sebagai model
 * Eloquent (70 kolom + relasi imunisasi) ke memori, enam kali per request. Tes ini
 * mengunci bahwa memori puncak agregat tidak tumbuh sebanding jumlah anak.
 */
class ImunisasiDashboardMemoriTest extends TestCase
{
    use RefreshDatabase;

    private const JUMLAH_ANAK = 2000;

    /** Batas kenaikan memori puncak (MB) untuk memproses JUMLAH_ANAK di semua agregat dasbor. */
    private const BATAS_MB = 16;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->seed(JenisVaksinSeeder::class);
        $this->seed(KelompokVaksinSeeder::class);
    }

    public function test_agregat_dasbor_tidak_memuat_seluruh_populasi_anak_ke_memori(): void
    {
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'API-API', 'id_kecamatan' => $kec->id]);
        Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);

        $this->seedAnakBanyak(self::JUMLAH_ANAK, $kec->id, $kel->id);

        $service = app(ImunisasiStatusService::class);

        gc_collect_cycles();
        $memoriAwal = memory_get_usage();

        $coverage = $service->getIdlCoverage([], withKejar: true);
        $ibl      = $service->getIblCoverage();
        $funnel   = $service->getFunnelDosis();
        $antigen  = $service->getCakupanAntigen();
        $kohort   = $service->getKohortWilayah();
        $rincian  = $service->getRincianPuskesmas();

        $kenaikanMb = (memory_get_peak_usage() - $memoriAwal) / 1048576;

        // Pastikan populasinya benar-benar diproses, bukan dilewati.
        $this->assertSame(self::JUMLAH_ANAK, $coverage['total']);
        $this->assertSame(self::JUMLAH_ANAK, $ibl['total']);
        $this->assertSame(self::JUMLAH_ANAK, collect($funnel)->firstWhere('kode', 'HB0')['jumlah']);
        $this->assertSame(self::JUMLAH_ANAK, collect($antigen)->firstWhere('kode', 'HB0')['jumlah_sudah']);
        $this->assertSame(self::JUMLAH_ANAK, $kohort[0]['total']);
        $this->assertSame(self::JUMLAH_ANAK, $rincian[0]['sasaran']);

        $this->assertLessThan(
            self::BATAS_MB,
            $kenaikanMb,
            sprintf('Memori puncak naik %.1f MB untuk %d anak — agregat masih memuat seluruh populasi sekaligus.', $kenaikanMb, self::JUMLAH_ANAK)
        );
    }

    /** Anak usia 30 bulan (masuk kohort IDL ≥12 & IBL ≥24) dengan 3 vaksin 'sudah', lewat insert massal agar cepat. */
    private function seedAnakBanyak(int $jumlah, int $idKec, int $idKel): void
    {
        $tglLahir = now()->subMonths(30)->toDateString();
        $now = now()->toDateTimeString();
        $vaksinIds = JenisVaksin::whereIn('kode', ['HB0', 'DPT-HB-HIB1', 'DPT-HB-HIB3'])->pluck('id')->all();
        $this->assertCount(3, $vaksinIds);

        foreach (array_chunk(range(1, $jumlah), 500) as $potongan) {
            $anak = [];
            foreach ($potongan as $i) {
                $anak[] = [
                    'nama' => 'Anak Massal ' . $i,
                    'nik' => '3' . str_pad((string) $i, 15, '0', STR_PAD_LEFT),
                    'jk' => 1,
                    'tempat_lahir' => 'Bontang',
                    'tgl_lahir' => $tglLahir,
                    'status' => 1,
                    'id_kec' => $idKec,
                    'id_kel' => $idKel,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('anak')->insert($anak);
        }

        $imunisasi = [];
        foreach (DB::table('anak')->where('nama', 'like', 'Anak Massal %')->pluck('id') as $idAnak) {
            foreach ($vaksinIds as $idVaksin) {
                $imunisasi[] = [
                    'id_anak' => $idAnak,
                    'id_jenis_vaksin' => $idVaksin,
                    'dosis' => 1,
                    'status' => 'sudah',
                    'tanggal_pemberian' => $tglLahir,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($imunisasi, 1000) as $potongan) {
            DB::table('imunisasi')->insert($potongan);
        }
    }
}
