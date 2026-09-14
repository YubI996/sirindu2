<?php

namespace Tests\Feature\VerifikasiRt;

use App\Models\Anak;
use App\Models\AnakKandidat;
use App\Models\AnakMergeLog;
use App\Models\AnakTautan;
use App\Models\DataAnak;
use App\Models\IntervensiGizi;
use App\Models\Rt;
use App\Models\User;
use App\Models\VerifikasiAnak;
use App\Services\IdentitasMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeGabungTest extends TestCase
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

    private function ukur(Anak $a, float $tb = 80): DataAnak
    {
        return DataAnak::create(['id_anak' => $a->id, 'tgl_kunjungan' => '2026-01-10', 'bln' => 24, 'posisi' => 'berdiri',
            'tb' => $tb, 'bb' => 10, 'lla' => 0, 'lk' => 0, 'id_user' => 1, 'zscore_bb_u' => 0, 'zscore_pb_u' => -2.5, 'zscore_bb_pb' => 0,
            'sumber' => $a->sumber === 'operasi_timbang' ? 'operasi_timbang' : 'manual']);
    }

    private function imunisasi(Anak $a): int
    {
        $jenis = DB::table('jenis_vaksin')->insertGetId(['kode' => 'TST-'.$a->id, 'nama' => 'Vaksin Tes', 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('imunisasi')->insertGetId(['id_anak' => $a->id, 'id_jenis_vaksin' => $jenis, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function tautanSetuju(Anak $x, Anak $y): AnakTautan
    {
        [$a, $b] = AnakTautan::urut($x->id, $y->id);
        return AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => 'sama', 'status' => 'disetujui',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
    }

    public function test_gabung_memindahkan_semua_data_anak_sebelum_menghapus(): void
    {
        $ot  = $this->anak('3201000000018001', ['sumber' => 'operasi_timbang', 'alamat' => 'Jl. OT', 'pj_nama' => null]);
        $cap = $this->anak('3201000000018002', ['nama' => 'Nama Capil', 'no_kk' => '6474000000000018', 'alamat_ktp' => 'Jl. KTP', 'pj_nama' => 'Kader X']);
        $u1 = $this->ukur($ot);
        $u2 = $this->ukur($cap, 81);
        $imId = $this->imunisasi($cap);
        $iv = IntervensiGizi::create(['id_anak' => $cap->id, 'jenis' => IntervensiGizi::JENIS[0], 'status' => 'Direncanakan']);
        $rt = Rt::factory()->create();
        $va = VerifikasiAnak::create(['id_anak' => $cap->id, 'id_rt' => $rt->id, 'status' => 'berdomisili', 'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
        DB::table('prioritas_gizi')->updateOrInsert(['id_anak' => $cap->id], ['stunting' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $t = $this->tautanSetuju($ot, $cap);
        $sisiCap = $ot->id < $cap->id ? 'b' : 'a';
        $sisiOt  = $sisiCap === 'a' ? 'b' : 'a';

        // Default identitas dari Capil; NIK sengaja dipilih dari OT untuk menguji pilihan eksplisit.
        $log = $this->svc->gabung($t, $this->super, ['nama' => $sisiCap, 'no_kk' => $sisiCap, 'alamat_ktp' => $sisiCap, 'nik' => $sisiOt]);

        $this->assertNull(Anak::find($cap->id));
        $keep = $ot->fresh();
        $this->assertSame('Nama Capil', $keep->nama);
        $this->assertSame('6474000000000018', $keep->no_kk);
        $this->assertSame('3201000000018001', $keep->nik, 'nik dipilih eksplisit dari OT');
        $this->assertSame('Jl. KTP', $keep->alamat_ktp);
        $this->assertSame('Jl. OT', $keep->alamat, 'domisili default dari non-capil');
        $this->assertSame('operasi_timbang', $keep->sumber, 'sumber OT tidak pernah berubah');
        $this->assertSame(['operasi_timbang', 'capil'], $keep->sumber_gabungan);
        $this->assertSame('Kader X', $keep->pj_nama, 'PJ diisi dari baris yang dihapus karena kosong');
        $this->assertSame(2, DataAnak::where('id_anak', $keep->id)->count());
        $this->assertSame($keep->id, (int) DB::table('imunisasi')->where('id', $imId)->value('id_anak'));
        $this->assertSame($keep->id, (int) $iv->fresh()->id_anak);
        $this->assertSame($keep->id, (int) $va->fresh()->id_anak);
        $this->assertSame(1, DB::table('prioritas_gizi')->where('id_anak', $keep->id)->count());
        $this->assertSame(0, DB::table('prioritas_gizi')->where('id_anak', $cap->id)->count());
        $this->assertSame('digabung', $t->fresh()->status);
        $this->assertSame($keep->id, (int) $log->id_dipertahankan);
        $this->assertSame([$u2->id], $log->snapshot['dipindah']['data_anak']);
        $this->assertSame('3201000000018002', $log->snapshot['anak_dihapus']['nik']);
    }

    public function test_dua_ot_ditolak_dan_tak_ada_yang_berubah(): void
    {
        $x = $this->anak('3201000000018003', ['sumber' => 'operasi_timbang']);
        $y = $this->anak('3201000000018004', ['sumber' => 'operasi_timbang']);
        $t = $this->tautanSetuju($x, $y);

        try {
            $this->svc->gabung($t, $this->super, []);
            $this->fail('harus ditolak');
        } catch (\InvalidArgumentException) {
        }
        $this->assertSame(2, Anak::whereIn('id', [$x->id, $y->id])->count());
        $this->assertSame(0, AnakMergeLog::count());
    }

    public function test_tautan_lain_yang_memuat_baris_terhapus_ditolak_otomatis(): void
    {
        $ot  = $this->anak('3201000000018005', ['sumber' => 'operasi_timbang']);
        $cap = $this->anak('3201000000018006');
        $lain = $this->anak('3201000000018007');
        $t = $this->tautanSetuju($ot, $cap);
        [$p, $q] = AnakTautan::urut($cap->id, $lain->id);
        $tLain = AnakTautan::create(['id_anak_a' => $p, 'id_anak_b' => $q, 'keputusan' => 'sama', 'status' => 'diusulkan',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);
        AnakKandidat::create(['id_anak_a' => $p, 'id_anak_b' => $q, 'skor' => 1, 'via' => 'ortu', 'child_sim' => 1, 'parent_sim' => 1, 'dipindai_at' => now()]);

        $this->svc->gabung($t, $this->super, []);

        $this->assertSame('ditolak', $tLain->fresh()->status);
        $this->assertStringContainsString('digabung', $tLain->fresh()->catatan_reviu);
        $this->assertSame(0, AnakKandidat::count(), 'kandidat ikut cascade');
    }

    public function test_pilihan_baris_dihormati_hanya_bila_tanpa_ot(): void
    {
        $man = $this->anak('3201000000018008', ['sumber' => 'manual']);
        $cap = $this->anak('3201000000018009');
        $t = $this->tautanSetuju($man, $cap);

        $this->svc->gabung($t, $this->super, [], 'b'); // pertahankan capil

        $this->assertNull(Anak::find($man->id));
        $this->assertNotNull(Anak::find($cap->id));
        $this->assertSame(['capil', 'manual'], Anak::find($cap->id)->sumber_gabungan);
    }
}
