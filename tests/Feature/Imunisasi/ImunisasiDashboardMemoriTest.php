<?php

namespace Tests\Feature\Imunisasi;

use App\Models\JenisVaksin;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Puskesmas;
use App\Services\ImunisasiStatusService;
use App\Support\KohortImunisasi;
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

        $kohort = KohortImunisasi::dari((int) date('Y'));

        // 1.000 anak di SI, 1.000 di Baduta. Tanggalnya diambil dari rentang kohort
        // itu sendiri, bukan ditulis absolut, supaya tes tidak basi saat tahun berganti.
        $this->seedAnakBanyak(1000, $kec->id, $kel->id, $kohort->rentang('SI')[0], 0);
        $this->seedAnakBanyak(1000, $kec->id, $kel->id, $kohort->rentang('BADUTA')[0], 1000);
        $this->assertDatabaseCount('anak', self::JUMLAH_ANAK);
        $this->assertDatabaseCount('imunisasi', self::JUMLAH_ANAK * 3);

        $service = app(ImunisasiStatusService::class);

        gc_collect_cycles();
        $memoriAwal = memory_get_usage();

        $coverage   = $service->getIdlCoverage($kohort);
        $butuhKejar = $service->getButuhKejar();
        $ibl        = $service->getIblCoverage($kohort);
        $funnel     = $service->getFunnelDosis($kohort);
        $antigen    = $service->getCakupanAntigen($kohort);
        $kohortWil  = $service->getKohortWilayah($kohort);
        $rincian    = $service->getRincianPuskesmas($kohort);

        $kenaikanMb = (memory_get_peak_usage() - $memoriAwal) / 1048576;

        // Pastikan populasinya benar-benar diproses, bukan dilewati.
        // Verifikasi bahwa agregat menghitung anak nyata, bukan zero-scan di luar kohort.
        $this->assertGreaterThan(0, $coverage['total'], 'IDL harus menghitung anak dalam SI');
        $this->assertGreaterThan(0, $ibl['total'], 'IBL harus menghitung anak dalam BADUTA');
        $this->assertGreaterThan(0, collect($funnel)->firstWhere('kode', 'HB0')['jumlah'], 'Funnel HB0 harus menghitung anak');
        $this->assertGreaterThan(0, collect($antigen)->firstWhere('kode', 'HB0')['jumlah_sudah'], 'Antigen HB0 sudah harus non-zero');
        $this->assertGreaterThan(0, $kohortWil[0]['total'], 'Kohort Wilayah harus menghitung anak');
        $this->assertGreaterThan(0, $rincian[0]['sasaran'], 'Rincian Puskesmas harus menghitung anak');

        $this->assertLessThan(
            self::BATAS_MB,
            $kenaikanMb,
            sprintf('Memori puncak naik %.1f MB untuk %d anak — agregat masih memuat seluruh populasi sekaligus.', $kenaikanMb, self::JUMLAH_ANAK)
        );
    }

    /** Anak dengan 3 vaksin 'sudah' (HB0, DPT-HB-HIB1, DPT-HB-HIB3), lewat insert massal agar cepat. */
    private function seedAnakBanyak(int $jumlah, int $idKec, int $idKel, string $tglLahir, int $offsetNik = 0): void
    {
        $now = now()->toDateTimeString();
        $vaksinIds = JenisVaksin::whereIn('kode', ['HB0', 'DPT-HB-HIB1', 'DPT-HB-HIB3'])->pluck('id')->all();
        $this->assertCount(3, $vaksinIds);

        foreach (array_chunk(range(1, $jumlah), 500) as $potongan) {
            $anak = [];
            foreach ($potongan as $i) {
                $anak[] = [
                    'nama' => 'Anak Massal ' . $i,
                    'nik' => '3' . str_pad((string) ($i + $offsetNik), 15, '0', STR_PAD_LEFT),
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
        $nikAwal = '3' . str_pad((string) ($offsetNik + 1), 15, '0', STR_PAD_LEFT);
        $nikAkhir = '3' . str_pad((string) ($offsetNik + $jumlah), 15, '0', STR_PAD_LEFT);
        foreach (DB::table('anak')->whereBetween('nik', [$nikAwal, $nikAkhir])->pluck('id') as $idAnak) {
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
