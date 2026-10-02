<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Puskesmas;
use App\Models\SasaranKesmasLog;
use App\Support\WilkerPuskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penandaan massal Sasaran Balita Kesmas (spec 2026-10-02 §5.3): default dry-run, hanya NULL → 1,
 * nilai 0 tak pernah ditimpa, tanpa updated_at & tanpa event model.
 */
class TandaiSasaranKesmasCommandTest extends TestCase
{
    use RefreshDatabase;

    private Kelurahan $tanjungLaut; // catchment Puskesmas Bontang Selatan 1 (WilkerPuskesmas)
    private Kelurahan $berbas;      // catchment Bontang Selatan 2
    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        WilkerPuskesmas::flushCache();
        $kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->tanjungLaut = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $kec->id]);
        $this->berbas = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $kec->id]);
    }

    private function anak(Kelurahan $kel, array $extra = []): Anak
    {
        $this->n++;

        return Anak::create(array_merge([
            'nama' => 'Anak Tanda ' . $this->n, 'nik' => str_pad((string) $this->n, 16, '8', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-03-15', 'status' => 1, 'no' => '1', 'sumber' => 'manual',
            'id_kec' => $kel->id_kecamatan, 'id_kel' => $kel->id,
        ], $extra));
    }

    private function nilai(Anak $a): ?int
    {
        $v = DB::table('anak')->where('id', $a->id)->value('sasaran_balita_kesmas');

        return $v === null ? null : (int) $v;
    }

    public function test_tanpa_filter_wilayah_dan_tanpa_semua_ditolak(): void
    {
        $a = $this->anak($this->tanjungLaut);

        $this->artisan('kesmas:tandai-sasaran', ['--jalankan' => true, '--alasan' => 'uji'])
            ->expectsOutputToContain('--semua')
            ->assertFailed();

        $this->assertNull($this->nilai($a));
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_dry_run_hanya_merekap_tanpa_menulis(): void
    {
        $a = $this->anak($this->tanjungLaut);
        $this->anak($this->tanjungLaut, ['sasaran_balita_kesmas' => 0]);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertNull($this->nilai($a));
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_jalankan_tanpa_alasan_ditolak(): void
    {
        $a = $this->anak($this->tanjungLaut);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true])
            ->expectsOutputToContain('--alasan')
            ->assertFailed();

        $this->assertNull($this->nilai($a));
    }

    public function test_menandai_hanya_null_di_wilayah_dan_melewati_dilepas_pindah_meninggal_tidak_aktif(): void
    {
        $baru1 = $this->anak($this->tanjungLaut);
        $baru2 = $this->anak($this->tanjungLaut);
        $berdomisili = $this->anak($this->tanjungLaut, ['verif_rt_status' => 'berdomisili']);
        $dilepas = $this->anak($this->tanjungLaut, ['sasaran_balita_kesmas' => 0]);
        $sudah = $this->anak($this->tanjungLaut, ['sasaran_balita_kesmas' => 1]);
        $pindah = $this->anak($this->tanjungLaut, ['verif_rt_status' => 'pindah']);
        $meninggal = $this->anak($this->tanjungLaut, ['verif_rt_status' => 'meninggal']);
        $tidakAktif = $this->anak($this->tanjungLaut, ['status' => 0]);
        $lain = $this->anak($this->berbas);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'Penandaan uji'])
            ->expectsOutputToContain('Kode batch')
            ->assertSuccessful();

        foreach ([$baru1, $baru2, $berdomisili] as $a) {
            $this->assertSame(1, $this->nilai($a), $a->nama);
        }
        $this->assertSame(0, $this->nilai($dilepas), 'Centang yang sengaja dilepas tidak boleh ditimpa');
        $this->assertSame(1, $this->nilai($sudah));
        foreach ([$pindah, $meninggal, $tidakAktif, $lain] as $a) {
            $this->assertNull($this->nilai($a), $a->nama);
        }

        $log = SasaranKesmasLog::orderBy('id')->get();
        $this->assertCount(3, $log);
        $this->assertCount(1, $log->pluck('batch')->unique());
        foreach ($log as $l) {
            $this->assertSame('perintah', $l->sumber);
            $this->assertNull($l->nilai_lama);
            $this->assertSame(1, (int) $l->nilai_baru);
            $this->assertSame('Penandaan uji', $l->alasan);
            $this->assertNull($l->id_user);
        }
    }

    public function test_opsi_termasuk_pindah_dan_tidak_aktif(): void
    {
        $pindah = $this->anak($this->tanjungLaut, ['verif_rt_status' => 'pindah']);
        $tidakAktif = $this->anak($this->tanjungLaut, ['status' => 0]);

        $this->artisan('kesmas:tandai-sasaran', [
            '--kelurahan' => $this->tanjungLaut->id, '--termasuk-pindah' => true, '--termasuk-tidak-aktif' => true,
            '--jalankan' => true, '--alasan' => 'uji',
        ])->assertSuccessful();

        $this->assertSame(1, $this->nilai($pindah));
        $this->assertSame(1, $this->nilai($tidakAktif));
    }

    public function test_tidak_menyentuh_updated_at_dan_tidak_memicu_refresh_prioritas_gizi(): void
    {
        $a = $this->anak($this->tanjungLaut);
        // Anak::create memicu AnakObserver → baris prioritas_gizi. Bekukan stempel waktu keduanya.
        $this->assertDatabaseHas('prioritas_gizi', ['id_anak' => $a->id]);
        DB::table('anak')->where('id', $a->id)->update(['created_at' => '2026-01-01 08:00:00', 'updated_at' => '2026-01-01 08:00:00']);
        DB::table('prioritas_gizi')->where('id_anak', $a->id)->update(['refreshed_at' => '2026-01-01 08:00:00']);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'uji'])
            ->assertSuccessful();

        $this->assertSame(1, $this->nilai($a));
        $this->assertSame('2026-01-01 08:00:00', DB::table('anak')->where('id', $a->id)->value('updated_at'),
            'updated_at berubah → CapilDedupService::sigiziUntouched() kehilangan anak ini');
        $this->assertSame('2026-01-01 08:00:00', DB::table('prioritas_gizi')->where('id_anak', $a->id)->value('refreshed_at'),
            'Refresh prioritas gizi (OT) terpicu → penulisan lewat Eloquent, bukan query builder');
    }

    public function test_filter_puskesmas_memakai_catchment_kelurahan(): void
    {
        $pkm = Puskesmas::create(['name' => 'Puskesmas Bontang Selatan I', 'id_kecamatan' => $this->tanjungLaut->id_kecamatan]);
        $dalam = $this->anak($this->tanjungLaut);
        $luar = $this->anak($this->berbas);

        $this->artisan('kesmas:tandai-sasaran', ['--puskesmas' => $pkm->id, '--jalankan' => true, '--alasan' => 'uji'])
            ->assertSuccessful();

        $this->assertSame(1, $this->nilai($dalam));
        $this->assertNull($this->nilai($luar));
    }

    public function test_rentang_tanggal_lahir_inklusif(): void
    {
        $tepatAwal = $this->anak($this->tanjungLaut, ['tgl_lahir' => '2020-01-01']);
        $tepatAkhir = $this->anak($this->tanjungLaut, ['tgl_lahir' => '2020-12-31']);
        $sebelum = $this->anak($this->tanjungLaut, ['tgl_lahir' => '2019-12-31']);

        $this->artisan('kesmas:tandai-sasaran', [
            '--semua' => true, '--lahir-sejak' => '2020-01-01', '--lahir-sampai' => '2020-12-31',
            '--jalankan' => true, '--alasan' => 'uji',
        ])->assertSuccessful();

        $this->assertSame(1, $this->nilai($tepatAwal));
        $this->assertSame(1, $this->nilai($tepatAkhir));
        $this->assertNull($this->nilai($sebelum));
    }

    public function test_input_tak_sah_ditolak_tanpa_menulis(): void
    {
        $a = $this->anak($this->tanjungLaut);

        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => 999999, '--jalankan' => true, '--alasan' => 'uji'])->assertFailed();
        $this->artisan('kesmas:tandai-sasaran', ['--semua' => true, '--lahir-sejak' => '01/01/2020', '--jalankan' => true, '--alasan' => 'uji'])->assertFailed();
        $this->artisan('kesmas:tandai-sasaran', ['--semua' => true, '--jalankan' => true, '--alasan' => str_repeat('x', 256)])->assertFailed();

        $this->assertNull($this->nilai($a));
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_idempoten(): void
    {
        $a = $this->anak($this->tanjungLaut);
        $argumen = ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'uji'];

        $this->artisan('kesmas:tandai-sasaran', $argumen)->assertSuccessful();
        $this->artisan('kesmas:tandai-sasaran', $argumen)->expectsOutputToContain('Selesai: 0 anak ditandai')->assertSuccessful();

        $this->assertSame(1, $this->nilai($a));
        $this->assertSame(1, SasaranKesmasLog::count());
    }

    public function test_batalkan_hanya_mengembalikan_yang_belum_diubah_lewat_form(): void
    {
        $tetap = $this->anak($this->tanjungLaut);
        $diubahForm = $this->anak($this->tanjungLaut);
        $this->artisan('kesmas:tandai-sasaran', ['--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'awal'])
            ->assertSuccessful();
        $batch = SasaranKesmasLog::value('batch');

        // Petugas melepas centang lewat Edit Anak sesudah penandaan massal.
        DB::table('anak')->where('id', $diubahForm->id)->update(['sasaran_balita_kesmas' => 0]);
        SasaranKesmasLog::create(['id_anak' => $diubahForm->id, 'nilai_lama' => 1, 'nilai_baru' => 0, 'sumber' => 'form_edit']);

        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => $batch])->expectsOutputToContain('DRY-RUN')->assertSuccessful();
        $this->assertSame(1, $this->nilai($tetap), 'dry-run tidak menulis');

        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => $batch, '--jalankan' => true, '--alasan' => 'salah kriteria'])
            ->expectsOutputToContain('dilewati')
            ->assertSuccessful();

        $this->assertNull($this->nilai($tetap));
        $this->assertSame(0, $this->nilai($diubahForm), 'Keputusan petugas sesudah batch tidak boleh dibatalkan');
        $this->assertSame(1, SasaranKesmasLog::where('sumber', 'batal')->where('batch', $batch)->count());

        // Dibatalkan lagi: tak ada yang tersisa untuk dikembalikan.
        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => $batch, '--jalankan' => true, '--alasan' => 'ulang'])->assertSuccessful();
        $this->assertSame(1, SasaranKesmasLog::where('sumber', 'batal')->count());
    }

    public function test_batalkan_dengan_opsi_cakupan_ditolak_tanpa_menulis(): void
    {
        $a = $this->anak($this->tanjungLaut);
        $b = $this->anak($this->berbas);
        $this->artisan('kesmas:tandai-sasaran', ['--semua' => true, '--jalankan' => true, '--alasan' => 'awal'])->assertSuccessful();
        $batch = SasaranKesmasLog::value('batch');
        $logAwal = SasaranKesmasLog::count();

        $kasus = [
            '--kelurahan' => $this->tanjungLaut->id,
            '--lahir-sejak' => '2020-01-01',
            '--semua' => true,
            '--termasuk-pindah' => true,
        ];
        foreach ($kasus as $opsi => $nilai) {
            $kode = Artisan::call('kesmas:tandai-sasaran', [
                '--batalkan' => $batch, '--jalankan' => true, '--alasan' => 'coba', $opsi => $nilai,
            ]);
            $keluaran = Artisan::output();

            $this->assertSame(1, $kode, $opsi);
            $this->assertStringContainsString($opsi, $keluaran);
            $this->assertStringContainsString('seluruh batch', $keluaran);
        }

        $this->assertSame(1, $this->nilai($a));
        $this->assertSame(1, $this->nilai($b));
        $this->assertSame($logAwal, SasaranKesmasLog::count());
    }

    public function test_batalkan_menyebut_semua_opsi_cakupan_yang_dipakai(): void
    {
        $this->anak($this->tanjungLaut);
        $this->artisan('kesmas:tandai-sasaran', ['--semua' => true, '--jalankan' => true, '--alasan' => 'awal'])->assertSuccessful();
        $batch = SasaranKesmasLog::value('batch');

        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => $batch, '--kecamatan' => 1, '--lahir-sampai' => '2024-12-31'])
            ->expectsOutputToContain('--kecamatan')
            ->assertFailed();
    }

    public function test_kode_batch_dicetak_sebelum_potongan_pertama_ditulis(): void
    {
        $this->anak($this->tanjungLaut);

        $kode = Artisan::call('kesmas:tandai-sasaran', [
            '--kelurahan' => $this->tanjungLaut->id, '--jalankan' => true, '--alasan' => 'uji',
        ]);
        $keluaran = Artisan::output();

        $this->assertSame(0, $kode);
        $batch = SasaranKesmasLog::value('batch');
        $awal = strpos($keluaran, "Kode batch: {$batch}");
        $selesai = strpos($keluaran, 'Selesai:');
        $this->assertNotFalse($awal, 'Kode batch tidak dicetak');
        $this->assertNotFalse($selesai);
        $this->assertLessThan($selesai, $awal, 'Kode batch harus muncul sebelum baris Selesai');
        $this->assertStringContainsString("Kode batch: {$batch}", substr($keluaran, $selesai), 'Baris Selesai tetap memuat kode batch');
    }

    public function test_angka_dry_run_sama_dengan_baris_yang_ditulis(): void
    {
        $this->anak($this->tanjungLaut);                                              // NULL
        $this->anak($this->tanjungLaut);                                              // NULL
        $this->anak($this->tanjungLaut, ['sasaran_balita_kesmas' => 0]);              // dilepas
        $this->anak($this->tanjungLaut, ['sasaran_balita_kesmas' => 1]);              // sudah
        $this->anak($this->tanjungLaut, ['verif_rt_status' => 'pindah']);
        $this->anak($this->tanjungLaut, ['verif_rt_status' => 'meninggal']);
        $this->anak($this->tanjungLaut, ['status' => 0]);                             // Tidak Aktif
        $this->anak($this->tanjungLaut, ['verif_rt_status' => 'berdomisili']);        // NULL, ikut
        $this->anak($this->berbas);                                                   // kelurahan lain

        $opsi = ['--kelurahan' => $this->tanjungLaut->id];

        Artisan::call('kesmas:tandai-sasaran', $opsi);
        $keluaran = Artisan::output();
        $this->assertSame(1, preg_match('/akan ditandai (\d+)/u', $keluaran, $m), $keluaran);
        $akan = (int) $m[1];
        $this->assertSame(3, $akan);
        $this->assertSame(0, SasaranKesmasLog::count(), 'dry-run tidak menulis');

        $this->assertSame(0, Artisan::call('kesmas:tandai-sasaran', $opsi + ['--jalankan' => true, '--alasan' => 'uji']));

        $this->assertSame($akan, SasaranKesmasLog::count());
        $this->assertSame($akan + 1, DB::table('anak')->where('id_kel', $this->tanjungLaut->id)->where('sasaran_balita_kesmas', 1)->count(), 'yang ditulis + 1 anak yang sudah bertanda sebelumnya');
        $this->assertSame(0, DB::table('anak')->where('id_kel', $this->berbas->id)->where('sasaran_balita_kesmas', 1)->count());
    }

    public function test_batch_tak_dikenal_ditolak(): void
    {
        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => '00000000-0000-0000-0000-000000000000'])->assertFailed();
        $this->artisan('kesmas:tandai-sasaran', ['--batalkan' => 'bukan-uuid'])->assertFailed();
    }
}
