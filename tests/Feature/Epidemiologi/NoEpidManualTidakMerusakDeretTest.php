<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\EpidCounter;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Hipotesis klien (2026-09-07): setelah petugas boleh mengisi No. Epid sendiri,
 * counter tidak ikut disesuaikan — sehingga penomoran otomatis berikutnya masih
 * memakai nomor lama dan bertabrakan.
 *
 * Mekanismenya memang begitu: SurveillanceRepository::storeCase() TIDAK pernah
 * menyentuh EpidCounter saat nomor diisi manual. Yang menahannya adalah
 * EpidCounter::getNextSequence() yang membaca ulang nomor tertinggi yang
 * BENAR-BENAR terpakai di surveillance_cases sebelum memberi nomor
 * (`max(counter, maxSequenceTerpakai) + 1`), bukan sekadar menaikkan counter.
 *
 * Tes ini mengunci perilaku itu untuk beberapa bentuk pengisian manual.
 * Pada kode SEBELUM c3d2c1c hipotesis klien tepat — getNextSequence() waktu itu
 * hanya `increment()` tanpa membaca nomor terpakai.
 */
class NoEpidManualTidakMerusakDeretTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Kecamatan $kecamatan;
    private Kelurahan $kelurahan;
    private Rt $rt;
    private JenisKasusEpidemiologi $campak;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = User::factory()->create(['type' => 0]);
        $this->kecamatan = Kecamatan::factory()->create();
        $this->kelurahan = Kelurahan::factory()->create(['id_kecamatan' => $this->kecamatan->id]);
        $this->rt        = Rt::factory()->create(['id_kelurahan' => $this->kelurahan->id]);
        $this->campak    = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'CAMPAK_RUBELLA']);
    }

    private function kasus(string $noReg): SurveillanceCase
    {
        return SurveillanceCase::factory()->create([
            'no_registrasi'  => $noReg,
            'id_kec'         => $this->kecamatan->id,
            'id_kel'         => $this->kelurahan->id,
            'id_rt'          => $this->rt->id,
            'id_jenis_kasus' => $this->campak->id,
            'created_by'     => $this->admin->id,
            'updated_by'     => $this->admin->id,
            'tanggal_lapor'  => Carbon::create(2026, 6, 1),
            'tanggal_onset'  => Carbon::create(2026, 5, 28),
        ]);
    }

    private function nomorBerikutnya(): string
    {
        return EpidCounter::formatNoRegistrasi(2026, 'C', EpidCounter::getNextSequence(2026, 'C'));
    }

    public function test_nomor_manual_di_tengah_deret_tidak_dipakai_ulang(): void
    {
        // Deret 001..010 ada, 005 sempat kosong lalu diisi manual petugas.
        foreach ([1, 2, 3, 4, 6, 7, 8, 9, 10] as $n) {
            $this->kasus(EpidCounter::formatNoRegistrasi(2026, 'C', $n));
        }
        $this->kasus('C-171026005'); // diisi manual — counter TIDAK ikut naik
        EpidCounter::updateOrCreate(['tahun' => 2026, 'prefix' => 'C'], ['last_sequence' => 4]);

        $berikutnya = $this->nomorBerikutnya();

        $this->assertFalse(
            SurveillanceCase::where('no_registrasi', $berikutnya)->exists(),
            "Nomor otomatis {$berikutnya} bertabrakan dengan kasus yang sudah ada."
        );
    }

    public function test_nomor_manual_jauh_di_depan_tidak_dipakai_ulang(): void
    {
        // Petugas menomori sesuai register resmi, melompat jauh dari counter.
        $this->kasus('C-171026050');
        EpidCounter::updateOrCreate(['tahun' => 2026, 'prefix' => 'C'], ['last_sequence' => 1]);

        $berikutnya = $this->nomorBerikutnya();

        $this->assertSame('C-171026051', $berikutnya);
        $this->assertFalse(SurveillanceCase::where('no_registrasi', $berikutnya)->exists());
    }

    public function test_counter_tertinggal_jauh_tetap_aman(): void
    {
        // Persis keadaan yang dilaporkan klien: C-171026002 sudah ada,
        // counter masih 1 (mis. sisa import yang tak menaikkan counter).
        $this->kasus('C-171026001');
        $this->kasus('C-171026002');
        EpidCounter::updateOrCreate(['tahun' => 2026, 'prefix' => 'C'], ['last_sequence' => 1]);

        $berikutnya = $this->nomorBerikutnya();

        $this->assertSame('C-171026003', $berikutnya);
    }

    public function test_baris_counter_belum_ada_tetap_aman(): void
    {
        // Setelah migrasi 2026_07_16 tabel epid_counter dikosongkan.
        $this->kasus('C-171026007');
        EpidCounter::where('tahun', 2026)->where('prefix', 'C')->delete();

        $this->assertSame('C-171026008', $this->nomorBerikutnya());
    }

    public function test_nomor_manual_format_tak_resmi_tidak_mengacaukan_deret(): void
    {
        // Petugas mengetik format di luar pola resmi (mis. dari register lama).
        $this->kasus('C-171026003');
        $this->kasus('KTM9');
        $this->kasus('C-17102600');   // kurang satu digit
        EpidCounter::updateOrCreate(['tahun' => 2026, 'prefix' => 'C'], ['last_sequence' => 1]);

        $berikutnya = $this->nomorBerikutnya();

        $this->assertSame('C-171026004', $berikutnya);
        $this->assertFalse(SurveillanceCase::where('no_registrasi', $berikutnya)->exists());
    }
}
