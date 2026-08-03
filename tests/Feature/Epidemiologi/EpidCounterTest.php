<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\EpidCounter;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Penomoran epidemiologi (no_registrasi) mengikuti pola nyata di lapangan:
 * `[prefix]-1710[YY][NNN]`, dengan urutan NNN dihitung **per prefix per tahun**
 * (C-171026001..217 dan D-171026001..002 hidup berdampingan; tiap deret mulai dari 1).
 *
 * Counter juga WAJIB memperhitungkan nomor yang masuk lewat import — importer
 * menulis no_registrasi sendiri tanpa menaikkan counter. Tanpa sinkronisasi,
 * generator memakai nomor yang sudah terpakai → kolom no_registrasi UNIQUE →
 * error 500 saat simpan.
 */
class EpidCounterTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Kecamatan $kecamatan;
    private Kelurahan $kelurahan;
    private Rt $rt;
    private JenisKasusEpidemiologi $disease;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = User::factory()->create(['type' => 0]);
        $this->kecamatan = Kecamatan::factory()->create();
        $this->kelurahan = Kelurahan::factory()->create(['id_kecamatan' => $this->kecamatan->id]);
        $this->rt        = Rt::factory()->create(['id_kelurahan' => $this->kelurahan->id]);
        $this->disease   = JenisKasusEpidemiologi::factory()->create();
    }

    private function buatKasus(string $noReg): SurveillanceCase
    {
        return SurveillanceCase::factory()->create([
            'no_registrasi'    => $noReg,
            'id_kec'           => $this->kecamatan->id,
            'id_kel'           => $this->kelurahan->id,
            'id_rt'            => $this->rt->id,
            'id_jenis_kasus'   => $this->disease->id,
            'id_petugas_input' => $this->admin->id,
            'created_by'       => $this->admin->id,
            'updated_by'       => $this->admin->id,
        ]);
    }

    public function test_menyesuaikan_diri_dengan_nomor_hasil_import(): void
    {
        // Import menulis C-171026217 tanpa menaikkan counter (counter masih 0/1).
        $this->buatKasus('C-171026217');

        $this->assertSame(218, EpidCounter::getNextSequence(2026, 'C'));
    }

    public function test_tidak_pernah_menghasilkan_nomor_yang_sudah_dipakai(): void
    {
        // Regresi persis bug 500: counter tertinggal di 1, C-171026002 sudah ada.
        $this->buatKasus('C-171026002');
        EpidCounter::updateOrCreate(
            ['tahun' => 2026, 'prefix' => 'C'],
            ['last_sequence' => 1]
        );

        $next = EpidCounter::getNextSequence(2026, 'C');

        $this->assertGreaterThan(2, $next);
        $this->assertFalse(
            SurveillanceCase::where('no_registrasi', 'C-1710' . '26' . str_pad((string) $next, 3, '0', STR_PAD_LEFT))->exists(),
            'Nomor yang di-generate tidak boleh bertabrakan dengan yang sudah ada.'
        );
    }

    public function test_sequence_terpisah_per_prefix(): void
    {
        // C dan D punya deret sendiri-sendiri — D tidak ikut melompat karena C.
        $this->buatKasus('C-171026217');
        $this->buatKasus('D-171026002');

        $this->assertSame(218, EpidCounter::getNextSequence(2026, 'C'));
        $this->assertSame(3, EpidCounter::getNextSequence(2026, 'D'));
    }

    public function test_sequence_terpisah_per_tahun(): void
    {
        $this->buatKasus('C-171025236');

        $this->assertSame(237, EpidCounter::getNextSequence(2025, 'C'));
        // Tahun 2026 belum punya kasus C → mulai dari 1
        $this->assertSame(1, EpidCounter::getNextSequence(2026, 'C'));
    }

    public function test_mengabaikan_nomor_format_legacy(): void
    {
        // 24 nomor legacy (KKR*/KTM*) di luar format — tak boleh mengacaukan sequence.
        $this->buatKasus('KTM9');
        $this->buatKasus('KKR3');

        $this->assertSame(1, EpidCounter::getNextSequence(2026, 'C'));
    }

    public function test_afp_tanpa_prefix_punya_deret_sendiri(): void
    {
        // AFP/Polio tidak berprefix: 1710[YY][NNN]
        $this->buatKasus('171026005');
        // Nomor berprefix tidak boleh terhitung sebagai deret AFP
        $this->buatKasus('C-171026217');

        $this->assertSame(6, EpidCounter::getNextSequence(2026, ''));
    }

    public function test_pemanggilan_berurutan_naik(): void
    {
        $this->assertSame(1, EpidCounter::getNextSequence(2026, 'P'));
        $this->assertSame(2, EpidCounter::getNextSequence(2026, 'P'));
        $this->assertSame(3, EpidCounter::getNextSequence(2026, 'P'));
    }

    public function test_hapus_kasus_terakhir_membebaskan_nomornya(): void
    {
        // Tahun bersih: 001,002,003 diinput lewat generator.
        $this->assertSame(1, EpidCounter::getNextSequence(2026, 'C'));
        $this->buatKasus('C-171026001');
        $this->assertSame(2, EpidCounter::getNextSequence(2026, 'C'));
        $this->buatKasus('C-171026002');
        $this->assertSame(3, EpidCounter::getNextSequence(2026, 'C'));
        $kasus3 = $this->buatKasus('C-171026003');

        // Hapus yang terakhir → nomor 003 harus bebas lagi (tak melompat ke 004).
        app(\App\Repositories\Admin\Epidemiologi\SurveillanceRepository::class)->deleteCase($kasus3->id);

        $this->assertSame(3, EpidCounter::getNextSequence(2026, 'C'));
    }

    public function test_hapus_menurunkan_counter_ke_nomor_tertinggi_yang_tersisa(): void
    {
        // 001 & 003 ada; counter sempat sampai 3.
        $this->buatKasus('C-171026001');
        $kasus3 = $this->buatKasus('C-171026003');
        EpidCounter::updateOrCreate(['tahun' => 2026, 'prefix' => 'C'], ['last_sequence' => 3]);

        // Hapus 003 (tertinggi) → counter turun ke 1 (maks tersisa), berikutnya 002.
        app(\App\Repositories\Admin\Epidemiologi\SurveillanceRepository::class)->deleteCase($kasus3->id);

        $this->assertSame(2, EpidCounter::getNextSequence(2026, 'C'));
    }
}
