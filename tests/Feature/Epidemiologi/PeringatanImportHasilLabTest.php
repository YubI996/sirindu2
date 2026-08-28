<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\EpidRenumberLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Import hasil lab mencocokkan baris memakai No Epid (string), bukan id kasus.
 * Karena menghapus kasus merapatkan deret, nomor pada file lab yang dibuat
 * SEBELUM pergeseran sudah menjadi milik kasus lain — hasil lab bisa masuk ke
 * pasien yang salah tanpa pesan error apa pun.
 *
 * Klien memilih peringatan (bukan penolakan otomatis), jadi yang dikunci di
 * sini: peringatannya muncul saat memang ada pergeseran, menyebut nomor lama
 * dan barunya, dan TIDAK muncul saat belum pernah ada pergeseran — supaya tak
 * jadi imbauan permanen yang otomatis diabaikan petugas.
 */
class PeringatanImportHasilLabTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // type=0 => super-admin, bukan faskes: modal import hasil lab hanya
        // dirender untuk non-faskes (@if(!$isFaskes)).
        $this->admin = User::factory()->create(['type' => 0]);
    }

    private function catatGeser(string $lama, string $baru): EpidRenumberLog
    {
        return EpidRenumberLog::create([
            'no_lama'      => $lama,
            'no_baru'      => $baru,
            'dipicu_hapus' => $lama,
            'id_user'      => $this->admin->id,
            'created_at'   => now(),
        ]);
    }

    public function test_tanpa_pergeseran_peringatan_tidak_muncul(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.index'));

        $response->assertOk();
        $response->assertDontSee('Periksa dulu nomor Epid pada file Anda', false);
    }

    public function test_setelah_ada_pergeseran_peringatan_muncul(): void
    {
        $this->catatGeser('D-171026003', 'D-171026002');

        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.index'));

        $response->assertOk();
        $response->assertSee('Periksa dulu nomor Epid pada file Anda', false);
        $response->assertSee('pasien yang salah', false);
    }

    public function test_peringatan_menyebut_nomor_lama_dan_baru_terakhir(): void
    {
        $this->catatGeser('D-171026003', 'D-171026002');

        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.index'));

        // Nomor konkret, bukan imbauan umum — petugas bisa langsung mencocokkan
        // dengan isi filenya.
        $response->assertSee('D-171026003', false);
        $response->assertSee('D-171026002', false);
    }

    public function test_peringatan_memakai_pergeseran_paling_baru(): void
    {
        $lama = $this->catatGeser('D-171026009', 'D-171026008');
        $lama->created_at = now()->subDays(10);
        $lama->save();

        $this->catatGeser('C-171026004', 'C-171026003');

        $response = $this->actingAs($this->admin)->get(route('admin.epidemiologi.index'));

        $response->assertSee('C-171026004', false);
        $response->assertSee('2 nomor berubah dalam 30 hari terakhir', false);
    }
}
