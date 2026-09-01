<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Gizi & Timbang dikunci ke populasi Operasi Timbang (OT): statistik
 * yang ditampilkan tidak boleh bergeser hanya karena ada data baru ditambahkan
 * lewat jalur lain (input manual admin, import imunisasi, dst).
 *
 * Latar: sebelum ini dashboard membaca SELURUH anak/data_anak tanpa filter
 * sumber, jadi penambahan data apa pun langsung mengubah angka yang sudah
 * dipublikasikan Dinkes. Lihat CLAUDE.md § "Menghapus kasus merapatkan nomor
 * EPID..." untuk pola serupa (mengunci data yang sudah dipublikasikan).
 */
class TimbangDashboardTerkunciTest extends TestCase
{
    use RefreshDatabase;

    private function anakOt(string $nik, float $zTbU = -2.5, array $o = []): Anak
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

    /** Baseline: data OT terkunci tetap tampil normal (kunci bukan berarti kosong). */
    public function test_data_ot_terkunci_tetap_dihitung(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->anakOt('3201000000005001', -2.5); // stunting

        $ring = $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->assertOk()->json();
        $gizi = $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->assertOk()->json();

        $this->assertSame(1, $ring['total_anak']);
        $this->assertSame(1, $ring['total_ditimbang']);
        $this->assertSame(1, $gizi['stunting']);
    }

    /** Anak baru + pengukuran manual (default sumber) tidak boleh mengubah angka dashboard. */
    public function test_anak_dan_pengukuran_manual_tidak_mengubah_dashboard(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->anakOt('3201000000005101', -2.5);

        $sebelum = $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->assertOk()->json();
        $giziSebelum = $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->assertOk()->json();

        // Anak baru ditambah admin lewat jalur manual (sumber default = 'manual'),
        // sengaja diberi z-score EKSTREM — kalau bocor ikut dihitung, angka pasti berubah.
        $anakManual = Anak::create([
            'nama' => 'Anak Manual', 'nik' => '3201000000005102', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1,
        ]);
        DataAnak::create([
            'id_anak' => $anakManual->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 90, 'bb' => 12, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
            'zscore_bb_u' => -4.0, 'zscore_pb_u' => -4.0, 'zscore_bb_pb' => -4.0,
        ]);

        $sesudah = $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->assertOk()->json();
        $giziSesudah = $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->assertOk()->json();

        $this->assertSame($sebelum, $sesudah);
        $this->assertSame($giziSebelum, $giziSesudah);
    }

    /** Pengukuran baru untuk anak OT lama (mis. kunjungan posyandu berikutnya) tidak ikut kartu gizi. */
    public function test_pengukuran_baru_untuk_anak_ot_lama_tidak_ikut_dashboard(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $anak = $this->anakOt('3201000000005201', -2.5); // stunting, terkunci

        $giziSebelum = $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->assertOk()->json();

        // Kunjungan posyandu berikutnya, dicatat manual, z-score normal —
        // kalau bocor jadi kunjungan TERAKHIR, kartu gizi berubah dari stunting ke normal.
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 25,
            'posisi' => 'berdiri', 'tb' => 95, 'bb' => 13, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
            'zscore_bb_u' => 0.0, 'zscore_pb_u' => 0.0, 'zscore_bb_pb' => 0.0,
        ]);

        $giziSesudah = $this->actingAs($super)->getJson(route('admin.timbang.gizi'))->assertOk()->json();

        $this->assertSame($giziSebelum, $giziSesudah);
    }

    /** Baris placeholder dari import imunisasi (bb=0/tb=0) tidak ikut total kunjungan/tren. */
    public function test_baris_placeholder_imunisasi_tidak_ikut_kunjungan(): void
    {
        $super = User::factory()->create(['type' => 0]);
        $this->anakOt('3201000000005301', -1.0);

        $ringSebelum = $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->assertOk()->json();

        $anakImunisasi = Anak::create([
            'nama' => 'Anak Imunisasi', 'nik' => '3201000000005302', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => now()->subMonths(24)->toDateString(),
            'status' => 1,
        ]);
        DataAnak::create([
            'id_anak' => $anakImunisasi->id, 'tgl_kunjungan' => now()->toDateString(), 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 0, 'bb' => 0, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
            'alasan_tidak_imunisasi' => 'Sakit', 'sumber' => 'imunisasi',
        ]);

        $ringSesudah = $this->actingAs($super)->getJson(route('admin.timbang.ringkasan'))->assertOk()->json();

        $this->assertSame($ringSebelum['total_kunjungan'], $ringSesudah['total_kunjungan']);
        $this->assertSame($ringSebelum['total_anak'], $ringSesudah['total_anak']);
    }
}
