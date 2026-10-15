<?php

namespace Tests\Feature\AkunKesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Models\RumahSakit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BerandaPeranKesmasTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_nama_sarana_ada(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'nama_sarana'));
    }

    public function test_beranda_per_peran(): void
    {
        // Lingkup nyata: beranda peran terbatas ke dasbor Kesmas hanya bila lingkupnya benar-benar ada.
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'Bontang Baru', 'id_kecamatan' => $kec->id]);
        $pkm = Puskesmas::factory()->create();
        $pos = Posyandu::factory()->create(['id_puskesmas' => $pkm->id]);
        $rs = RumahSakit::create(['name' => 'RSUD Uji', 'jenis_rs' => 'RSUD', 'is_active' => true]);
        $lingkup = [
            'pimpinan' => [],
            'kelurahan' => ['id_kel' => $kel->id],
            'kader' => ['id_posyandu' => $pos->id],
            'kesmas_puskesmas' => ['id_puskesmas' => $pkm->id],
            'kesmas_rs' => ['id_rs' => $rs->id],
        ];
        foreach ($lingkup as $role => $atribut) {
            $this->assertSame('admin.kesmas.dashboard', (new User(['role' => $role] + $atribut))->berandaRoute(), $role);
        }
        $this->assertSame('akses.belumDiatur', (new User(['role' => 'sarkes']))->berandaRoute());
        $this->assertSame('rt.verifikasi', (new User(['role' => 'rt']))->berandaRoute());
        $this->assertSame('admin.home', (new User(['role' => 'superadmin']))->berandaRoute());
    }

    /** Lingkup kosong/tak valid → dasbor Kesmas pasti 403 dan tombol "Kembali"-nya buntu; arahkan ke halaman penjelasan. */
    public function test_lingkup_kosong_atau_hilang_ke_akses_belum_diatur(): void
    {
        foreach (['kelurahan', 'kader', 'kesmas_puskesmas', 'kesmas_rs'] as $role) {
            $this->assertSame('akses.belumDiatur', (new User(['role' => $role]))->berandaRoute(), "$role tanpa lingkup");
        }
        $this->assertSame('akses.belumDiatur', (new User(['role' => 'kelurahan', 'id_kel' => 9999999]))->berandaRoute(), 'baris kelurahan tak ada');

        $kel = Kelurahan::factory()->create();
        $this->assertSame('admin.kesmas.dashboard', (new User(['role' => 'kelurahan', 'id_kel' => $kel->id]))->berandaRoute());
    }

    public function test_login_peran_terbatas_ke_berandanya(): void
    {
        config(['services.recaptcha.enabled' => false]);
        User::factory()->create(['email' => 'pim@sirindu.test', 'password' => bcrypt('rahasia123'), 'type' => 2, 'role' => 'pimpinan']);

        $this->post(route('login'), ['email' => 'pim@sirindu.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('admin.kesmas.dashboard'));
    }

    public function test_sarkes_melihat_halaman_akses_belum_diatur(): void
    {
        $user = User::factory()->create(['type' => 2, 'role' => 'sarkes', 'nama_sarana' => 'Klinik Pratama Sehat']);

        $this->actingAs($user)->get(route('akses.belumDiatur'))
            ->assertOk()
            ->assertSee('Akses akun Anda belum diatur. Hubungi Dinas Kesehatan.')
            ->assertSee('Klinik Pratama Sehat');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('akses.belumDiatur'))->assertRedirect(route('login'));
    }
}
