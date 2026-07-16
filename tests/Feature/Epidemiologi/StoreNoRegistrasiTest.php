<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\JenisKasusEpidemiologi;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Rt;
use App\Models\RumahSakit;
use App\Models\SurveillanceCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * No. Epid pada form Tambah Kasus.
 *
 * Aturan yang dikehendaki:
 *  - Field boleh diisi petugas (Dinkes maupun faskes) — nomor dari register resmi.
 *  - Dikosongkan → di-generate otomatis (format [prefix]-1710[YY][NNN]).
 *  - Nomor sudah terdaftar → JANGAN sekadar ditolak: arahkan petugas memperbarui
 *    data yang ada. Tapi arahan itu hanya boleh muncul bila kasusnya memang boleh
 *    dilihat petugas tsb — kalau milik faskes lain, jangan bocorkan apa pun.
 */
class StoreNoRegistrasiTest extends TestCase
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
        $this->disease   = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'CAMPAK_RUBELLA']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nik'                => '3201011234560001',
            'nama_lengkap'       => 'John Doe',
            'tanggal_lahir'      => '1990-01-15',
            'jenis_kelamin'      => 'L',
            'alamat_lengkap'     => 'Jl. Merdeka No. 1',
            'id_kec'             => $this->kecamatan->id,
            'id_kel'             => $this->kelurahan->id,
            'id_rt'              => $this->rt->id,
            'nama_pelapor'       => 'Dr. Pelapor',
            'id_jenis_kasus'     => $this->disease->id,
            'tanggal_onset'      => Carbon::now()->subDays(5)->format('Y-m-d'),
            'tanggal_konsultasi' => Carbon::now()->subDays(3)->format('Y-m-d'),
        ], $overrides);
    }

    private function buatKasus(string $noReg, array $overrides = []): SurveillanceCase
    {
        return SurveillanceCase::factory()->create(array_merge([
            'no_registrasi'    => $noReg,
            'id_kec'           => $this->kecamatan->id,
            'id_kel'           => $this->kelurahan->id,
            'id_rt'            => $this->rt->id,
            'id_jenis_kasus'   => $this->disease->id,
            'id_petugas_input' => $this->admin->id,
            'created_by'       => $this->admin->id,
            'updated_by'       => $this->admin->id,
        ], $overrides));
    }

    public function test_dikosongkan_maka_digenerate_otomatis(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.epidemiologi.store'), $this->payload());

        $case = SurveillanceCase::where('nik', '3201011234560001')->first();

        $this->assertNotNull($case);
        $this->assertStringContainsString('1710' . date('y'), $case->no_registrasi);
    }

    public function test_nomor_yang_diisi_petugas_dipakai_bukan_diabaikan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.epidemiologi.store'), $this->payload([
                'no_registrasi' => 'C-171026777',
            ]));

        $case = SurveillanceCase::where('nik', '3201011234560001')->first();

        $this->assertNotNull($case);
        $this->assertSame('C-171026777', $case->no_registrasi);
    }

    public function test_faskes_juga_boleh_menetapkan_nomor(): void
    {
        $rs = RumahSakit::create([
            'id_kecamatan' => $this->kecamatan->id,
            'name'         => 'RS Uji Faskes',
            'kode_rs'      => 'RS-UJI-99',
            'is_active'    => true,
        ]);

        $userRs = User::factory()->create([
            'type'        => 1,
            'role'        => 'surveilans_rs',
            'faskes_type' => 'rs',
            'id_rs'       => $rs->id,
        ]);

        $this->actingAs($userRs)
            ->post(route('admin.epidemiologi.store'), $this->payload([
                'no_registrasi' => 'C-171026778',
                'nik'           => '3201011234560099',
            ]));

        $case = SurveillanceCase::where('nik', '3201011234560099')->first();

        $this->assertNotNull($case, 'Faskes harus bisa input manual data surveilans.');
        $this->assertSame('C-171026778', $case->no_registrasi);
    }

    public function test_nomor_sudah_ada_diarahkan_untuk_memperbarui(): void
    {
        $lama = $this->buatKasus('C-171026779', ['nama_lengkap' => 'Pasien Lama']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.epidemiologi.store'), $this->payload([
                'no_registrasi' => 'C-171026779',
            ]));

        // Diarahkan memperbarui data yang ada, bukan sekadar ditolak.
        $response->assertSessionHas('epid_duplikat');
        $this->assertSame($lama->id, session('epid_duplikat')['id']);

        // Tidak boleh membuat kasus baru.
        $this->assertNull(SurveillanceCase::where('nik', '3201011234560001')->first());
        $this->assertSame(1, SurveillanceCase::where('no_registrasi', 'C-171026779')->count());
    }

    public function test_form_menampilkan_arahan_perbarui_data(): void
    {
        // Arahan hanya berguna kalau benar-benar terlihat petugas di form.
        $lama = $this->buatKasus('C-171026781', ['nama_lengkap' => 'Pasien Lama']);

        $this->actingAs($this->admin)
            ->withSession(['epid_duplikat' => [
                'id'            => $lama->id,
                'no_registrasi' => $lama->no_registrasi,
                'nama_lengkap'  => $lama->nama_lengkap,
                'url_edit'      => route('admin.epidemiologi.edit', $lama->id),
            ]])
            ->get(route('admin.epidemiologi.create'))
            ->assertOk()
            ->assertSee('Perbarui data yang ada')
            ->assertSee('C-171026781')
            ->assertSee(route('admin.epidemiologi.edit', $lama->id));
    }

    public function test_nomor_milik_faskes_lain_tidak_dibocorkan(): void
    {
        // Kasus milik RS A; user RS B mencoba memakai nomor yang sama.
        $rsA = RumahSakit::create(['name' => 'RS A Uji', 'kode_rs' => 'RS-UJI-A', 'is_active' => true]);
        $rsB = RumahSakit::create(['name' => 'RS B Uji', 'kode_rs' => 'RS-UJI-B', 'is_active' => true]);

        $this->buatKasus('C-171026780', [
            'nama_lengkap' => 'Pasien Rahasia',
            'faskes_type'  => 'rs',
            'id_faskes'    => $rsA->id,
        ]);

        $userRsB = User::factory()->create([
            'type'        => 1,
            'role'        => 'surveilans_rs',
            'faskes_type' => 'rs',
            'id_rs'       => $rsB->id,
        ]);

        $response = $this->actingAs($userRsB)
            ->post(route('admin.epidemiologi.store'), $this->payload([
                'no_registrasi' => 'C-171026780',
                'nik'           => '3201011234560077',
            ]));

        // Tak boleh diarahkan ke kasus faskes lain (arahan itu memuat id + nama pasien).
        $response->assertSessionMissing('epid_duplikat');
        $response->assertSessionHasErrors('no_registrasi');
        $this->assertNull(SurveillanceCase::where('nik', '3201011234560077')->first());
    }
}
