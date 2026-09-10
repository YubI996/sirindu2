<?php

namespace Tests\Feature\Migrations;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dua salah tulis di data induk posyandu, ketahuan saat membandingkan daftar
 * berkas OT Juni 2026 dengan isi server.
 *
 * 1. `Cendana` terdaftar di Puskesmas Bontang Utara 1, padahal ke-43 anaknya
 *    ada di Kelurahan LOK TUAN — wilayah Bontang Utara 2. Dua baris lain di
 *    Lok Tuan (`Anggrek Putih`, dan `Flamboyan 2` sesudah koreksi Dinkes)
 *    sudah berada di Bontang Utara 2, jadi Cendana-lah yang meleset.
 *
 * 2. `Nisa Indah` di Bontang Utara 1 tak pernah menerima satu anak pun,
 *    sementara 31 anak berkas OT menulis "nusa indah" di puskesmas yang sama
 *    dan malah nyasar ke `Nusa Indah` milik Bontang Utara 2 (lewat tahap
 *    "global-unik" — nama identik dan waktu itu unik se-master). "Nisa" adalah
 *    salah ketik "Nusa".
 *
 * Keduanya diperbaiki di data induk, bukan lewat berkas keputusan, karena nama
 * dan puskesmas posyandu TAMPIL di layar petugas — membiarkannya salah berarti
 * membiarkan petugas membaca yang keliru walau anaknya sudah mendarat benar.
 *
 * Penjaganya berkaca pada pelajaran migration Anggrek: jangan sampai koreksi
 * ini justru MELAHIRKAN nama kembar dalam satu puskesmas, karena nama kembar
 * itulah yang bikin matcher menyerah dan datanya hilang dari dashboard.
 */
class KoreksiCendanaDanNisaIndahTest extends TestCase
{
    use RefreshDatabase;

    private Puskesmas $bu1;
    private Puskesmas $bu2;

    private function migration()
    {
        return require database_path('migrations/2026_09_10_000001_koreksi_cendana_dan_nisa_indah.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $this->bu1 = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
        $this->bu2 = Puskesmas::create(['name' => 'Bontang Utara 2', 'id_kecamatan' => $kec->id]);
    }

    private function anak(int $idPosyandu, int $idPuskesmas): Anak
    {
        return Anak::create([
            'nik' => '6474025209250001', 'nama' => 'FARAH NUR SEPTIANA PUTRI',
            'jk' => 2, 'tgl_lahir' => '2025-09-12', 'nama_ibu' => 'SITI AMINAH',
            'sumber' => 'operasi_timbang', 'status' => 1, 'no' => 'OT-00001',
            'id_posyandu' => $idPosyandu, 'id_puskesmas' => $idPuskesmas,
        ]);
    }

    public function test_cendana_dipindah_ke_bontang_utara_2(): void
    {
        $cendana = Posyandu::create(['name' => 'Cendana', 'id_puskesmas' => $this->bu1->id]);

        $this->migration()->up();

        $this->assertSame($this->bu2->id, (int) $cendana->fresh()->id_puskesmas);
    }

    public function test_anak_di_cendana_ikut_pindah_puskesmas(): void
    {
        // `anak` menyimpan id_puskesmas terpisah dari id_posyandu. Kalau
        // posyandunya pindah tapi anaknya tidak, anak itu tercatat di puskesmas
        // yang tak lagi memilikinya — rekap per puskesmas jadi salah diam-diam.
        $cendana = Posyandu::create(['name' => 'Cendana', 'id_puskesmas' => $this->bu1->id]);
        $anak    = $this->anak($cendana->id, $this->bu1->id);

        $this->migration()->up();

        $this->assertSame($this->bu2->id, (int) $anak->fresh()->id_puskesmas);
    }

    public function test_anak_yang_puskesmasnya_sudah_lain_tidak_diseret(): void
    {
        // Hanya membetulkan yang sebelumnya SELARAS. Baris yang sudah menyimpang
        // punya sebab sendiri dan bukan urusan migration ini.
        $cendana = Posyandu::create(['name' => 'Cendana', 'id_puskesmas' => $this->bu1->id]);
        $lain    = Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $this->bu1->id_kecamatan]);
        $anak    = $this->anak($cendana->id, $lain->id);

        $this->migration()->up();

        $this->assertSame($lain->id, (int) $anak->fresh()->id_puskesmas);
    }

    public function test_cendana_tidak_dipindah_kalau_tujuannya_sudah_punya_cendana(): void
    {
        // Memindahkannya akan melahirkan dua "Cendana" dalam satu puskesmas —
        // persis bentuk kerusakan yang sedang kita bereskan di tempat lain.
        $asal   = Posyandu::create(['name' => 'Cendana', 'id_puskesmas' => $this->bu1->id]);
        $tujuan = Posyandu::create(['name' => 'Cendana', 'id_puskesmas' => $this->bu2->id]);

        $this->migration()->up();

        $this->assertSame($this->bu1->id, (int) $asal->fresh()->id_puskesmas);
        $this->assertSame($this->bu2->id, (int) $tujuan->fresh()->id_puskesmas);
    }

    public function test_nisa_indah_dinamai_ulang_jadi_nusa_indah(): void
    {
        $nisa = Posyandu::create(['name' => 'Nisa Indah', 'id_puskesmas' => $this->bu1->id]);
        Posyandu::create(['name' => 'Nusa Indah', 'id_puskesmas' => $this->bu2->id]);

        $this->migration()->up();

        $this->assertSame('Nusa Indah', $nisa->fresh()->name);
    }

    public function test_nisa_indah_tidak_dinamai_ulang_kalau_nusa_indah_sudah_ada_di_puskesmas_yang_sama(): void
    {
        $nisa = Posyandu::create(['name' => 'Nisa Indah', 'id_puskesmas' => $this->bu1->id]);
        Posyandu::create(['name' => 'Nusa Indah', 'id_puskesmas' => $this->bu1->id]);

        $this->migration()->up();

        $this->assertSame('Nisa Indah', $nisa->fresh()->name);
    }

    public function test_down_tidak_menyeret_nusa_indah_milik_puskesmas_lain(): void
    {
        // `Nusa Indah` yang asli (Bontang Utara 2) tak pernah disentuh up(), jadi
        // down() tak boleh menyentuhnya. Membalik "semua yang bernama Nusa Indah"
        // berarti membuat posyandu yang sehat ikut salah nama.
        $nisa = Posyandu::create(['name' => 'Nisa Indah', 'id_puskesmas' => $this->bu1->id]);
        $asli = Posyandu::create(['name' => 'Nusa Indah', 'id_puskesmas' => $this->bu2->id]);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertSame('Nisa Indah', $nisa->fresh()->name);
        $this->assertSame('Nusa Indah', $asli->fresh()->name);
    }

    public function test_dijalankan_dua_kali_hasilnya_sama(): void
    {
        $cendana = Posyandu::create(['name' => 'Cendana', 'id_puskesmas' => $this->bu1->id]);
        $nisa    = Posyandu::create(['name' => 'Nisa Indah', 'id_puskesmas' => $this->bu1->id]);

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame($this->bu2->id, (int) $cendana->fresh()->id_puskesmas);
        $this->assertSame('Nusa Indah', $nisa->fresh()->name);
        $this->assertSame(1, Posyandu::where('name', 'Cendana')->count());
        $this->assertSame(1, Posyandu::where('name', 'Nusa Indah')->count());
    }
}
