<?php

namespace Tests\Feature;

use App\Models\Kecamatan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use App\Services\FaskesMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencocokan nama posyandu berkas e-PPGBM terhadap master.
 *
 * Master ditulis dengan angka ROMAWI ("Sejahtera II"), ekspor e-PPGBM memakai
 * angka ARAB ("SEJAHTERA 2"). Matcher lama mencocokkan nama apa adanya lalu
 * jatuh ke LIKE '%nama%' global, sehingga 23,8% baris Juni 2026 berakhir
 * id_posyandu NULL dan 6,5% menempel ke posyandu milik puskesmas lain.
 */
class FaskesMatcherTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,int> nama puskesmas => id */
    protected array $pus = [];

    protected function setUp(): void
    {
        parent::setUp();

        $kec = Kecamatan::create(['name' => 'Bontang Barat']);
        foreach (['Bontang Barat', 'Bontang Utara 1', 'Bontang Utara 2', 'Bontang Selatan 2'] as $n) {
            $this->pus[$n] = Puskesmas::create(['name' => $n, 'id_kecamatan' => $kec->id])->id;
        }
    }

    protected function posyandu(string $nama, string $puskesmas): int
    {
        return Posyandu::create(['name' => $nama, 'id_puskesmas' => $this->pus[$puskesmas]])->id;
    }

    public function test_angka_romawi_di_master_cocok_dengan_angka_arab_di_berkas(): void
    {
        $id = $this->posyandu('Sejahtera II', 'Bontang Barat');

        $hasil = (new FaskesMatcher())->cocokkan('SEJAHTERA 2', 'BONTANG BARAT');

        $this->assertSame($id, $hasil['id']);
    }

    public function test_nama_kembar_dibedakan_oleh_puskesmas(): void
    {
        $barat = $this->posyandu('Mawar', 'Bontang Barat');
        $utara = $this->posyandu('Mawar', 'Bontang Utara 1');

        $m = new FaskesMatcher();

        $this->assertSame($barat, $m->cocokkan('MAWAR', 'BONTANG BARAT')['id']);
        $this->assertSame($utara, $m->cocokkan('MAWAR', 'BONTANG UTARA I')['id']);
    }

    public function test_beda_spasi_tetap_cocok(): void
    {
        $id = $this->posyandu('Mekarsari', 'Bontang Barat');

        $hasil = (new FaskesMatcher())->cocokkan('MEKAR SARI', 'BONTANG BARAT');

        $this->assertSame($id, $hasil['id']);
    }

    public function test_akhiran_satu_bersifat_opsional_di_kedua_sisi(): void
    {
        $flamboyan   = $this->posyandu('Flamboyan 1', 'Bontang Utara 1');
        $this->posyandu('Flamboyan 2', 'Bontang Utara 1');
        $cendrawasih = $this->posyandu('Cendrawasih', 'Bontang Barat');
        $this->posyandu('Cendrawasih II', 'Bontang Barat');

        $m = new FaskesMatcher();

        // berkas polos -> master bernomor 1
        $this->assertSame($flamboyan, $m->cocokkan('FLAMBOYAN', 'BONTANG UTARA I')['id']);
        // berkas bernomor 1 (nempel tanpa spasi) -> master polos
        $this->assertSame($cendrawasih, $m->cocokkan('CENDRAWASIH1', 'BONTANG BARAT')['id']);
    }

    public function test_nama_kembar_dalam_satu_puskesmas_tidak_ditebak(): void
    {
        $a = $this->posyandu('Anggrek', 'Bontang Barat');
        $b = $this->posyandu('Anggrek', 'Bontang Barat');

        $hasil = (new FaskesMatcher())->cocokkan('ANGGREK', 'BONTANG BARAT');

        $this->assertNull($hasil['id']);
        $this->assertSame('ambigu', $hasil['alasan']);
        // Dua master bernama sama tak bisa dibedakan dari namanya saja — sebut
        // id-nya supaya Dinkes bisa menggabung/menamai ulang barisnya.
        $this->assertSame(["Anggrek #{$a}", "Anggrek #{$b}"], $hasil['kandidat']);
    }

    public function test_tidak_menempel_ke_posyandu_lain_yang_namanya_kebetulan_memuat(): void
    {
        // Matcher lama: LIKE '%EDELWEIS%' -> "Griya Edelweis". Pasien salah posyandu.
        $this->posyandu('Griya Edelweis', 'Bontang Utara 1');

        $hasil = (new FaskesMatcher())->cocokkan('EDELWEIS', 'BONTANG UTARA I');

        $this->assertNull($hasil['id']);
        $this->assertSame('tak-ada', $hasil['alasan']);
    }

    public function test_puskesmas_master_yang_meleset_ditolong_kecocokan_unik_global(): void
    {
        // Master menaruh Cendana di Bontang Utara 1; berkas menyebut Bontang Utara II.
        // Namanya unik se-master, jadi aman dipulihkan.
        $id = $this->posyandu('Cendana', 'Bontang Utara 1');

        $hasil = (new FaskesMatcher())->cocokkan('CENDANA', 'BONTANG UTARA II');

        $this->assertSame($id, $hasil['id']);
        $this->assertSame('global-unik', $hasil['alasan']);
    }

    public function test_fallback_global_tidak_dipakai_bila_namanya_tidak_unik(): void
    {
        $this->posyandu('Mutiara 1', 'Bontang Utara 1');
        $this->posyandu('Mutiara 2', 'Bontang Selatan 2');

        // "MUTIARA" polos dari puskesmas yang tak punya Mutiara 1 -> jangan tebak.
        $hasil = (new FaskesMatcher())->cocokkan('MUTIARA', 'BONTANG UTARA II');

        $this->assertNull($hasil['id']);
    }

    public function test_nama_kosong_menghasilkan_null(): void
    {
        $hasil = (new FaskesMatcher())->cocokkan('  ', 'BONTANG BARAT');

        $this->assertNull($hasil['id']);
        $this->assertSame('kosong', $hasil['alasan']);
    }

    /**
     * Puskesmas kena cacat yang persis sama: master "Bontang Utara 1",
     * berkas "BONTANG UTARA I" — 7.386 dari 9.884 baris Juni 2026 (74,7%)
     * berakhir id_puskesmas NULL karena exact gagal dan LIKE '%BONTANG UTARA I%'
     * juga tak cocok.
     */
    public function test_puskesmas_romawi_di_berkas_cocok_dengan_angka_arab_di_master(): void
    {
        $m = new FaskesMatcher();

        $this->assertSame($this->pus['Bontang Utara 1'], $m->cocokkanPuskesmas('BONTANG UTARA I')['id']);
        $this->assertSame($this->pus['Bontang Utara 2'], $m->cocokkanPuskesmas('BONTANG UTARA II')['id']);
        $this->assertSame($this->pus['Bontang Barat'], $m->cocokkanPuskesmas('BONTANG BARAT')['id']);
    }

    public function test_puskesmas_tak_dikenal_menghasilkan_null(): void
    {
        $hasil = (new FaskesMatcher())->cocokkanPuskesmas('PUSKESMAS ANTAH BERANTAH');

        $this->assertNull($hasil['id']);
        $this->assertSame('tak-ada', $hasil['alasan']);
    }

    /**
     * Nama yang menyerah ('tak-ada') tetap harus memberi Dinkes bahan
     * keputusan — daftar kosong bikin berkas tinjauan tak bisa dipakai.
     */
    public function test_saran_menampilkan_master_yang_mirip_untuk_ditinjau_manusia(): void
    {
        $this->posyandu('Bakung 1', 'Bontang Barat');
        $this->posyandu('Bakung 2', 'Bontang Utara 1');
        $this->posyandu('Melati 1', 'Bontang Utara 1');

        $saran = (new FaskesMatcher())->saran('BAKUNG');

        $this->assertContains('Bakung 1 (Bontang Barat)', $saran);
        $this->assertContains('Bakung 2 (Bontang Utara 1)', $saran);
        $this->assertNotContains('Melati 1 (Bontang Utara 1)', $saran);
    }

    public function test_saran_juga_menangkap_master_yang_memuat_nama_berkas(): void
    {
        $this->posyandu('Griya Edelweis', 'Bontang Utara 1');

        $this->assertContains('Griya Edelweis (Bontang Utara 1)', (new FaskesMatcher())->saran('EDELWEIS'));
    }

    public function test_saran_kosong_bila_tak_ada_yang_mirip(): void
    {
        $this->posyandu('Melati 1', 'Bontang Utara 1');

        $this->assertSame([], (new FaskesMatcher())->saran('ANTAH BERANTAH'));
    }
}
