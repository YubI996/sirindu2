<?php

namespace Tests\Feature\ImportKesmas;

use App\Models\Anak;
use App\Services\KolomKesmasImport;
use App\Support\TanggalBerkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KolomKesmasImportTest extends TestCase
{
    use RefreshDatabase;

    private function anak(array $isi = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah',
        ], $isi));
    }

    private function ubah(string $tingkat, array $sel, ?Anak $anak = null): array
    {
        return KolomKesmasImport::ubah($tingkat, $sel, $anak ?? $this->anak(), fn ($v) => TanggalBerkas::parse($v, 'dmy'));
    }

    public function test_daftar_kolom_diturunkan_dari_kesmas_rules(): void
    {
        $this->assertContains('air_bersih', KolomKesmasImport::kolom('anak'));
        $this->assertContains('tgl_hbig', KolomKesmasImport::kolom('anak'));
        $this->assertNotContains('sasaran_balita_kesmas', KolomKesmasImport::kolom('anak'));
        $this->assertContains('mbg', KolomKesmasImport::kolom('kunjungan'));
        $this->assertContains('rujukan', KolomKesmasImport::kolom('kunjungan'));
    }

    public function test_pilihan_untuk_template(): void
    {
        $this->assertSame(['Ya', 'Tidak'], KolomKesmasImport::pilihan('air_bersih'));
        $this->assertSame(['Ya', 'Tidak'], KolomKesmasImport::pilihan('kn1'));
        $this->assertSame(['Belum', 'Normal', 'Tidak normal'], KolomKesmasImport::pilihan('skrining_shk'));
        $this->assertContains('Bidan', KolomKesmasImport::pilihan('penolong_lahir'));
        $this->assertNull(KolomKesmasImport::pilihan('fktp_bpjs'));
    }

    public function test_mengubah_tiap_jenis_kolom_anak(): void
    {
        $hasil = $this->ubah('anak', [
            'air_bersih' => 'ya', 'penolong_lahir' => 'BIDAN', 'skrining_shk' => 'Tidak normal',
            'bbl' => '3,1', 'tgl_hbig' => '05/01/2023', 'fktp_bpjs' => ' Puskesmas Bontang Utara 1 ',
        ]);

        $this->assertSame([], $hasil['salah']);
        $this->assertSame([
            'air_bersih' => 1, 'penolong_lahir' => 'Bidan', 'skrining_shk' => 'tidak_normal',
            'bbl' => 3.1, 'tgl_hbig' => '2023-01-05', 'fktp_bpjs' => 'Puskesmas Bontang Utara 1',
        ], $hasil['nilai']);
    }

    public function test_sel_tak_dikenal_masuk_salah_dengan_label_dan_pilihan(): void
    {
        $hasil = $this->ubah('anak', ['penolong_lahir' => 'Dukun', 'air_bersih' => 'Ya']);

        $this->assertSame(['air_bersih' => 1], $hasil['nilai']);
        $this->assertStringContainsString('Penolong persalinan', $hasil['salah']['penolong_lahir']);
        $this->assertStringContainsString("'Dukun'", $hasil['salah']['penolong_lahir']);
        $this->assertStringContainsString('Bidan', $hasil['salah']['penolong_lahir']);
    }

    public function test_aturan_kesmas_rules_tetap_berlaku(): void
    {
        // tgl_hbig sebelum tanggal lahir & usia kehamilan di luar 20–45 ditolak oleh KesmasRules.
        $hasil = $this->ubah('anak', ['tgl_hbig' => '01/12/2022', 'usia_kehamilan_lahir' => '50', 'imd' => 'Tidak']);

        $this->assertSame(['imd' => 0], $hasil['nilai']);
        $this->assertArrayHasKey('tgl_hbig', $hasil['salah']);
        $this->assertStringContainsString('Usia kehamilan saat lahir', $hasil['salah']['usia_kehamilan_lahir']);
    }

    public function test_penolong_lama_di_luar_daftar_tetap_sah(): void
    {
        $anak = $this->anak(['penolong_lahir' => 'Dukun Terlatih']);

        $hasil = $this->ubah('anak', ['penolong_lahir' => 'dukun terlatih'], $anak);

        $this->assertSame(['penolong_lahir' => 'Dukun Terlatih'], $hasil['nilai']);
    }

    public function test_kolom_kunjungan(): void
    {
        $hasil = $this->ubah('kunjungan', ['mbg' => 'Ya', 'rujukan' => 'Puskesmas X', 'tgl_penanda_ckg' => '10/03/2025']);

        $this->assertSame(['mbg' => 1, 'tgl_penanda_ckg' => '2025-03-10'], $hasil['nilai']);
        $this->assertStringContainsString('Rujukan', $hasil['salah']['rujukan']);
    }
}
