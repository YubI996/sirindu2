<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\EpidCounter;
use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\SurveillanceCase;
use App\Models\User;
use App\Repositories\Admin\Epidemiologi\SurveillanceRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Menghapus kasus di TENGAH deret merapatkan nomor di atasnya:
 * 1..10, hapus 007 → 008;009;010 turun jadi 007;008;009.
 *
 * Deret berjalan per prefix per tahun, jadi perapatan tak boleh menyeberang
 * penyakit maupun tahun. Nomor legacy non-format (mis. "KTM9") tidak disentuh.
 */
class EpidRenumberSetelahHapusTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Kecamatan $kec;
    private Kelurahan $kel;
    private Rt $rt;
    private SurveillanceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['type' => 0]);
        $this->kec = Kecamatan::factory()->create();
        $this->kel = Kelurahan::factory()->create(['id_kecamatan' => $this->kec->id]);
        $this->rt  = Rt::factory()->create(['id_kelurahan' => $this->kel->id]);
        $this->repo = app(SurveillanceRepository::class);

        $this->actingAs($this->admin);
    }

    private function penyakit(string $kode): JenisKasusEpidemiologi
    {
        return JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => $kode]);
    }

    private function buatKasus(string $noRegistrasi, ?JenisKasusEpidemiologi $penyakit = null): SurveillanceCase
    {
        return SurveillanceCase::factory()->create([
            'no_registrasi'    => $noRegistrasi,
            'id_kec'           => $this->kec->id,
            'id_kel'           => $this->kel->id,
            'id_rt'            => $this->rt->id,
            'id_jenis_kasus'   => ($penyakit ?? $this->penyakit('DIFTERI_OBS'))->id,
            'id_petugas_input' => $this->admin->id,
            'created_by'       => $this->admin->id,
            'updated_by'       => $this->admin->id,
        ]);
    }

    /** @return string[] daftar no_registrasi yang tersisa, terurut */
    private function nomorTersisa(): array
    {
        return SurveillanceCase::orderBy('no_registrasi')->pluck('no_registrasi')->all();
    }

    public function test_hapus_nomor_tengah_merapatkan_nomor_di_atasnya(): void
    {
        $difteri = $this->penyakit('DIFTERI_OBS');
        foreach (range(1, 10) as $n) {
            $this->buatKasus('D-171026' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), $difteri);
        }

        $target = SurveillanceCase::where('no_registrasi', 'D-171026007')->firstOrFail();
        $this->repo->deleteCase($target->id);

        $this->assertSame([
            'D-171026001', 'D-171026002', 'D-171026003', 'D-171026004', 'D-171026005',
            'D-171026006', 'D-171026007', 'D-171026008', 'D-171026009',
        ], $this->nomorTersisa());
    }

    /** Yang di BAWAH nomor terhapus tak boleh bergeser. */
    public function test_nomor_di_bawahnya_tidak_berubah(): void
    {
        $difteri = $this->penyakit('DIFTERI_OBS');
        $bawah = $this->buatKasus('D-171026002', $difteri);
        $this->buatKasus('D-171026005', $difteri);
        $this->buatKasus('D-171026006', $difteri);

        $this->repo->deleteCase(SurveillanceCase::where('no_registrasi', 'D-171026005')->firstOrFail()->id);

        $this->assertSame('D-171026002', $bawah->fresh()->no_registrasi);
        $this->assertSame(['D-171026002', 'D-171026005'], $this->nomorTersisa());
    }

    /** Deret per penyakit: menghapus Difteri tak menyentuh nomor Campak. */
    public function test_tidak_menyeberang_prefix_penyakit_lain(): void
    {
        $difteri = $this->penyakit('DIFTERI_OBS');
        $campak  = $this->penyakit('CAMPAK_RUBELLA');

        $this->buatKasus('D-171026001', $difteri);
        $difteriNaik = $this->buatKasus('D-171026002', $difteri);
        $campakKasus = $this->buatKasus('C-171026002', $campak);
        $afp = $this->buatKasus('171026002', $this->penyakit('AFP'));

        $this->repo->deleteCase(SurveillanceCase::where('no_registrasi', 'D-171026001')->firstOrFail()->id);

        $this->assertSame('D-171026001', $difteriNaik->fresh()->no_registrasi);
        $this->assertSame('C-171026002', $campakKasus->fresh()->no_registrasi, 'nomor Campak ikut tergeser');
        $this->assertSame('171026002', $afp->fresh()->no_registrasi, 'nomor AFP ikut tergeser');
    }

    /** Deret per tahun: menghapus kasus 2026 tak menyentuh nomor 2025. */
    public function test_tidak_menyeberang_tahun_lain(): void
    {
        $difteri = $this->penyakit('DIFTERI_OBS');
        $tahunLalu = $this->buatKasus('D-171025008', $difteri);
        $this->buatKasus('D-171026005', $difteri);
        $this->buatKasus('D-171026008', $difteri);

        $this->repo->deleteCase(SurveillanceCase::where('no_registrasi', 'D-171026005')->firstOrFail()->id);

        $this->assertSame('D-171025008', $tahunLalu->fresh()->no_registrasi);
    }

    /** Nomor legacy di luar format resmi tak boleh ikut dirapatkan. */
    public function test_nomor_legacy_tidak_disentuh(): void
    {
        $difteri = $this->penyakit('DIFTERI_OBS');
        $legacy = $this->buatKasus('KTM9', $difteri);
        $this->buatKasus('D-171026003', $difteri);
        $this->buatKasus('D-171026004', $difteri);

        $this->repo->deleteCase(SurveillanceCase::where('no_registrasi', 'D-171026003')->firstOrFail()->id);

        $this->assertSame('KTM9', $legacy->fresh()->no_registrasi);
        $this->assertSame('D-171026003', SurveillanceCase::where('no_registrasi', 'like', 'D-%')->first()->no_registrasi);
    }

    /** Menghapus kasus BERNOMOR LEGACY tak boleh menggeser apa pun. */
    public function test_hapus_kasus_legacy_tidak_menggeser(): void
    {
        $difteri = $this->penyakit('DIFTERI_OBS');
        $legacy = $this->buatKasus('KTM9', $difteri);
        $this->buatKasus('D-171026003', $difteri);

        $this->repo->deleteCase($legacy->id);

        $this->assertSame(['D-171026003'], $this->nomorTersisa());
    }

    /** Setelah dirapatkan, kasus baru menyambung tanpa melompat. */
    public function test_nomor_berikutnya_menyambung_setelah_dirapatkan(): void
    {
        $difteri = $this->penyakit('DIFTERI_OBS');
        foreach ([1, 2, 3] as $n) {
            $this->buatKasus('D-171026' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), $difteri);
        }

        $this->repo->deleteCase(SurveillanceCase::where('no_registrasi', 'D-171026002')->firstOrFail()->id);

        // tersisa 001 & 002 (bekas 003) → berikutnya harus 003, bukan 004
        $this->assertSame(3, EpidCounter::getNextSequence(2026, 'D'));
    }

    /** Setiap perubahan nomor tercatat agar bisa ditelusuri. */
    public function test_perubahan_nomor_dicatat_di_log(): void
    {
        $difteri = $this->penyakit('DIFTERI_OBS');
        $this->buatKasus('D-171026001', $difteri);
        $this->buatKasus('D-171026002', $difteri);
        $naik = $this->buatKasus('D-171026003', $difteri);

        $this->repo->deleteCase(SurveillanceCase::where('no_registrasi', 'D-171026002')->firstOrFail()->id);

        $this->assertDatabaseHas('epid_renumber_log', [
            'id_surveillance_case' => $naik->id,
            'no_lama'              => 'D-171026003',
            'no_baru'              => 'D-171026002',
            'dipicu_hapus'         => 'D-171026002',
            'id_user'              => $this->admin->id,
        ]);
    }
}
