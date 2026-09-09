<?php

namespace Tests\Feature\Migrations;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Koreksi data induk hasil verifikasi Dinkes (September 2026):
 *
 *  1. Dua baris bernama "Anggrek" di Puskesmas Bontang Barat tak bisa dibedakan
 *     sistem, sehingga 178 anak menggantung. Dinkes menetapkan: "Anggrek" untuk
 *     Kelurahan Belimbing, "Anggrek1" untuk Gunung Telihan (mengikuti penulisan
 *     di berkas e-PPGBM). Salah satu baris dinamai ulang jadi "Anggrek1".
 *  2. "Flamboyan 2" tercatat di Bontang Utara 1; Dinkes menegaskan seharusnya
 *     Bontang Utara 2.
 *
 * Keduanya hanya aman selama baris yang disentuh belum dipakai anak mana pun —
 * kalau sudah, penamaan ulang berarti memindahkan anak tanpa sepengetahuan
 * petugas. Migration ini menolak melakukannya dan membiarkan datanya apa adanya.
 */
class KoreksiMasterPosyanduDinkesTest extends TestCase
{
    use RefreshDatabase;

    private Puskesmas $barat;
    private Puskesmas $utara1;
    private Puskesmas $utara2;

    private function migration()
    {
        return require database_path('migrations/2026_09_09_000001_koreksi_master_posyandu_hasil_verifikasi_dinkes.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $kec = Kecamatan::create(['name' => 'Bontang Barat']);
        $this->barat  = Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $kec->id]);
        $this->utara1 = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
        $this->utara2 = Puskesmas::create(['name' => 'Bontang Utara 2', 'id_kecamatan' => $kec->id]);
    }

    private function duaAnggrek(): array
    {
        return [
            Posyandu::create(['name' => 'Anggrek', 'id_puskesmas' => $this->barat->id]),
            Posyandu::create(['name' => 'Anggrek', 'id_puskesmas' => $this->barat->id]),
        ];
    }

    public function test_satu_anggrek_dinamai_ulang_jadi_anggrek1(): void
    {
        [$a, $b] = $this->duaAnggrek();

        $this->migration()->up();

        // id terkecil tetap "Anggrek" (Belimbing), yang lebih besar jadi "Anggrek1".
        $this->assertSame('Anggrek', Posyandu::find($a->id)->name);
        $this->assertSame('Anggrek1', Posyandu::find($b->id)->name);
    }

    public function test_setelah_dikoreksi_matcher_bisa_membedakan_keduanya(): void
    {
        $this->duaAnggrek();
        $this->migration()->up();

        $m = new \App\Services\FaskesMatcher();

        $this->assertNotNull($m->cocokkan('ANGGREK', 'BONTANG BARAT')['id']);
        $this->assertNotNull($m->cocokkan('ANGGREK1', 'BONTANG BARAT')['id']);
        $this->assertNotSame(
            $m->cocokkan('ANGGREK', 'BONTANG BARAT')['id'],
            $m->cocokkan('ANGGREK1', 'BONTANG BARAT')['id']
        );
    }

    public function test_flamboyan_2_dipindah_ke_bontang_utara_2(): void
    {
        $f = Posyandu::create(['name' => 'Flamboyan 2', 'id_puskesmas' => $this->utara1->id]);

        $this->migration()->up();

        $this->assertSame($this->utara2->id, (int) Posyandu::find($f->id)->id_puskesmas);
    }

    public function test_anggrek_yang_sudah_dipakai_anak_tidak_dinamai_ulang(): void
    {
        [$a, $b] = $this->duaAnggrek();
        Anak::create([
            'nik' => '6474025209250099', 'nama' => 'ANAK ANGGREK', 'jk' => 1,
            'tgl_lahir' => '2025-01-01', 'sumber' => 'operasi_timbang',
            'status' => 1, 'no' => 'OT-00099', 'id_posyandu' => $b->id,
        ]);

        $this->migration()->up();

        $this->assertSame('Anggrek', Posyandu::find($a->id)->name);
        $this->assertSame('Anggrek', Posyandu::find($b->id)->name, 'Baris yang sudah dipakai anak tidak boleh dinamai ulang.');
    }

    public function test_dijalankan_dua_kali_tidak_merusak(): void
    {
        $this->duaAnggrek();
        $f = Posyandu::create(['name' => 'Flamboyan 2', 'id_puskesmas' => $this->utara1->id]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame(1, Posyandu::where('name', 'Anggrek')->count());
        $this->assertSame(1, Posyandu::where('name', 'Anggrek1')->count());
        $this->assertSame($this->utara2->id, (int) Posyandu::find($f->id)->id_puskesmas);
    }

    public function test_down_mengembalikan_keadaan_semula(): void
    {
        $this->duaAnggrek();
        $f = Posyandu::create(['name' => 'Flamboyan 2', 'id_puskesmas' => $this->utara1->id]);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame(2, Posyandu::where('name', 'Anggrek')->count());
        $this->assertSame(0, Posyandu::where('name', 'Anggrek1')->count());
        $this->assertSame($this->utara1->id, (int) Posyandu::find($f->id)->id_puskesmas);
    }
}
