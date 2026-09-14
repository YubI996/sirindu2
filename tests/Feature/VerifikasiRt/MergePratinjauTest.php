<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakTautan;
use App\Models\User;
use App\Services\IdentitasMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergePratinjauTest extends TestCase
{
    use RefreshDatabase;

    private IdentitasMergeService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(IdentitasMergeService::class);
    }

    private function anak(string $nik, array $o = []): Anak
    {
        return Anak::create(array_merge(['nama' => 'Anak '.$nik, 'nik' => $nik, 'jk' => 1, 'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil'], $o));
    }

    private function tautan(Anak $x, Anak $y, string $status = 'disetujui', string $keputusan = 'sama'): AnakTautan
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        return AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => $keputusan, 'status' => $status,
            'diusulkan_oleh' => User::factory()->create()->id, 'diusulkan_at' => now()]);
    }

    public function test_baris_ot_selalu_dipertahankan_dan_terkunci(): void
    {
        $cap = $this->anak('3201000000017001', ['alamat_ktp' => 'KTP Capil']);
        $ot  = $this->anak('3201000000017002', ['sumber' => 'operasi_timbang', 'alamat' => 'Domisili OT']);
        $p = $this->svc->pratinjau($this->tautan($cap, $ot));

        $this->assertSame('b', $p['dipertahankan']); // ot punya id lebih besar → b
        $this->assertTrue($p['kunci_dipertahankan']);
        $this->assertSame('a', $p['default']['nik'], 'identitas dari capil');
        $this->assertSame('a', $p['default']['alamat_ktp']);
        $this->assertSame('b', $p['default']['alamat'], 'domisili dari non-capil');
        $this->assertSame('b', $p['default']['golda'], 'sisanya dari yang dipertahankan');
    }

    public function test_dua_ot_ditolak(): void
    {
        $x = $this->anak('3201000000017003', ['sumber' => 'operasi_timbang']);
        $y = $this->anak('3201000000017004', ['sumber' => 'operasi_timbang']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Operasi Timbang');
        $this->svc->pratinjau($this->tautan($x, $y));
    }

    public function test_tanpa_ot_dinkes_boleh_memilih_default_non_capil(): void
    {
        $man = $this->anak('3201000000017005', ['sumber' => 'manual']);
        $cap = $this->anak('3201000000017006');
        $p = $this->svc->pratinjau($this->tautan($man, $cap));

        $this->assertSame('a', $p['dipertahankan']);
        $this->assertFalse($p['kunci_dipertahankan']);
        $this->assertTrue($p['boleh_pilih_baris']);
    }

    public function test_hanya_tautan_sama_yang_disetujui(): void
    {
        $x = $this->anak('3201000000017007');
        $y = $this->anak('3201000000017008');

        $this->expectException(\InvalidArgumentException::class);
        $this->svc->pratinjau($this->tautan($x, $y, 'diusulkan'));
    }
}
