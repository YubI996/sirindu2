<?php

namespace Tests\Feature;

use App\Models\Kecamatan;
use App\Models\Posyandu;
use App\Models\Puskesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `posyandu:backfill-ot` melaporkan bila DUA nama berbeda di berkas jatuh ke
 * SATU posyandu yang sama.
 *
 * Ini gejala tunggal dari tiga kerusakan yang selama ini baru ketahuan setelah
 * daftar berkas dibandingkan manual dengan isi server:
 *   - "ANGGREK" + "ANGGREK1" -> satu baris Anggrek (178 anak, Sept 2026)
 *   - "CENDRAWASIH" + "CENDRAWASIH1" -> satu baris Cendrawasih (113 anak)
 *   - "nusa indah"@BU-1 + "Nusa indah"@BU-2 -> satu baris Nusa Indah (31 anak)
 * Ketiganya "cocok 100%" menurut hitungan cakupan — tak ada yang gagal, tak ada
 * yang perlu keputusan — padahal anaknya mendarat di posyandu yang keliru.
 *
 * Aturannya: tabrakan itu MENCURIGAKAN kalau sistem yang menebak, dan WAJAR
 * kalau manusia yang memutuskan. Baris berkas yang kolom posyandunya kosong
 * memang sengaja diarahkan Dinkes ke posyandu yang sudah punya nama sendiri —
 * melaporkannya cuma jadi derau yang membuat laporan ini ikut diabaikan.
 *
 * Laporan, bukan penolakan: dua ejaan untuk satu posyandu itu benar-benar ada,
 * jadi hanya manusia yang bisa memutuskan mana yang sinonim dan mana yang bukan.
 */
class BackfillTabrakanNamaTest extends TestCase
{
    use RefreshDatabase;

    protected string $csv;
    protected string $keputusan;
    protected Puskesmas $barat;
    protected Puskesmas $bu1;
    protected Puskesmas $bu2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $kec = Kecamatan::create(['name' => 'Bontang']);
        $this->barat = Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $kec->id]);
        $this->bu1   = Puskesmas::create(['name' => 'Bontang Utara 1', 'id_kecamatan' => $kec->id]);
        $this->bu2   = Puskesmas::create(['name' => 'Bontang Utara 2', 'id_kecamatan' => $kec->id]);

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
        foreach ($baris as $i => $b) {
            $isi[] = implode(',', [
                $b['nama'] ?? ('ANAK ' . $i),
                $b['tgl_lahir'] ?? '12/09/2025',
                '',
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

    public function test_dua_nama_berbeda_yang_ditebak_ke_satu_posyandu_dilaporkan(): void
    {
        // Sengaja TANPA satu pun baris di tabel anak: yang diperiksa adalah
        // pemetaan berkas -> data induk, dan itu sudah salah sebelum anaknya
        // dicari. Menunggu anaknya ada berarti diam pada berkas impor pertama.
        Posyandu::create(['name' => 'Cendrawasih', 'id_puskesmas' => $this->barat->id]);

        $this->tulisOt([
            ['posyandu' => 'CENDRAWASIH',  'kelurahan' => 'BELIMBING'],
            ['posyandu' => 'CENDRAWASIH1', 'kelurahan' => 'GUNUNG TELIHAN'],
        ]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv])
            ->expectsOutputToContain('TABRAKAN NAMA')
            ->expectsOutputToContain('CENDRAWASIH1')
            ->assertSuccessful();
    }

    public function test_nama_sama_dari_dua_puskesmas_yang_ditebak_ke_satu_posyandu_dilaporkan(): void
    {
        // Master hanya punya satu "Nusa Indah", di Bontang Utara 2. Baris
        // Bontang Utara 1 lolos lewat tahap "global-unik" — nama identik dan
        // unik se-master — lalu diam-diam tercatat di puskesmas tetangga.
        Posyandu::create(['name' => 'Nusa Indah', 'id_puskesmas' => $this->bu2->id]);

        $this->tulisOt([
            ['posyandu' => 'nusa indah', 'puskesmas' => 'BONTANG UTARA I',  'kelurahan' => 'BONTANG BARU'],
            ['posyandu' => 'Nusa indah', 'puskesmas' => 'BONTANG UTARA II', 'kelurahan' => 'LOK TUAN'],
        ]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv])
            ->expectsOutputToContain('TABRAKAN NAMA')
            ->expectsOutputToContain('Nusa Indah')
            ->assertSuccessful();
    }

    public function test_tabrakan_yang_berasal_dari_keputusan_dinkes_tidak_dilaporkan(): void
    {
        // Baris berkas yang kolom posyandunya kosong memang sengaja diarahkan
        // Dinkes ke posyandu yang sudah punya namanya sendiri. Itu pilihan
        // manusia, bukan tebakan sistem.
        Posyandu::create(['name' => 'Sejahtera V', 'id_puskesmas' => $this->barat->id]);

        $this->tulisOt([
            ['posyandu' => 'SEJAHTERA 5', 'kelurahan' => 'GUNUNG TELIHAN'],
            ['posyandu' => '',            'kelurahan' => 'GUNUNG TELIHAN'],
        ]);
        $this->tulisKeputusan([[
            'posyandu_berkas' => '',
            'puskesmas'       => 'BONTANG BARAT',
            'kelurahan'       => 'GUNUNG TELIHAN',
            'posyandu_master' => 'Sejahtera V',
        ]]);

        $this->artisan('posyandu:backfill-ot', [
            'csv'         => $this->csv,
            '--keputusan' => $this->keputusan,
        ])->doesntExpectOutputToContain('TABRAKAN NAMA')->assertSuccessful();
    }

    public function test_nama_yang_sama_berulang_kali_bukan_tabrakan(): void
    {
        // Ratusan baris dengan nama posyandu yang sama adalah keadaan normal —
        // itu memang isi satu posyandu. Yang dihitung nama BERBEDA, bukan baris.
        Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);
        Posyandu::create(['name' => 'Kusuma', 'id_puskesmas' => $this->barat->id]);

        $this->tulisOt([
            ['posyandu' => 'SEJAHTERA 2'],
            ['posyandu' => 'SEJAHTERA 2'],
            ['posyandu' => 'KUSUMA'],
        ]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv])
            ->doesntExpectOutputToContain('TABRAKAN NAMA')
            ->assertSuccessful();
    }
}
