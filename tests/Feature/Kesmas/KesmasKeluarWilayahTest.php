<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Services\ImunisasiStatusService;
use App\Services\KesmasDashboardService;
use App\Support\PeriodeKesmas;
use App\Support\WilkerPuskesmas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dasbor Kesmas tidak menghitung anak yang sudah keluar: Tidak Aktif (anak.status = 0) atau
 * verifikasi RT "pindah"/"meninggal" yang SUDAH DISETUJUI. Tanda sasarannya tidak diubah —
 * hanya dikecualikan saat menghitung, jadi bisa kembali terhitung bila statusnya berbalik.
 * Pindah ke wilker lain di Bontang tidak termasuk "keluar": dasbor mengikuti alamat anak.
 */
class KesmasKeluarWilayahTest extends TestCase
{
    use RefreshDatabase;

    private KesmasDashboardService $svc;
    private Kecamatan $kec;
    private Kelurahan $kel;
    private Kelurahan $kelLain;

    protected function setUp(): void
    {
        parent::setUp();
        ImunisasiStatusService::flushCache();
        WilkerPuskesmas::flushCache();
        $this->svc = app(KesmasDashboardService::class);
        $this->kec = Kecamatan::create(['name' => 'Bontang Selatan']);
        $this->kel = Kelurahan::create(['name' => 'Berbas Tengah', 'id_kecamatan' => $this->kec->id]);
        $this->kelLain = Kelurahan::create(['name' => 'Tanjung Laut', 'id_kecamatan' => $this->kec->id]);
    }

    private function periode(): PeriodeKesmas
    {
        return PeriodeKesmas::dari(2025, 'tahun');
    }

    /** Anak berumur 30 bulan pada 31 Des 2025 (bulan 31 hari, aman dari luapan). */
    private function anak(array $extra = []): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create(array_merge([
            'nama' => 'Anak Keluar ' . $n, 'nik' => '88' . str_pad((string) $n, 14, '0', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => Carbon::create(2025, 12, 31)->subMonthsNoOverflow(30)->toDateString(),
            'status' => 1, 'no' => '1', 'sumber' => 'manual', 'id_kec' => $this->kec->id, 'id_kel' => $this->kel->id,
            'sasaran_balita_kesmas' => 1,
        ], $extra));
    }

    private function jumlah(array $filters = []): int
    {
        return $this->svc->sasaran($this->periode(), $filters)['semua'];
    }

    public function test_tidak_aktif_tidak_dihitung(): void
    {
        $this->anak();
        $this->anak(['status' => 0]);

        $this->assertSame(1, $this->jumlah());
    }

    public function test_pindah_dan_meninggal_yang_disetujui_tidak_dihitung(): void
    {
        $this->anak();
        $this->anak(['verif_rt_status' => 'pindah', 'verif_rt_reviu' => 'disetujui']);
        $this->anak(['verif_rt_status' => 'meninggal', 'verif_rt_reviu' => 'disetujui']);

        $this->assertSame(1, $this->jumlah());
    }

    public function test_pindah_yang_baru_diusulkan_atau_ditolak_tetap_dihitung(): void
    {
        // Usulan RT belum menjadi keputusan: anak jangan hilang dari dasbor sebelum reviu menyetujui.
        $this->anak(['verif_rt_status' => 'pindah', 'verif_rt_reviu' => 'diusulkan']);
        $this->anak(['verif_rt_status' => 'pindah', 'verif_rt_reviu' => 'ditolak']);
        $this->anak(['verif_rt_status' => 'meninggal', 'verif_rt_reviu' => 'diusulkan']);

        $this->assertSame(3, $this->jumlah());
    }

    public function test_berdomisili_atau_belum_diverifikasi_tetap_dihitung(): void
    {
        $this->anak(['verif_rt_status' => 'berdomisili', 'verif_rt_reviu' => 'disetujui']);
        $this->anak(['verif_rt_status' => 'tidak_dikenal', 'verif_rt_reviu' => 'disetujui']);
        $this->anak(); // NULL: tak pernah diverifikasi — NOT(NULL …) tidak boleh membuangnya diam-diam

        $this->assertSame(3, $this->jumlah());
    }

    public function test_pindah_ke_rt_lain_di_bontang_terhitung_lagi_setelah_rt_baru_memverifikasi(): void
    {
        // Verifikasi terbaru yang berlaku (anak.verif_rt_*): RT baru mengusulkan "berdomisili".
        $this->anak(['verif_rt_status' => 'berdomisili', 'verif_rt_reviu' => 'diusulkan']);

        $this->assertSame(1, $this->jumlah());
    }

    public function test_pindah_wilker_dengan_alamat_diperbarui_ikut_wilker_baru(): void
    {
        $a = $this->anak();
        $this->assertSame(1, $this->jumlah(['id_kelurahan' => $this->kel->id]));
        $this->assertSame(0, $this->jumlah(['id_kelurahan' => $this->kelLain->id]));

        $a->update(['id_kel' => $this->kelLain->id]);

        $this->assertSame(0, $this->jumlah(['id_kelurahan' => $this->kel->id]));
        $this->assertSame(1, $this->jumlah(['id_kelurahan' => $this->kelLain->id]));
        $this->assertSame(1, $this->jumlah(), 'tetap satu di tingkat kota');
    }

    public function test_tanda_sasaran_tidak_diubah_dan_kembali_terhitung_bila_status_berbalik(): void
    {
        $a = $this->anak(['status' => 0]);
        $this->assertSame(0, $this->jumlah());
        $this->assertSame(1, (int) $a->fresh()->sasaran_balita_kesmas, 'tanda tidak disentuh');

        $a->update(['status' => 1]);

        $this->assertSame(1, $this->jumlah());
    }

    public function test_semua_kartu_dan_registri_memakai_aturan_yang_sama(): void
    {
        $this->anak();
        $this->anak(['status' => 0]);
        $this->anak(['verif_rt_status' => 'pindah', 'verif_rt_reviu' => 'disetujui']);
        $p = $this->periode();

        $this->assertSame(1, $this->svc->spmKohort($p, [])['anak_balita']['sasaran']);
        $this->assertSame(1, $this->svc->pemantauanTk($p, [])['sasaran']);
        $this->assertSame(1, $this->svc->sdidtk($p, [])['total']['sasaran']);
        $this->assertSame(1, $this->svc->ckg($p, [])['kelompok']['t2']['sasaran']);
        $this->assertSame(1, $this->svc->layananLingkungan($p, [])['sasaran']);
        $this->assertSame(1, $this->svc->registri($p, [])['total']);
    }

    public function test_banner_penandaan_mengecualikan_yang_keluar_dan_melaporkan_jumlahnya(): void
    {
        $this->anak();                                                                              // dihitung
        $this->anak(['sasaran_balita_kesmas' => null]);                                             // belum ditandai
        $this->anak(['status' => 0]);                                                               // bertanda, keluar
        $this->anak(['verif_rt_status' => 'meninggal', 'verif_rt_reviu' => 'disetujui']);          // bertanda, keluar
        $this->anak(['status' => 0, 'sasaran_balita_kesmas' => null]);                              // keluar tapi belum bertanda

        $r = $this->svc->penandaanSasaran($this->periode(), []);

        $this->assertSame(['total' => 2, 'bertanda' => 1, 'dilepas' => 0, 'belum' => 1, 'dikecualikan' => 2], $r,
            'dikecualikan = anak BERTANDA yang tak terhitung karena keluar (itulah selisih bertanda vs kartu)');
    }
}
