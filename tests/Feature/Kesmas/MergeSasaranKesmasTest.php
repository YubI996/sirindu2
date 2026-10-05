<?php

namespace Tests\Feature\Kesmas;

use App\Models\Anak;
use App\Models\AnakTautan;
use App\Models\SasaranKesmasLog;
use App\Models\User;
use App\Services\IdentitasMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penggabungan dua baris anak (Verifikasi RT) tidak boleh menghilangkan tanda Sasaran Balita Kesmas
 * dan tanggal HBIG milik baris yang dilebur. Aturannya "isi bila kosong" — pola yang sama dengan PJ:
 * keputusan yang sudah ada di baris yang dipertahankan (termasuk 0 = sengaja dilepas) tidak ditimpa.
 */
class MergeSasaranKesmasTest extends TestCase
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

    /** Baris OT (dipertahankan, terkunci) + baris Capil (dilebur). */
    private function gabung(array $keepOpsi, array $dropOpsi, string $nikKeep, string $nikDrop): array
    {
        $keep = $this->anak($nikKeep, $keepOpsi + ['sumber' => 'operasi_timbang']);
        $drop = $this->anak($nikDrop, $dropOpsi);
        [$a, $b] = AnakTautan::urut($keep->id, $drop->id);
        $t = AnakTautan::create(['id_anak_a' => $a, 'id_anak_b' => $b, 'keputusan' => 'sama', 'status' => 'disetujui',
            'diusulkan_oleh' => $this->super->id, 'diusulkan_at' => now()]);

        return [$keep, $drop, $this->svc->gabung($t, $this->super, [])];
    }

    public function test_tanda_dan_hbig_dari_baris_dilebur_disalin_ke_baris_kosong(): void
    {
        [$keep, , ] = $this->gabung([], ['sasaran_balita_kesmas' => 1, 'tgl_hbig' => '2024-01-02'], '3201000000021001', '3201000000021002');

        $keep = $keep->fresh();
        $this->assertSame(1, (int) $keep->sasaran_balita_kesmas);
        $this->assertSame('2024-01-02', (string) $keep->tgl_hbig);

        $log = SasaranKesmasLog::where('id_anak', $keep->id)->sole();
        $this->assertNull($log->nilai_lama);
        $this->assertSame(1, (int) $log->nilai_baru);
        $this->assertSame('gabung', $log->sumber);
        $this->assertSame($this->super->id, (int) $log->id_user);
    }

    public function test_keputusan_di_baris_dipertahankan_tidak_ditimpa(): void
    {
        // 0 = petugas sengaja melepas; baris dilebur yang bertanda tidak boleh menghidupkannya lagi.
        [$keep, , ] = $this->gabung(
            ['sasaran_balita_kesmas' => 0, 'tgl_hbig' => '2024-01-05'],
            ['sasaran_balita_kesmas' => 1, 'tgl_hbig' => '2024-01-02'],
            '3201000000021003', '3201000000021004'
        );

        $keep = $keep->fresh();
        $this->assertSame(0, (int) $keep->sasaran_balita_kesmas);
        $this->assertSame('2024-01-05', (string) $keep->tgl_hbig);
        $this->assertSame(0, SasaranKesmasLog::where('id_anak', $keep->id)->count(), 'tak ada perubahan → tak ada jejak');
    }

    public function test_baris_dilebur_yang_kosong_tidak_mengubah_apa_pun(): void
    {
        [$keep, , ] = $this->gabung(['sasaran_balita_kesmas' => 1], [], '3201000000021005', '3201000000021006');

        $this->assertSame(1, (int) $keep->fresh()->sasaran_balita_kesmas);
        $this->assertNull($keep->fresh()->tgl_hbig);
        $this->assertSame(0, SasaranKesmasLog::where('id_anak', $keep->id)->count());
    }

    public function test_keduanya_kosong_tetap_null_bukan_nol(): void
    {
        [$keep, , ] = $this->gabung([], [], '3201000000021007', '3201000000021008');

        $this->assertNull($keep->fresh()->sasaran_balita_kesmas);
        $this->assertSame(0, SasaranKesmasLog::count());
    }

    public function test_batalkan_merge_memulihkan_kedua_baris_apa_adanya(): void
    {
        [$keep, $drop, $log] = $this->gabung([], ['sasaran_balita_kesmas' => 1, 'tgl_hbig' => '2024-01-02'], '3201000000021009', '3201000000021010');
        $this->assertSame(1, (int) $keep->fresh()->sasaran_balita_kesmas);

        $pulih = $this->svc->batalkan($log, $this->super);

        $this->assertNull($keep->fresh()->sasaran_balita_kesmas, 'baris dipertahankan kembali ke NULL');
        $this->assertNull($keep->fresh()->tgl_hbig);
        $this->assertSame($drop->id, $pulih->id);
        $this->assertSame(1, (int) $pulih->sasaran_balita_kesmas, 'baris yang dilebur kembali bertanda');
        $this->assertSame('2024-01-02', (string) $pulih->tgl_hbig);

        $terakhir = SasaranKesmasLog::where('id_anak', $keep->id)->orderByDesc('id')->first();
        $this->assertSame('batal', $terakhir->sumber, 'pembatalan meninggalkan jejak, log tetap sinkron dengan tabel');
        $this->assertSame(1, (int) $terakhir->nilai_lama);
        $this->assertNull($terakhir->nilai_baru);
    }
}
