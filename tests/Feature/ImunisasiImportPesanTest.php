<?php

namespace Tests\Feature;

use App\Imports\ImunisasiImport;
use App\Models\Anak;
use App\Models\JenisVaksin;
use App\Models\User;
use Database\Seeders\JenisVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Pesan di "Lihat detail error" Riwayat Import harus menjelaskan sebab dan cara
 * memperbaikinya — dan kegagalan yang dulu dilewati diam-diam (tanggal vaksin tak
 * terbaca, kolom vaksin tak dikenali) harus dilaporkan. Perilaku penyimpanan tidak berubah.
 */
class ImunisasiImportPesanTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(JenisVaksinSeeder::class);
        $this->admin = User::factory()->create(['type' => 1]);
    }

    private function anak(array $overrides = []): Anak
    {
        return Anak::create(array_merge([
            'nama' => 'Budi Santoso', 'nik' => '3201011501200001', 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2020-01-15', 'status' => 1,
        ], $overrides));
    }

    private function rows(array $header, array ...$data): Collection
    {
        return collect(array_merge([$header], $data));
    }

    /** Jalankan satu chunk lalu kembalikan daftar pesan. */
    private function pesan(Collection $rows): array
    {
        $import = new ImunisasiImport($this->admin->id);
        $import->collection($rows);
        return $import->getResults()['failures'];
    }

    private function hanyaYangMemuat(array $pesan, string $potongan): array
    {
        return array_values(array_filter($pesan, fn ($p) => str_contains($p, $potongan)));
    }

    public function test_ringkasan_dan_legenda_awalan_di_baris_pertama(): void
    {
        $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'BCG'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '2020-01-15', '2020-02-15'],
        ));

        $this->assertStringStartsWith('Ringkasan: 1 baris data dibaca, 2 vaksin disimpan/diperbarui untuk 1 anak', $pesan[0]);
        $this->assertStringContainsString('[ERROR]', $pesan[0]);
        $this->assertStringContainsString('[PERINGATAN]', $pesan[0]);
        $this->assertStringContainsString('[INFO]', $pesan[0]);
    }

    public function test_header_tidak_ditemukan_menyebut_kolom_yang_diharapkan(): void
    {
        $pesan = $this->pesan(collect([['# hanya komentar'], ['# lagi']]));

        $error = $this->hanyaYangMemuat($pesan, '[ERROR] Header tidak ditemukan');
        $this->assertCount(1, $error);
        $this->assertStringContainsString('nik_anak, nama_anak, tgl_lahir_anak', $error[0]);
        $this->assertStringContainsString('kode_vaksin', $error[0]);
    }

    public function test_tanpa_kolom_vaksin_dikenali_dilaporkan_sebagai_error_sekali(): void
    {
        $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'Polio 1'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '2020-03-15'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '2020-04-15'],
        ));

        $error = $this->hanyaYangMemuat($pesan, '[ERROR] Tidak ada kolom vaksin yang dikenali');
        $this->assertCount(1, $error);
        $this->assertStringContainsString("'polio 1'", $error[0]);
        $this->assertStringContainsString('POLIO1', $error[0], 'Menyebut kode yang sah agar petugas bisa membetulkan header.');
        $this->assertSame([], $this->hanyaYangMemuat($pesan, 'tidak dikenali sebagai kode vaksin'), 'Tidak digandakan dengan peringatan kolom.');
        $this->assertSame([], $this->hanyaYangMemuat($pesan, 'tidak ada yang disimpan'), 'Tanpa kolom vaksin, [INFO] per baris hanya jadi kebisingan.');
    }

    public function test_kolom_tak_dikenal_dilaporkan_sekali_dan_kolom_sah_tetap_diproses(): void
    {
        $anak = $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'Polio 1', 'keterangan', 'alasan_tidak_imunisasi'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '2020-01-15', '2020-03-15', 'lengkap', ''],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '2020-01-16', '2020-03-15', 'lengkap', ''],
        ));

        $peringatan = $this->hanyaYangMemuat($pesan, 'tidak dikenali sebagai kode vaksin');
        $this->assertCount(1, $peringatan);
        $this->assertStringContainsString("'polio 1'", $peringatan[0]);
        $this->assertStringContainsString("'keterangan'", $peringatan[0]);
        $this->assertStringNotContainsString("'hb0'", $peringatan[0]);
        $this->assertStringNotContainsString('alasan_tidak_imunisasi', $peringatan[0]);
        $this->assertDatabaseHas('imunisasi', ['id_anak' => $anak->id, 'id_jenis_vaksin' => JenisVaksin::where('kode', 'HB0')->value('id')]);
    }

    public function test_identitas_kurang_menyebut_kolom_terisi_dan_kosong(): void
    {
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0'],
            ['', 'Budi Santoso', '', '2020-01-15'],
        ));

        $p = $this->hanyaYangMemuat($pesan, 'identitas anak kurang');
        $this->assertCount(1, $p);
        $this->assertStringStartsWith('[PERINGATAN] Baris 2:', $p[0]);
        $this->assertStringContainsString('terisi: nama_anak', $p[0]);
        $this->assertStringContainsString('kosong: nik_anak, tgl_lahir_anak', $p[0]);
        $this->assertStringContainsString('minimal 2 dari', $p[0]);
    }

    public function test_tgl_lahir_tak_terbaca_disebut_nilainya(): void
    {
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0'],
            ['', 'Budi Santoso', '31/02/2020', '2020-01-15'],
        ));

        $p = $this->hanyaYangMemuat($pesan, 'identitas anak kurang');
        $this->assertCount(1, $p);
        $this->assertStringContainsString("tgl_lahir_anak '31/02/2020' tidak terbaca", $p[0]);
        $this->assertStringContainsString('YYYY-MM-DD', $p[0]);
    }

    public function test_nik_bukan_digit_tetap_cocok_via_nama_tgl_dan_diberi_info(): void
    {
        $anak = $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0'],
            ['3.20101E+15', 'Budi Santoso', '2020-01-15', '2020-01-15'],
        ));

        $this->assertDatabaseHas('imunisasi', ['id_anak' => $anak->id, 'id_jenis_vaksin' => JenisVaksin::where('kode', 'HB0')->value('id')]);
        $info = $this->hanyaYangMemuat($pesan, "NIK '3.20101E+15' bukan 15");
        $this->assertCount(1, $info);
        $this->assertStringStartsWith('[INFO] Baris 2', $info[0]);
        $this->assertStringContainsString('sebagai teks', $info[0], 'Sebutkan penyebab umum: Excel membulatkan NIK.');
        $this->assertStringContainsString('nama+tgl_lahir', $info[0]);
    }

    public function test_anak_tidak_ditemukan_menyebut_identitas_yang_dicari(): void
    {
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0'],
            ['9999999999999999', 'Tidak Ada', '2019-01-01', '2020-01-15'],
        ));

        $p = $this->hanyaYangMemuat($pesan, 'Anak tidak ditemukan');
        $this->assertCount(1, $p);
        $this->assertStringStartsWith('[ERROR] Baris 2 (Tidak Ada)', $p[0]);
        $this->assertStringContainsString("NIK 9999999999999999, nama 'Tidak Ada', tgl lahir 2019-01-01", $p[0]);
        $this->assertStringContainsString('diimport lebih dulu', $p[0]);
    }

    public function test_anak_tidak_ditemukan_dengan_nik_rusak_menyebut_niknya(): void
    {
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0'],
            ['3.20101E+15', 'Tidak Ada', '2019-01-01', '2020-01-15'],
        ));

        $p = $this->hanyaYangMemuat($pesan, 'Anak tidak ditemukan');
        $this->assertCount(1, $p);
        $this->assertStringContainsString("NIK '3.20101E+15' bukan 15", $p[0]);
        $this->assertStringContainsString('tidak dipakai mencari', $p[0]);
    }

    public function test_tanggal_vaksin_tak_terbaca_dilaporkan_dan_yang_lain_tetap_tersimpan(): void
    {
        $anak = $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'BCG', 'MR1'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '2020-01-15', '15/02/2020', '-'],
        ));

        $this->assertDatabaseHas('imunisasi', ['id_anak' => $anak->id, 'id_jenis_vaksin' => JenisVaksin::where('kode', 'HB0')->value('id')]);
        $this->assertDatabaseMissing('imunisasi', ['id_anak' => $anak->id, 'id_jenis_vaksin' => JenisVaksin::where('kode', 'BCG')->value('id')]);
        $this->assertDatabaseMissing('imunisasi', ['id_anak' => $anak->id, 'id_jenis_vaksin' => JenisVaksin::where('kode', 'MR1')->value('id')]);

        $p = $this->hanyaYangMemuat($pesan, 'tanggal tidak terbaca');
        $this->assertCount(1, $p, 'Satu pesan per baris, bukan per sel.');
        $this->assertStringStartsWith('[PERINGATAN] Baris 2 (Budi Santoso)', $p[0]);
        $this->assertStringContainsString("BCG ('15/02/2020')", $p[0]);
        $this->assertStringContainsString("MR1 ('-')", $p[0]);
        $this->assertStringContainsString('YYYY-MM-DD', $p[0]);
        $this->assertStringStartsWith('Ringkasan: 1 baris data dibaca, 1 vaksin disimpan/diperbarui untuk 1 anak', $pesan[0]);
    }

    public function test_tanda_x_angka_tunggal_dan_tahun_saja_bukan_tanggal(): void
    {
        // Dulu: 'x' -> hari ini, '1' -> 1899-12-31, '2020' -> 1905-07-12 (serial Excel) — semua diam-diam.
        $anak = $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'BCG', 'MR1'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', 'x', '1', '2020'],
        ));

        $this->assertDatabaseCount('imunisasi', 0);
        $p = $this->hanyaYangMemuat($pesan, 'tanggal tidak terbaca');
        $this->assertCount(1, $p);
        $this->assertStringContainsString("HB0 ('x')", $p[0]);
        $this->assertStringContainsString("BCG ('1')", $p[0]);
        $this->assertStringContainsString("MR1 ('2020')", $p[0]);
        $this->assertSame($anak->id, Anak::first()->id);
    }

    public function test_tanggal_garis_miring_ditolak_karena_urutan_hari_bulan_ambigu(): void
    {
        // Carbon membaca '05/02/2020' sebagai 2 Mei (gaya AS) padahal petugas bermaksud 5 Februari.
        $anak = $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '05/02/2020'],
        ));

        $this->assertDatabaseMissing('imunisasi', ['id_anak' => $anak->id]);
        $p = $this->hanyaYangMemuat($pesan, 'tanggal tidak terbaca');
        $this->assertCount(1, $p);
        $this->assertStringContainsString("HB0 ('05/02/2020')", $p[0]);
    }

    public function test_format_iso_hari_bulan_tahun_dan_serial_excel_diterima(): void
    {
        $anak = $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'BCG', 'MR1'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '2020-02-15', '16-02-2020', 43878], // 43878 = 2020-02-17
        ));

        $kode = fn (string $k) => JenisVaksin::where('kode', $k)->value('id');
        $this->assertDatabaseHas('imunisasi', ['id_anak' => $anak->id, 'id_jenis_vaksin' => $kode('HB0'), 'tanggal_pemberian' => '2020-02-15']);
        $this->assertDatabaseHas('imunisasi', ['id_anak' => $anak->id, 'id_jenis_vaksin' => $kode('BCG'), 'tanggal_pemberian' => '2020-02-16']);
        $this->assertDatabaseHas('imunisasi', ['id_anak' => $anak->id, 'id_jenis_vaksin' => $kode('MR1'), 'tanggal_pemberian' => '2020-02-17']);
        $this->assertSame([], $this->hanyaYangMemuat($pesan, 'tanggal tidak terbaca'));
    }

    public function test_baris_tanpa_tanggal_dan_alasan_diberi_info(): void
    {
        $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'BCG', 'alasan_tidak_imunisasi'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '', '', ''],
        ));

        $p = $this->hanyaYangMemuat($pesan, 'tidak ada yang disimpan');
        $this->assertCount(1, $p);
        $this->assertStringStartsWith('[INFO] Baris 2 (Budi Santoso)', $p[0]);
        $this->assertStringContainsString('alasan_tidak_imunisasi', $p[0]);
    }

    public function test_long_kode_vaksin_tak_dikenal_diberi_daftar_kode_sah_sekali(): void
    {
        $this->anak();
        $pesan = $this->pesan($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'kode_vaksin', 'tanggal_pemberian'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', 'XYZ', '2020-01-15'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', 'ABC', '2020-01-15'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', 'HB0', '2020-01-15'],
        ));

        $this->assertCount(2, $this->hanyaYangMemuat($pesan, 'tidak ditemukan di master data'));
        $info = $this->hanyaYangMemuat($pesan, 'Kode vaksin yang tidak dikenal');
        $this->assertCount(1, $info);
        $this->assertStringContainsString('XYZ, ABC', $info[0]);
        $this->assertStringContainsString('HB0', $info[0]);
        $this->assertStringContainsString('BCG', $info[0]);
        $this->assertStringStartsWith('Ringkasan: 3 baris data dibaca, 1 vaksin disimpan/diperbarui untuk 1 anak, 0 baris gagal, 2 baris dilewati', $pesan[0]);
    }
}
