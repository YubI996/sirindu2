<?php

namespace Tests\Feature\ImportKesmas;

use App\Exceptions\FormatTanggalAmbigu;
use App\Imports\KesmasImport;
use App\Models\Anak;
use App\Models\DataAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KesmasImportAnakTest extends TestCase
{
    use RefreshDatabase;

    private const ID = ['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'nama_ortu'];

    private function anak(array $isi = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah',
        ], $isi));
    }

    private function budi(array $tambahan = []): array
    {
        return array_merge(['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah'], $tambahan);
    }

    private function impor(array $header, array $baris, string $format = 'auto'): array
    {
        $i = (new KesmasImport(1))->setFormatTanggal($format);
        $i->collection(collect(array_merge([$header], $baris)));

        return $i->getResults();
    }

    private function teks(array $hasil): string
    {
        return implode("\n", $hasil['failures']);
    }

    public function test_field_anak_terisi_lewat_nik(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih', 'penolong_lahir'], [$this->budi(['Ya', 'bidan'])]);

        $this->assertSame(1, $hasil['success'], $this->teks($hasil));
        $this->assertSame(0, $hasil['error_count']);
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
        $this->assertSame('Bidan', $anak->fresh()->penolong_lahir);
    }

    public function test_sel_kosong_tidak_menimpa_nilai_lama(): void
    {
        $anak = $this->anak(['air_bersih' => 0, 'fktp_bpjs' => 'Klinik A']);

        $this->impor([...self::ID, 'air_bersih', 'fktp_bpjs', 'jamban_sehat'], [$this->budi(['', null, 'Ya'])]);

        $this->assertSame(0, (int) $anak->fresh()->air_bersih);
        $this->assertSame('Klinik A', $anak->fresh()->fktp_bpjs);
        $this->assertSame(1, (int) $anak->fresh()->jamban_sehat);
    }

    public function test_sel_berisi_menimpa_nilai_lama(): void
    {
        $anak = $this->anak(['air_bersih' => 1]);

        $this->impor([...self::ID, 'air_bersih'], [$this->budi(['Tidak'])]);

        $this->assertSame(0, (int) $anak->fresh()->air_bersih);
    }

    public function test_sel_tak_sah_hanya_kolom_itu_yang_dilewati(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'penolong_lahir', 'air_bersih'], [$this->budi(['Dukun', 'Ya'])]);

        $this->assertSame(1, $hasil['success']);
        $this->assertNull($anak->fresh()->penolong_lahir);
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
        $this->assertStringContainsString('[PERINGATAN] Baris 2 (Budi Santoso): Penolong persalinan', $this->teks($hasil));
        $this->assertStringContainsString('kolom dilewati', $this->teks($hasil));
    }

    public function test_identitas_wajib_kosong_error(): void
    {
        $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih'], [['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', '', 'Ya']]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertStringContainsString('[ERROR] Baris 2 (Budi Santoso): kolom wajib kosong atau tidak terbaca: nama_ortu', $this->teks($hasil));
        $this->assertNull(Anak::first()->air_bersih);
    }

    public function test_anak_tidak_ditemukan_error_tanpa_membuat_anak(): void
    {
        $hasil = $this->impor([...self::ID, 'air_bersih'], [['', 'Anak Baru', 'L', '2023-01-01', 'Ibu Baru', 'Ya']]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, Anak::count());
        $this->assertStringContainsString('tidak ditemukan', $this->teks($hasil));
    }

    public function test_nik_sel_angka_excel_tetap_cocok(): void
    {
        $anak = $this->anak();

        $this->impor([...self::ID, 'air_bersih'], [[6474010101230001.0, 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah', 'Ya']]);

        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
    }

    public function test_header_beda_huruf_dan_berspasi_tetap_dikenali(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([' NIK_ANAK', 'Nama_Anak ', 'JK', 'Tgl_Lahir', 'NAMA_ORTU', ' Air_Bersih '], [$this->budi(['Ya'])]);

        $this->assertSame(1, $hasil['success'], $this->teks($hasil));
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
    }

    public function test_header_wajib_hilang_menghentikan_import(): void
    {
        $this->anak();

        $hasil = $this->impor(['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'air_bersih'], [['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Ya']]);

        $this->assertSame(0, $hasil['success']);
        $this->assertStringContainsString('[ERROR] Header: kolom wajib tidak ada: nama_ortu', $this->teks($hasil));
        $this->assertNull(Anak::first()->air_bersih);
    }

    public function test_kolom_tak_dikenal_dan_sasaran_tidak_ditulis(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'sasaran_balita_kesmas', 'air_bersih'], [$this->budi(['1', 'Ya'])]);

        $this->assertNull($anak->fresh()->sasaran_balita_kesmas);
        $this->assertStringContainsString("[PERINGATAN] Header: kolom 'sasaran_balita_kesmas' tidak dikenal", $this->teks($hasil));
    }

    public function test_baris_tanpa_tanggal_kunjungan_hanya_mengisi_field_anak(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'tgl_kunjungan', 'bb', 'mbg', 'air_bersih'], [$this->budi(['', '10', 'Ya', 'Ya'])]);

        $this->assertSame(0, DataAnak::count());
        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
        $this->assertStringContainsString('diabaikan karena tgl_kunjungan kosong', $this->teks($hasil));
    }

    public function test_baris_hanya_identitas_peringatan_dan_tidak_dihitung(): void
    {
        $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih'], [$this->budi([''])]);

        $this->assertSame(0, $hasil['success']);
        $this->assertSame(0, $hasil['error_count']);
        $this->assertStringContainsString('tidak ada isian Kesmas atau kunjungan', $this->teks($hasil));
    }

    public function test_anak_di_dua_baris_baris_bawah_menang_dengan_peringatan(): void
    {
        $anak = $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih'], [$this->budi(['Ya']), $this->budi(['Tidak'])]);

        $this->assertSame(0, (int) $anak->fresh()->air_bersih);
        $this->assertStringContainsString('[PERINGATAN] Baris 3 (Budi Santoso): Akses air bersih di rumah berbeda dengan baris 2', $this->teks($hasil));
    }

    public function test_baris_kosong_dilewati_diam_diam(): void
    {
        $this->anak();

        $hasil = $this->impor([...self::ID, 'air_bersih'], [[null, null, null, null, null, null], $this->budi(['Ya'])]);

        $this->assertSame(1, $hasil['success']);
        $this->assertSame([], $hasil['failures']);
    }

    public function test_format_tanggal_ambigu_menghentikan_import(): void
    {
        $this->expectException(FormatTanggalAmbigu::class);

        $this->impor([...self::ID, 'air_bersih'], [['', 'Budi Santoso', 'L', '05/02/2023', 'Siti Aminah', 'Ya']]);
    }

    public function test_format_tanggal_pilihan_dipakai(): void
    {
        $anak = $this->anak(['tgl_lahir' => '2023-02-05']);

        $this->impor([...self::ID, 'air_bersih'], [['', 'Budi Santoso', 'L', '05/02/2023', 'Siti Aminah', 'Ya']], 'dmy');

        $this->assertSame(1, (int) $anak->fresh()->air_bersih);
    }
}
