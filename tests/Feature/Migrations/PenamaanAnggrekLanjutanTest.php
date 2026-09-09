<?php

namespace Tests\Feature\Migrations;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lanjutan koreksi "Anggrek" — menutup kasus yang tertinggal di produksi.
 *
 * Migration 2026_09_09_000001 menamai ulang baris "Anggrek" ber-id LEBIH BESAR
 * jadi "Anggrek1", dan menolak menyentuh baris yang sudah dipakai anak. Di
 * produksi kedua aturan itu bertabrakan: matcher LAMA memakai
 * `pluck('id','name')`, yang untuk nama kembar hanya menyisakan id TERAKHIR —
 * jadi seluruh anak "ANGGREK" menumpuk di baris ber-id besar, justru baris yang
 * hendak dinamai ulang. Penjaganya bekerja, penamaannya batal, dan "Anggrek1"
 * tak pernah lahir.
 *
 * Akibatnya 178 baris berkas Juni 2026 tetap menggantung meski keputusan Dinkes
 * sudah ada: 91 baris menyebut "Anggrek1" (tak ada di data induk) dan 87 baris
 * menyebut "Anggrek" (ada dua, jadi ambigu).
 *
 * Yang keliru bukan penjaganya, melainkan pilihan barisnya. Nama mana menempel
 * ke baris mana memang sembarang — data induk tidak menyimpan kelurahan, jadi
 * kedua baris tak punya identitas sendiri. Maka: namai ulang baris yang AMAN
 * disentuh, bukan baris yang kebetulan ber-id lebih besar.
 */
class PenamaanAnggrekLanjutanTest extends TestCase
{
    use RefreshDatabase;

    private Puskesmas $barat;

    private function migration()
    {
        return require database_path('migrations/2026_09_09_000002_selesaikan_penamaan_anggrek_bontang_barat.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $kec = Kecamatan::create(['name' => 'Bontang Barat']);
        $this->barat = Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $kec->id]);
    }

    /** @return array{0:Posyandu,1:Posyandu} */
    private function duaAnggrek(): array
    {
        return [
            Posyandu::create(['name' => 'Anggrek', 'id_puskesmas' => $this->barat->id]),
            Posyandu::create(['name' => 'Anggrek', 'id_puskesmas' => $this->barat->id]),
        ];
    }

    private function anak(int $idPosyandu, string $sumber = 'operasi_timbang'): Anak
    {
        static $n = 0;
        $n++;

        return Anak::create([
            'nik' => str_pad((string) $n, 16, '6', STR_PAD_LEFT),
            'nama' => 'ANAK ' . $n, 'jk' => 2, 'tgl_lahir' => '2025-09-12',
            'nama_ibu' => 'SITI', 'sumber' => $sumber, 'status' => 1,
            'no' => 'OT-' . $n, 'id_posyandu' => $idPosyandu,
        ]);
    }

    public function test_barisnya_dipilih_karena_aman_disentuh_bukan_karena_id_lebih_besar(): void
    {
        // Kondisi produksi: anak menumpuk di baris ber-id besar.
        [$kecil, $besar] = $this->duaAnggrek();
        $this->anak($besar->id);

        $this->migration()->up();

        $this->assertSame('Anggrek1', $kecil->fresh()->name);
        $this->assertSame('Anggrek', $besar->fresh()->name);
    }

    public function test_anak_tidak_ikut_berpindah_saat_barisnya_dinamai_ulang(): void
    {
        [$kecil, $besar] = $this->duaAnggrek();
        $anak = $this->anak($besar->id);

        $this->migration()->up();

        // Nama boleh berubah; kepemilikan anak tidak.
        $this->assertSame($besar->id, (int) $anak->fresh()->id_posyandu);
        $this->assertSame(0, Anak::where('id_posyandu', $kecil->id)->count());
    }

    public function test_kalau_keduanya_kosong_yang_ber_id_besar_yang_dinamai_ulang(): void
    {
        // Perilaku migration sebelumnya dipertahankan untuk pemasangan baru.
        [$kecil, $besar] = $this->duaAnggrek();

        $this->migration()->up();

        $this->assertSame('Anggrek', $kecil->fresh()->name);
        $this->assertSame('Anggrek1', $besar->fresh()->name);
    }

    public function test_tidak_mengubah_apa_pun_kalau_anggrek1_sudah_ada(): void
    {
        $anggrek  = Posyandu::create(['name' => 'Anggrek', 'id_puskesmas' => $this->barat->id]);
        $anggrek1 = Posyandu::create(['name' => 'Anggrek1', 'id_puskesmas' => $this->barat->id]);

        $this->migration()->up();

        $this->assertSame('Anggrek', $anggrek->fresh()->name);
        $this->assertSame('Anggrek1', $anggrek1->fresh()->name);
    }

    public function test_keduanya_dipakai_anak_operasi_timbang_tetap_dinamai_ulang(): void
    {
        // Anak sumber Operasi Timbang akan disortir ulang oleh
        // `posyandu:backfill-ot` tepat setelah ini, jadi tak ada yang tertinggal
        // di posyandu yang keliru. Yang ber-id besar dipilih agar sesuai
        // keputusan awal Dinkes.
        [$kecil, $besar] = $this->duaAnggrek();
        $this->anak($kecil->id);
        $this->anak($besar->id);

        $this->migration()->up();

        $this->assertSame('Anggrek', $kecil->fresh()->name);
        $this->assertSame('Anggrek1', $besar->fresh()->name);
    }

    public function test_baris_berisi_anak_di_luar_operasi_timbang_tidak_disentuh(): void
    {
        // Anak Capil tidak ikut disortir backfill — memindahkannya berarti
        // kerusakan senyap, persis yang sedang diperbaiki.
        [$kecil, $besar] = $this->duaAnggrek();
        $this->anak($kecil->id, 'capil');
        $this->anak($besar->id, 'capil');

        $this->migration()->up();

        $this->assertSame('Anggrek', $kecil->fresh()->name);
        $this->assertSame('Anggrek', $besar->fresh()->name);
    }
}
