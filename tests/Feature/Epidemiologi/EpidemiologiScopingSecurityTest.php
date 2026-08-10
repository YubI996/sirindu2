<?php

namespace Tests\Feature\Epidemiologi;

use App\Models\Anak;
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
 * Kunci perbaikan broken access control / IDOR modul epidemiologi (audit 2026-08-10):
 *
 *  1. lookupNik — dulu TANPA scope: sembarang NIK memanen biodata (nama, alamat,
 *     telepon, ortu) lintas wilayah, plus seluruh registry `anak` se-kota. Kini
 *     dibatasi visibleTo (kasus) & catchment kelurahan (anak).
 *  2. getMapData?city_wide=1 — dulu membocorkan nama pasien + No. Epid se-kota ke
 *     faskes mana pun. Kini city_wide untuk non-superadmin = AGREGAT saja (hitungan),
 *     tanpa daftar nama/marker.
 *  3. Field scoping (faskes_type/id_faskes/created_by) tetap fillable demi penulis
 *     tepercaya, tapi request pengguna tak boleh menjangkaunya — dikunci di sini.
 */
class EpidemiologiScopingSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private Kecamatan $kecamatan;
    private Kelurahan $kelurahan;
    private Rt $rt;
    private JenisKasusEpidemiologi $disease;
    private RumahSakit $rsA;
    private RumahSakit $rsB;
    private User $userRsA;
    private User $userRsB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = User::factory()->create(['type' => 0]); // superadmin
        $this->kecamatan = Kecamatan::factory()->create();
        $this->kelurahan = Kelurahan::factory()->create(['id_kecamatan' => $this->kecamatan->id]);
        $this->rt        = Rt::factory()->create(['id_kelurahan' => $this->kelurahan->id]);
        $this->disease   = JenisKasusEpidemiologi::factory()->create(['kode_penyakit' => 'CAMPAK_RUBELLA']);

        $this->rsA = RumahSakit::create(['name' => 'RS A Uji', 'kode_rs' => 'RS-SEC-A', 'is_active' => true]);
        $this->rsB = RumahSakit::create(['name' => 'RS B Uji', 'kode_rs' => 'RS-SEC-B', 'is_active' => true]);

        $this->userRsA = User::factory()->create([
            'type' => 1, 'role' => 'surveilans_rs', 'faskes_type' => 'rs', 'id_rs' => $this->rsA->id,
        ]);
        $this->userRsB = User::factory()->create([
            'type' => 1, 'role' => 'surveilans_rs', 'faskes_type' => 'rs', 'id_rs' => $this->rsB->id,
        ]);
    }

    private function kasusRsA(array $overrides = []): SurveillanceCase
    {
        return SurveillanceCase::factory()->create(array_merge([
            'id_kec'          => $this->kecamatan->id,
            'id_kel'          => $this->kelurahan->id,
            'id_rt'           => $this->rt->id,
            'id_jenis_kasus'  => $this->disease->id,
            'faskes_type'     => 'rs',
            'id_faskes'       => $this->rsA->id,
            'created_by'      => $this->admin->id,
            'updated_by'      => $this->admin->id,
            'tanggal_onset'   => Carbon::now()->subDays(5)->format('Y-m-d'),
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nik'                => '3201011234567001',
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

    // ─────────────── Finding 1: lookupNik ───────────────

    public function test_lookupnik_tidak_bocorkan_biodata_kasus_faskes_lain(): void
    {
        $nik = '3201019999990001';
        $this->kasusRsA(['nik' => $nik, 'nama_lengkap' => 'Pasien Rahasia', 'alamat_lengkap' => 'Alamat Rahasia']);

        // RS B (bukan pemilik kasus) tak boleh dapat biodata apa pun.
        $this->actingAs($this->userRsB)
            ->getJson(route('admin.epidemiologi.lookupNik', $nik))
            ->assertOk()
            ->assertJson(['found' => false])
            ->assertDontSee('Pasien Rahasia')
            ->assertDontSee('Alamat Rahasia');

        // Superadmin tetap boleh (tak di-scope).
        $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.lookupNik', $nik))
            ->assertOk()
            ->assertJson(['found' => true, 'source' => 'surveillance']);
    }

    public function test_lookupnik_anak_registry_dibatasi_wilayah(): void
    {
        $nik = '3201018888880002';
        // NIK ini hanya ada di registry anak, bukan kasus surveilans.
        Anak::forceCreate([
            'nik' => $nik, 'nama' => 'Anak Rahasia', 'jk' => 1, 'status' => 1,
            'tgl_lahir' => '2020-01-01', 'id_kel' => $this->kelurahan->id,
            'alamat' => 'Alamat Anak Rahasia',
        ]);

        // RS tak berwilayah → fallback anak difilter (fail closed).
        $this->actingAs($this->userRsB)
            ->getJson(route('admin.epidemiologi.lookupNik', $nik))
            ->assertOk()
            ->assertJson(['found' => false])
            ->assertDontSee('Anak Rahasia');

        // Superadmin boleh melihat fallback registry.
        $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.lookupNik', $nik))
            ->assertOk()
            ->assertJson(['found' => true, 'source' => 'anak']);
    }

    // ─────────────── Finding 2: getMapData city_wide ───────────────

    public function test_mapdata_citywide_sembunyikan_nama_pasien_dari_faskes(): void
    {
        $this->kasusRsA([
            'nama_lengkap' => 'Pasien Peta Rahasia',
            'no_registrasi' => 'C-171099001',
            'latitude' => -0.123, 'longitude' => 117.456,
        ]);

        // Faskes (RS B) minta peta city-wide: hitungan boleh, nama TIDAK.
        $resp = $this->actingAs($this->userRsB)
            ->getJson(route('admin.epidemiologi.mapData', ['city_wide' => 1]))
            ->assertOk()
            ->assertDontSee('Pasien Peta Rahasia')
            ->assertDontSee('C-171099001')
            ->assertJsonPath('caseMarkers', []);

        // Hitungan agregat tetap mengalir (choropleth tak lumpuh).
        $this->assertGreaterThanOrEqual(1, $resp->json('totalCases'));

        // Superadmin tetap melihat detail.
        $this->actingAs($this->admin)
            ->getJson(route('admin.epidemiologi.mapData', ['city_wide' => 1]))
            ->assertOk()
            ->assertSee('Pasien Peta Rahasia');
    }

    // ─────────────── Finding 3: mass-assignment field scoping ───────────────

    public function test_faskes_tak_bisa_menyuntik_field_scoping_saat_store(): void
    {
        // RS A mencoba mengklaim/menyembunyikan kasus lewat field terlarang di request.
        $this->actingAs($this->userRsA)
            ->post(route('admin.epidemiologi.store'), $this->payload([
                'nik'         => '3201010000000010',
                'faskes_type' => 'puskesmas',
                'id_faskes'   => 999999,
                'created_by'  => 999999,
            ]));

        $case = SurveillanceCase::where('nik', '3201010000000010')->first();

        $this->assertNotNull($case);
        // Field scoping ditetapkan dari Auth, bukan dari request.
        $this->assertSame('rs', $case->faskes_type);
        $this->assertSame($this->rsA->id, $case->id_faskes);
        $this->assertSame($this->userRsA->id, $case->created_by);
    }

    public function test_faskes_tak_bisa_memindah_kasus_keluar_scope_saat_update(): void
    {
        $case = $this->kasusRsA(['nik' => '3201010000000011']);

        $this->actingAs($this->userRsA)
            ->put(route('admin.epidemiologi.update', $case->id), $this->payload([
                'nik'         => '3201010000000011',
                'faskes_type' => 'puskesmas',
                'id_faskes'   => 999999,
                'created_by'  => 999999,
            ]));

        $case->refresh();
        // Kepemilikan faskes tak berubah; kasus tetap dalam scope RS A.
        $this->assertSame('rs', $case->faskes_type);
        $this->assertSame($this->rsA->id, $case->id_faskes);
        $this->assertSame($this->admin->id, $case->created_by); // pembuat asli tak tertimpa
        $this->assertSame($this->userRsA->id, $case->updated_by);
    }
}
