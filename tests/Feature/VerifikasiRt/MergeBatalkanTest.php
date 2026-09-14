<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakTautan;
use App\Models\DataAnak;
use App\Models\User;
use App\Services\IdentitasMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeBatalkanTest extends TestCase
{
    use RefreshDatabase;

    private IdentitasMergeService $svc;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc   = app(IdentitasMergeService::class);
        $this->super = User::factory()->create(['type' => 0]);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil', 'nama_ibu' => 'Ibu', 'nama_ayah' => 'Ayah'], $o));
    }

    private function ukur(Anak $a): DataAnak
    {
        return DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-01-10', 'bln' => 24, 'posisi' => 'berdiri',
            'tb' => 80, 'bb' => 10, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => 0, 'zscore_bb_pb' => 0, 'sumber' => 'manual']);
    }

    private function gabungOtCapil(string $nikOt, string $nikCap): array
    {
        $ot  = $this->anak($nikOt, ['sumber' => 'operasi_timbang', 'nama' => 'Nama OT']);
        $cap = $this->anak($nikCap, ['nama' => 'Nama Capil']);
        $u = $this->ukur($cap);
        [$a, $b] = AnakTautan::urut($ot->id, $cap->id);
        $t = AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => 'sama', 'status' => 'disetujui',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
        $log = $this->svc->gabung($t, $this->super, ['nama' => $ot->id < $cap->id ? 'b' : 'a']);
        return [$ot, $cap, $u, $t, $log];
    }

    public function test_batalkan_memulihkan_persis(): void
    {
        [$ot, $cap, $u, $t, $log] = $this->gabungOtCapil('3201000000019001', '3201000000019002');
        $this->assertSame('Nama Capil', $ot->fresh()->nama);

        $pulih = $this->svc->batalkan($log, $this->super);

        $this->assertSame($cap->id, $pulih->id);
        $this->assertSame('3201000000019002', $pulih->nik);
        $this->assertSame('Nama OT', $ot->fresh()->nama, 'nilai lama dipulihkan');
        $this->assertSame('3201000000019001', $ot->fresh()->nik);
        $this->assertNull($ot->fresh()->sumber_gabungan);
        $this->assertSame($cap->id, (int) $u->fresh()->id_anak, 'pengukuran kembali ke pemilik asal');
        $this->assertSame('disetujui', $t->fresh()->status);
        $this->assertNotNull($log->fresh()->dibatalkan_at);
    }

    public function test_batalkan_dua_kali_ditolak(): void
    {
        [, , , , $log] = $this->gabungOtCapil('3201000000019003', '3201000000019004');
        $this->svc->batalkan($log, $this->super);

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->batalkan($log->fresh(), $this->super);
    }

    public function test_batalkan_ditolak_bila_ada_pengukuran_baru_yang_tak_bisa_dipetakan(): void
    {
        [$ot, , , , $log] = $this->gabungOtCapil('3201000000019005', '3201000000019006');
        $this->ukur($ot->fresh()); // data baru setelah merge

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('baru');
        $this->svc->batalkan($log, $this->super);
    }
}
