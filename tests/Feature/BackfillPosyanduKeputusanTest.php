<?php

namespace Tests\Feature;

use App\Models\Anak;
use App\Models\Kecamatan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `posyandu:backfill-ot --keputusan=<csv>` menerapkan keputusan Dinkes untuk
 * baris yang sengaja tidak ditebak sistem.
 *
 * Latar: matcher berhenti pada 414 baris berkas Juni 2026 — nama yang tak ada
 * padanan persisnya ("edelweis" vs "Griya Edelweis"), nama kembar di data induk
 * ("Anggrek" dua baris di puskesmas sama), dan baris yang kolom posyandunya
 * memang kosong. Dinkes memutuskan tiap kasus; berkas keputusan inilah wadahnya,
 * supaya pemetaannya bisa ditinjau siapa pun dan dipakai ulang pada impor
 * berikutnya, bukan hilang jadi keputusan sekali pakai.
 *
 * Keputusan SELALU menang atas tebakan matcher — itu memang gunanya.
 */
class BackfillPosyanduKeputusanTest extends TestCase
{
    use RefreshDatabase;

    protected string $csv;
    protected string $keputusan;
    protected Puskesmas $barat;
    protected Puskesmas $utara;

    protected function setUp(): void
    {
        parent::setUp();

        // Lihat BackfillPosyanduOtTest: command-nya menulis berkas tinjauan ke
        // Storage::disk('local'), yang tanpa fake mengotori storage proyek.
        Storage::fake('local');

        $kec = Kecamatan::create(['name' => 'Bontang Barat']);
        $this->barat = Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $kec->id]);
        $this->utara = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);

        $this->csv       = tempnam(sys_get_temp_dir(), 'ot') . '.csv';
        $this->keputusan = tempnam(sys_get_temp_dir(), 'kp') . '.csv';
    }

    protected function tearDown(): void
    {
        foreach ([$this->csv, $this->keputusan] as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        parent::tearDown();
    }

    /** @param array<int,array<string,string>> $baris */
    protected function tulisOt(array $baris): void
    {
        $isi = ['Nama,Tgl Lahir,Nama Ortu,Pukesmas,Desa/Kel,Posyandu'];
        foreach ($baris as $b) {
            $isi[] = implode(',', [
                $b['nama'], $b['tgl_lahir'], $b['nama_ortu'] ?? '',
                $b['puskesmas'] ?? 'BONTANG BARAT',
                $b['kelurahan'] ?? 'GUNUNG TELIHAN',
                $b['posyandu'] ?? '',
            ]);
        }
        file_put_contents($this->csv, implode("\n", $isi));
    }

    /** @param array<int,array<string,string>> $baris */
    protected function tulisKeputusan(array $baris): void
    {
        $isi = ['posyandu_berkas,puskesmas,kelurahan,posyandu_master'];
        foreach ($baris as $b) {
            $isi[] = implode(',', [
                $b['posyandu_berkas'] ?? '',
                $b['puskesmas'] ?? '',
                $b['kelurahan'] ?? '',
                $b['posyandu_master'],
            ]);
        }
        file_put_contents($this->keputusan, implode("\n", $isi));
    }

    protected function anak(array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nik' => '6474025209250001', 'nama' => 'FARAH NUR SEPTIANA PUTRI',
            'jk' => 2, 'tgl_lahir' => '2025-09-12', 'nama_ibu' => 'SITI AMINAH',
            'sumber' => 'operasi_timbang', 'status' => 1, 'no' => 'OT-00001',
        ], $o));
    }

    protected function jalankan(): void
    {
        $this->artisan('posyandu:backfill-ot', [
            'csv'          => $this->csv,
            '--keputusan'  => $this->keputusan,
            '--commit'     => true,
        ])->assertSuccessful();
    }

    public function test_keputusan_memetakan_nama_yang_tak_punya_padanan_persis(): void
    {
        // Kasus nyata: berkas menulis "edelweis", data induk "Griya Edelweis".
        $pos  = Posyandu::create(['name' => 'Griya Edelweis', 'id_puskesmas' => $this->utara->id]);
        $anak = $this->anak(['id_posyandu' => null]);

        $this->tulisOt([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'edelweis',
            'puskesmas' => 'BONTANG UTARA I', 'kelurahan' => 'BONTANG KUALA',
        ]]);
        $this->tulisKeputusan([[
            'posyandu_berkas' => 'edelweis', 'puskesmas' => 'BONTANG UTARA I',
            'kelurahan' => 'BONTANG KUALA', 'posyandu_master' => 'Griya Edelweis',
        ]]);

        $this->jalankan();

        $this->assertSame($pos->id, (int) $anak->fresh()->id_posyandu);
    }

    public function test_keputusan_membedakan_nama_kembar_lewat_kelurahan(): void
    {
        // Dua "Anggrek" di puskesmas yang sama — matcher menyerah, keputusan tidak.
        $belimbing = Posyandu::create(['name' => 'Anggrek', 'id_puskesmas' => $this->barat->id]);
        $telihan   = Posyandu::create(['name' => 'Anggrek1', 'id_puskesmas' => $this->barat->id]);
        $anak      = $this->anak(['id_posyandu' => null]);

        $this->tulisOt([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'ANGGREK',
            'puskesmas' => 'BONTANG BARAT', 'kelurahan' => 'BELIMBING',
        ]]);
        $this->tulisKeputusan([[
            'posyandu_berkas' => 'ANGGREK', 'puskesmas' => 'BONTANG BARAT',
            'kelurahan' => 'BELIMBING', 'posyandu_master' => 'Anggrek',
        ]]);

        $this->jalankan();

        $this->assertSame($belimbing->id, (int) $anak->fresh()->id_posyandu);
        $this->assertNotSame($telihan->id, (int) $anak->fresh()->id_posyandu);
    }

    public function test_keputusan_menangani_baris_yang_kolom_posyandunya_kosong(): void
    {
        // Tak ada nama yang bisa dicocokkan — hanya keputusan yang bisa mengisi.
        $pos  = Posyandu::create(['name' => 'Pasir Putih 10', 'id_puskesmas' => $this->barat->id]);
        $anak = $this->anak(['id_posyandu' => null]);

        $this->tulisOt([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => '',
            'puskesmas' => 'BONTANG BARAT', 'kelurahan' => 'BONTANG LESTARI',
        ]]);
        $this->tulisKeputusan([[
            'posyandu_berkas' => '', 'puskesmas' => 'BONTANG BARAT',
            'kelurahan' => 'BONTANG LESTARI', 'posyandu_master' => 'Pasir Putih 10',
        ]]);

        $this->jalankan();

        $this->assertSame($pos->id, (int) $anak->fresh()->id_posyandu);
    }

    public function test_keputusan_menang_atas_tebakan_matcher(): void
    {
        // Matcher sendiri akan memilih "Sejahtera II" untuk "SEJAHTERA 2".
        Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);
        $lain = Posyandu::create(['name' => 'Sejahtera V', 'id_puskesmas' => $this->barat->id]);
        $anak = $this->anak(['id_posyandu' => null]);

        $this->tulisOt([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'SEJAHTERA 2',
            'puskesmas' => 'BONTANG BARAT', 'kelurahan' => 'GUNUNG TELIHAN',
        ]]);
        $this->tulisKeputusan([[
            'posyandu_berkas' => 'SEJAHTERA 2', 'puskesmas' => 'BONTANG BARAT',
            'kelurahan' => 'GUNUNG TELIHAN', 'posyandu_master' => 'Sejahtera V',
        ]]);

        $this->jalankan();

        $this->assertSame($lain->id, (int) $anak->fresh()->id_posyandu);
    }

    public function test_keputusan_menyebut_posyandu_yang_tak_ada_dilaporkan_bukan_didiamkan(): void
    {
        Posyandu::create(['name' => 'Griya Edelweis', 'id_puskesmas' => $this->utara->id]);
        $anak = $this->anak(['id_posyandu' => null]);

        $this->tulisOt([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'edelweis',
            'puskesmas' => 'BONTANG UTARA I', 'kelurahan' => 'BONTANG KUALA',
        ]]);
        $this->tulisKeputusan([[
            'posyandu_berkas' => 'edelweis', 'puskesmas' => 'BONTANG UTARA I',
            'kelurahan' => 'BONTANG KUALA', 'posyandu_master' => 'Posyandu Antah Berantah',
        ]]);

        $this->artisan('posyandu:backfill-ot', [
            'csv'         => $this->csv,
            '--keputusan' => $this->keputusan,
            '--commit'    => true,
        ])->expectsOutputToContain('Antah Berantah')->assertSuccessful();

        $this->assertNull($anak->fresh()->id_posyandu);
    }

    public function test_tanpa_flag_keputusan_perilaku_lama_tidak_berubah(): void
    {
        $pos  = Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);
        $anak = $this->anak(['id_posyandu' => null]);

        $this->tulisOt([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'SEJAHTERA 2',
        ]]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv, '--commit' => true])
            ->assertSuccessful();

        $this->assertSame($pos->id, (int) $anak->fresh()->id_posyandu);
    }
}
