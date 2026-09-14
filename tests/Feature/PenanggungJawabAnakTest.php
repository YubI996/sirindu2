<?php

namespace Tests\Feature;

use App\Exports\TimbangDaftarExport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Penanggung jawab (PJ) per anak bermasalah gizi — diisi langsung dari modal
 * daftar anak di dasbor Operasi Timbang (kategori stunting / wasting /
 * underweight — koreksi klien 14 Sep 2026). Satu PJ per anak, apa pun kategorinya.
 */
class PenanggungJawabAnakTest extends TestCase
{
    use RefreshDatabase;

    private function anakOt(string $nik, array $o = [], float $zTbU = -2.5): Anak
    {
        $anak = Anak::create(array_merge([
            'nama' => 'Anak OT '.$nik, 'nik' => $nik, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1, 'sumber' => 'operasi_timbang',
        ], $o));

        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => now()->subDays(30)->toDateString(), 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
            'zscore_bb_u' => 0.0, 'zscore_pb_u' => $zTbU, 'zscore_bb_pb' => 0.0,
            'sumber' => 'operasi_timbang',
        ]);

        return $anak;
    }

    private function kelurahan(string $nama): Kelurahan
    {
        return Kelurahan::create(['name' => $nama, 'id_kecamatan' => 1]);
    }

    public function test_superadmin_mengisi_pj_anak(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak  = $this->anakOt('3201000000007001');

        $this->actingAs($super)
            ->putJson(route('admin.timbang.pj', $anak), ['pj_nama' => '  Kader Sari  '])
            ->assertOk()
            ->assertJsonPath('pj_nama', 'Kader Sari')
            ->assertJsonPath('pj_oleh', $super->name);

        $anak->refresh();
        $this->assertSame('Kader Sari', $anak->pj_nama);
        $this->assertSame($super->id, (int) $anak->pj_updated_by);
        $this->assertNotNull($anak->pj_updated_at);
    }

    public function test_pj_kosong_menghapus_pj(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak  = $this->anakOt('3201000000007002', ['pj_nama' => 'Kader Lama']);

        $this->actingAs($super)
            ->putJson(route('admin.timbang.pj', $anak), ['pj_nama' => ''])
            ->assertOk()
            ->assertJsonPath('pj_nama', null);

        $this->assertNull($anak->refresh()->pj_nama);
    }

    public function test_pj_terlalu_panjang_ditolak(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak  = $this->anakOt('3201000000007003');

        $this->actingAs($super)
            ->putJson(route('admin.timbang.pj', $anak), ['pj_nama' => str_repeat('x', 101)])
            ->assertStatus(422);
    }

    public function test_faskes_tidak_bisa_mengisi_pj_anak_kelurahan_lain(): void
    {
        $kelA = $this->kelurahan('Kel A');
        $kelB = $this->kelurahan('Kel B');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $kelA->id]);
        $anak   = $this->anakOt('3201000000007004', ['id_kel' => $kelB->id]);

        $this->actingAs($faskes)
            ->putJson(route('admin.timbang.pj', $anak), ['pj_nama' => 'Kader B'])
            ->assertForbidden();

        $this->assertNull($anak->refresh()->pj_nama);
    }

    public function test_faskes_bisa_mengisi_pj_anak_kelurahannya(): void
    {
        $kelA = $this->kelurahan('Kel A');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $kelA->id]);
        $anak   = $this->anakOt('3201000000007005', ['id_kel' => $kelA->id]);

        $this->actingAs($faskes)
            ->putJson(route('admin.timbang.pj', $anak), ['pj_nama' => 'Kader A'])
            ->assertOk();

        $this->assertSame('Kader A', $anak->refresh()->pj_nama);
    }

    public function test_tamu_tidak_bisa_mengisi_pj(): void
    {
        $anak = $this->anakOt('3201000000007006');

        $this->putJson(route('admin.timbang.pj', $anak), ['pj_nama' => 'X'])
            ->assertUnauthorized();
    }

    public function test_daftar_stunting_menyertakan_id_dan_pj(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak  = $this->anakOt('3201000000007007', ['pj_nama' => 'Kader Sari']);

        $rows = $this->actingAs($super)
            ->getJson(route('admin.timbang.daftar', ['kategori' => 'stunting']))
            ->assertOk()
            ->json('rows');

        $this->assertCount(1, $rows);
        $this->assertSame($anak->hashid, $rows[0]['id']);
        $this->assertSame('Kader Sari', $rows[0]['pj_nama']);
    }

    public function test_daftar_menyertakan_saran_nama_pj_yang_sudah_dipakai(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->anakOt('3201000000007008', ['pj_nama' => 'Kader Sari']);
        $this->anakOt('3201000000007009', ['pj_nama' => 'Bidan Rina']);
        $this->anakOt('3201000000007010', ['pj_nama' => 'Kader Sari']);
        $this->anakOt('3201000000007011');

        $saran = $this->actingAs($super)
            ->getJson(route('admin.timbang.daftar', ['kategori' => 'stunting']))
            ->assertOk()
            ->json('pj_saran');

        $this->assertSame(['Bidan Rina', 'Kader Sari'], $saran);
    }

    public function test_saran_pj_faskes_terbatas_kelurahannya(): void
    {
        $kelA = $this->kelurahan('Kel A');
        $kelB = $this->kelurahan('Kel B');
        $faskes = User::factory()->create(['type' => 1, 'id_kel' => $kelA->id]);
        $this->anakOt('3201000000007012', ['id_kel' => $kelA->id, 'pj_nama' => 'Kader A']);
        $this->anakOt('3201000000007013', ['id_kel' => $kelB->id, 'pj_nama' => 'Kader B']);

        $saran = $this->actingAs($faskes)
            ->getJson(route('admin.timbang.daftar', ['kategori' => 'stunting']))
            ->assertOk()
            ->json('pj_saran');

        $this->assertSame(['Kader A'], $saran);
    }

    public function test_export_kategori_pj_punya_kolom_penanggung_jawab(): void
    {
        $rows = [['nama' => 'A', 'pj_nama' => 'Kader Sari']];

        $denganPj = new TimbangDaftarExport($rows, 'Stunting', true);
        $this->assertContains('Penanggung Jawab', $denganPj->headings());
        $baris = $denganPj->array()[0];
        $this->assertSame('Kader Sari', end($baris));

        $tanpaPj = new TimbangDaftarExport($rows, 'Balita Sasaran', false);
        $this->assertNotContains('Penanggung Jawab', $tanpaPj->headings());
    }

    public function test_export_endpoint_hanya_menyertakan_pj_untuk_kategori_pj(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->anakOt('3201000000007014', ['pj_nama' => 'Kader Sari']);

        Excel::fake();
        $this->travelTo(now()->startOfMinute()); // nama berkas memuat timestamp
        $stamp = now()->format('Ymd_His');

        $this->actingAs($super)->get(route('admin.timbang.daftar.export', ['kategori' => 'stunting']))->assertOk();
        Excel::assertDownloaded(
            "daftar-stunting-{$stamp}.xlsx",
            fn (TimbangDaftarExport $e) => in_array('Penanggung Jawab', $e->headings(), true)
        );

        $this->actingAs($super)->get(route('admin.timbang.daftar.export', ['kategori' => 'sasaran']))->assertOk();
        Excel::assertDownloaded(
            "daftar-sasaran-{$stamp}.xlsx",
            fn (TimbangDaftarExport $e) => !in_array('Penanggung Jawab', $e->headings(), true)
        );
    }

    /** Blade dasbor: kolom PJ hanya untuk stunting, wasting, underweight (koreksi klien) & memakai endpoint PJ. */
    public function test_dasbor_memuat_kolom_pj_untuk_tiga_kategori(): void
    {
        $src = file_get_contents(resource_path('views/admin/dashboard/timbang.blade.php'));

        $this->assertMatchesRegularExpression(
            "/PJ_KATEGORI\s*=\s*\[\s*'stunting'\s*,\s*'wasting'\s*,\s*'underweight'\s*\]/",
            $src
        );
        $this->assertStringContainsString("admin.timbang.pj", $src);
        $this->assertStringContainsString('id="pj-saran"', $src);
        // Sisa percobaan lama: sel PJ ditulis SETELAH </tr> (baris rusak).
        $this->assertStringNotContainsString('r.Penanggung_Jawab', $src);
    }
}
