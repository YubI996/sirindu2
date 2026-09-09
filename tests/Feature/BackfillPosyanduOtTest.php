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
 * `posyandu:backfill-ot` memperbaiki id_posyandu anak sumber=operasi_timbang
 * yang terlanjur salah/NULL akibat matcher lama.
 *
 * NIK di ekspor e-PPGBM tersensor ("00030**********"), jadi anak dicocokkan
 * lewat (nama, tgl lahir) dengan nama ortu sebagai penentu akhir — pola sama
 * dengan `wilayah:rekonsiliasi-kelurahan`.
 */
class BackfillPosyanduOtTest extends TestCase
{
    use RefreshDatabase;

    protected string $csv;
    protected Puskesmas $barat;

    protected function setUp(): void
    {
        parent::setUp();

        // Command-nya menulis berkas tinjauan lewat Storage::disk('local').
        // Tanpa fake, tiap kali suite dijalankan ia menumpuk sampah di
        // storage/app/posyandu milik proyek — bukan sekadar berantakan, tapi
        // bercampur dengan berkas tinjauan sungguhan yang dibaca manusia.
        Storage::fake('local');

        $kec = Kecamatan::create(['name' => 'Bontang Barat']);
        $this->barat = Puskesmas::create(['name' => 'Bontang Barat', 'id_kecamatan' => $kec->id]);
        $this->csv = tempnam(sys_get_temp_dir(), 'ot') . '.csv';
    }

    protected function tearDown(): void
    {
        if (is_file($this->csv)) {
            unlink($this->csv);
        }
        parent::tearDown();
    }

    /** @param array<int,array<string,string>> $baris */
    protected function tulisCsv(array $baris): void
    {
        $isi = ["Nama,Tgl Lahir,Nama Ortu,Pukesmas,Desa/Kel,Posyandu"];
        foreach ($baris as $b) {
            $isi[] = implode(',', [
                $b['nama'], $b['tgl_lahir'], $b['nama_ortu'] ?? '',
                $b['puskesmas'] ?? 'BONTANG BARAT', $b['kelurahan'] ?? 'GUNUNG TELIHAN', $b['posyandu'],
            ]);
        }
        file_put_contents($this->csv, implode("\n", $isi));
    }

    protected function anak(array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nik' => '6474025209250001', 'nama' => 'FARAH NUR SEPTIANA PUTRI',
            'jk' => 2, 'tgl_lahir' => '2025-09-12', 'nama_ibu' => 'SITI AMINAH',
            'sumber' => 'operasi_timbang', 'status' => 1, 'no' => 'OT-00001',
        ], $o));
    }

    public function test_dry_run_melaporkan_tanpa_menulis(): void
    {
        $pos  = Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);
        $anak = $this->anak(['id_posyandu' => null]);
        $this->tulisCsv([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'SEJAHTERA 2',
        ]]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv])->assertSuccessful();

        $this->assertNull($anak->fresh()->id_posyandu);
        $this->assertNotNull($pos->id);
    }

    public function test_commit_mengisi_posyandu_yang_semula_null(): void
    {
        $pos  = Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);
        $anak = $this->anak(['id_posyandu' => null]);
        $this->tulisCsv([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'SEJAHTERA 2',
        ]]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv, '--commit' => true])->assertSuccessful();

        $this->assertSame($pos->id, (int) $anak->fresh()->id_posyandu);
    }

    public function test_commit_membetulkan_posyandu_yang_salah_tempel(): void
    {
        // Matcher lama menempelkan anak ini ke "Griya Edelweis" lewat LIKE.
        $salah = Posyandu::create(['name' => 'Griya Edelweis', 'id_puskesmas' => $this->barat->id]);
        $benar = Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);
        $anak  = $this->anak(['id_posyandu' => $salah->id]);
        $this->tulisCsv([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'SEJAHTERA 2',
        ]]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv, '--commit' => true])->assertSuccessful();

        $this->assertSame($benar->id, (int) $anak->fresh()->id_posyandu);
    }

    public function test_posyandu_ambigu_tidak_ditebak_dan_diekspor_untuk_dinkes(): void
    {
        Posyandu::create(['name' => 'Anggrek', 'id_puskesmas' => $this->barat->id]);
        Posyandu::create(['name' => 'Anggrek', 'id_puskesmas' => $this->barat->id]);
        $anak = $this->anak(['id_posyandu' => null]);
        $this->tulisCsv([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'ANGGREK',
        ]]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv, '--commit' => true])->assertSuccessful();

        $this->assertNull($anak->fresh()->id_posyandu);
        $berkas = collect(Storage::disk('local')->allFiles())
            ->first(fn ($f) => str_contains($f, 'perlu-keputusan'));
        $this->assertNotNull($berkas, 'Kasus ambigu harus diekspor untuk ditinjau Dinkes.');
        $this->assertStringContainsString('ANGGREK', Storage::disk('local')->get($berkas));
    }

    public function test_anak_di_luar_sumber_operasi_timbang_tidak_disentuh(): void
    {
        Posyandu::create(['name' => 'Sejahtera II', 'id_puskesmas' => $this->barat->id]);
        $anak = $this->anak(['sumber' => 'capil', 'id_posyandu' => null]);
        $this->tulisCsv([[
            'nama' => 'FARAH NUR SEPTIANA PUTRI', 'tgl_lahir' => '12/09/2025',
            'nama_ortu' => 'SITI AMINAH', 'posyandu' => 'SEJAHTERA 2',
        ]]);

        $this->artisan('posyandu:backfill-ot', ['csv' => $this->csv, '--commit' => true])->assertSuccessful();

        $this->assertNull($anak->fresh()->id_posyandu);
    }
}
