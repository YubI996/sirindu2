<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Services\CapilDedupService;
use App\Services\IdentitasMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentitasMatcherTest extends TestCase
{
    use RefreshDatabase;

    private IdentitasMatcher $m;

    protected function setUp(): void
    {
        parent::setUp();
        $this->m = new IdentitasMatcher();
    }

    private function anak(array $o): Anak
    {
        static $n = 0;
        $n++;
        return Anak::create(array_merge([
            'nama' => 'Anak', 'nik' => '3201000000010'.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-01-01', 'status' => 1, 'sumber' => 'capil',
            'nama_ibu' => 'Siti Aminah', 'nama_ayah' => 'Budi Santoso', 'no_kk' => null,
        ], $o));
    }

    public function test_konstanta_sama_dengan_capil_dedup(): void
    {
        $this->assertSame(CapilDedupService::CHILD_MIN, IdentitasMatcher::CHILD_MIN);
        $this->assertSame(CapilDedupService::CHILD_NEAR_MIN, IdentitasMatcher::CHILD_NEAR_MIN);
        $this->assertSame(CapilDedupService::PARENT_MIN, IdentitasMatcher::PARENT_MIN);
        $this->assertSame(CapilDedupService::DATE_TOLERANCE_DAYS, IdentitasMatcher::DATE_TOLERANCE_DAYS);
        $this->assertSame(CapilDedupService::CHILD_STRONG, IdentitasMatcher::CHILD_STRONG);
        $this->assertSame(CapilDedupService::PARENT_STRONG, IdentitasMatcher::PARENT_STRONG);
    }

    public function test_tgl_tepat_nama_70_dan_kk_sama_via_kk(): void
    {
        $a = $this->anak(['nama' => 'Muhammad Rizky', 'no_kk' => '6474010101010001', 'sumber' => 'operasi_timbang']);
        $b = $this->anak(['nama' => 'Muhamad Rizki', 'no_kk' => '6474010101010001', 'nama_ibu' => 'Orang Lain', 'nama_ayah' => 'Lain']);

        $r = $this->m->evaluate($a, $b);
        $this->assertNotNull($r);
        $this->assertSame('kk', $r['via']);
    }

    public function test_tgl_meleset_satu_hari_butuh_nama_90_via_ortu(): void
    {
        $a = $this->anak(['nama' => 'Aisyah Putri', 'tgl_lahir' => '2024-01-01']);
        $b = $this->anak(['nama' => 'Aisyah Putri', 'tgl_lahir' => '2024-01-02']);
        $c = $this->anak(['nama' => 'Aisyah Putry Ramadhani', 'tgl_lahir' => '2024-01-02']);

        $this->assertSame('ortu', $this->m->evaluate($a, $b)['via']);
        $this->assertNull($this->m->evaluate($a, $c), 'nama < 90% saat tanggal meleset → bukan kandidat');
    }

    public function test_nama_dan_ortu_sangat_mirip_abaikan_tanggal_via_nama_kuat(): void
    {
        $a = $this->anak(['nama' => 'Kevin Pratama', 'tgl_lahir' => '2024-01-01']);
        $b = $this->anak(['nama' => 'Kevin Pratama', 'tgl_lahir' => '2023-01-01']);

        $r = $this->m->evaluate($a, $b);
        $this->assertNotNull($r);
        $this->assertSame('nama_kuat', $r['via']);
    }

    public function test_tanpa_kk_dan_ortu_beda_bukan_kandidat(): void
    {
        $a = $this->anak(['nama' => 'Dewi Lestari']);
        $b = $this->anak(['nama' => 'Dewi Lestari', 'nama_ibu' => 'Rina', 'nama_ayah' => 'Joko']);

        $this->assertNull($this->m->evaluate($a, $b));
    }

    public function test_pindai_semua_mengembalikan_pasangan_unik_terurut_dan_mengecualikan_ot_ot(): void
    {
        $ot1 = $this->anak(['nama' => 'Farhan Akbar', 'sumber' => 'operasi_timbang']);
        $ot2 = $this->anak(['nama' => 'Farhan Akbar', 'sumber' => 'operasi_timbang']);
        $cap = $this->anak(['nama' => 'Farhan Akbar', 'sumber' => 'capil']);
        $this->anak(['nama' => 'Zulaikha', 'nama_ibu' => 'X', 'nama_ayah' => 'Y']);

        $pairs = $this->m->pindaiSemua(Anak::all());
        $set = array_map(fn ($p) => [$p['a'], $p['b']], $pairs);
        sort($set);

        $this->assertSame([[$ot1->id, $cap->id], [$ot2->id, $cap->id]], $set, 'OT×Capil masuk, OT×OT tidak');
        foreach ($pairs as $p) {
            $this->assertLessThan($p['b'], $p['a']);
            $this->assertArrayHasKey('score', $p);
        }
    }

    public function test_capil_dedup_masih_memakai_aturan_yang_sama(): void
    {
        $svc = new CapilDedupService();
        $a = $this->anak(['nama' => 'Nadia Salsabila', 'no_kk' => '6474000000000009']);
        $b = $this->anak(['nama' => 'Nadia Salsabilla', 'no_kk' => '6474000000000009']);

        $this->assertSame($this->m->evaluate($a, $b), $svc->evaluate($a, $b));
        $this->assertSame($this->m->nameSim('abc', 'abd'), $svc->nameSim('abc', 'abd'));
    }
}
