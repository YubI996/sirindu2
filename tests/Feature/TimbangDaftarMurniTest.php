<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkbox "murni" di modal daftar: underweight tanpa stunting & wasting,
 * wasting tanpa stunting. Baris membawa flag stunting/wasting (filter klien),
 * param murni=1 menyaring di server (dipakai export).
 */
class TimbangDaftarMurniTest extends TestCase
{
    use RefreshDatabase;

    private function anak(string $nama, float $zBbU, float $zTbU, float $zBbTb): void
    {
        $a = Anak::create([
            'nama' => $nama, 'nik' => '32010000000' . random_int(10000, 99999), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-06-01', 'status' => 1, 'sumber' => 'operasi_timbang',
        ]);
        DataAnak::create([
            'id_anak' => $a->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 24,
            'posisi' => 'berdiri', 'tb' => 85, 'bb' => 9, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'sumber' => 'operasi_timbang',
            'zscore_bb_u' => $zBbU, 'zscore_pb_u' => $zTbU, 'zscore_bb_pb' => $zBbTb,
        ]);
    }

    public function test_murni_menyaring_underweight_dan_wasting(): void
    {
        $this->anak('UW murni',      -2.5, -1.0, -1.0);
        $this->anak('UW + stunting', -2.5, -2.5, -1.0);
        $this->anak('UW + wasting',  -2.5, -1.0, -2.5);
        $this->anak('W murni',       -1.0, -1.0, -2.5);
        $user = User::factory()->create(['type' => 0]);

        $semua = $this->actingAs($user)->getJson(route('admin.timbang.daftar', ['kategori' => 'underweight']))->json('rows');
        $this->assertCount(3, $semua);
        $byNama = collect($semua)->keyBy('nama');
        $this->assertFalse($byNama['UW murni']['stunting']);
        $this->assertFalse($byNama['UW murni']['wasting']);
        $this->assertTrue($byNama['UW + stunting']['stunting']);
        $this->assertTrue($byNama['UW + wasting']['wasting']);

        $murni = $this->actingAs($user)->getJson(route('admin.timbang.daftar', ['kategori' => 'underweight', 'murni' => 1]))->json('rows');
        $this->assertSame(['UW murni'], array_column($murni, 'nama'));

        $wasting = $this->actingAs($user)->getJson(route('admin.timbang.daftar', ['kategori' => 'wasting', 'murni' => 1]))->json('rows');
        $this->assertEqualsCanonicalizing(['UW + wasting', 'W murni'], array_column($wasting, 'nama'));

        // Stunting tidak terpengaruh param murni.
        $st = $this->actingAs($user)->getJson(route('admin.timbang.daftar', ['kategori' => 'stunting', 'murni' => 1]))->json('rows');
        $this->assertSame(['UW + stunting'], array_column($st, 'nama'));
    }
}
