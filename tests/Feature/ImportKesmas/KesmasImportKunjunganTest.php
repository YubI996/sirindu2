<?php

namespace Tests\Feature\ImportKesmas;

use App\Imports\KesmasImport;
use App\Models\Anak;
use App\Models\DataAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KesmasImportKunjunganTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = ['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'nama_ortu', 'tgl_kunjungan', 'bb', 'tb_cm', 'posisi', 'mbg', 'rujukan', 'air_bersih'];

    private Anak $anak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->anak = Anak::create([
            'nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'jk' => 1,
            'tgl_lahir' => '2023-01-01', 'status' => 1, 'nama_ibu' => 'Siti Aminah',
        ]);
    }

    private function baris(string $tglLahir, string $tgl, mixed $bb, mixed $tb = 85, string $posisi = 'Berdiri', string $mbg = '', string $rujukan = '', string $air = ''): array
    {
        return ['6474010101230001', 'Budi Santoso', 'L', $tglLahir, 'Siti Aminah', $tgl, $bb, $tb, $posisi, $mbg, $rujukan, $air];
    }

    private function impor(array $baris): array
    {
        $i = (new KesmasImport(7))->setFormatTanggal('auto');
        $i->collection(collect(array_merge([self::HEADER], $baris)));

        return $i->getResults();
    }

    private function teks(array $hasil): string
    {
        return implode("\n", $hasil['failures']);
    }

    public function test_kunjungan_baru_dibuat_dengan_ukuran_kg_dan_layanan(): void
    {
        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', '10,5', 85, 'Berdiri', 'Ya')]);

        $this->assertSame(1, $hasil['success'], $this->teks($hasil));
        $k = DataAnak::sole();
        $this->assertSame('2025-03-10', substr((string) $k->tgl_kunjungan, 0, 10));
        $this->assertEqualsWithDelta(10.5, (float) $k->bb, 0.0001);
        $this->assertEqualsWithDelta(85.0, (float) $k->tb, 0.0001);
        $this->assertSame('H', $k->posisi);
        $this->assertSame(26, (int) $k->bln);
        $this->assertSame(1, (int) $k->mbg);
        $this->assertSame(0.0, (float) $k->lk);
        $this->assertSame(7, (int) $k->id_user);
    }

    public function test_bayi_di_bawah_dua_bulan_bb_dalam_gram(): void
    {
        $this->anak->update(['tgl_lahir' => '2025-01-10']);

        $hasil = $this->impor([$this->baris('2025-01-10', '2025-02-01', 3250, 50, 'Terlentang')]);

        $this->assertSame(1, $hasil['success'], $this->teks($hasil));
        $this->assertEqualsWithDelta(3.25, (float) DataAnak::sole()->bb, 0.0001);
    }

    public function test_bayi_di_bawah_dua_bulan_bb_ditulis_kg_ditolak_seluruh_baris(): void
    {
        $this->anak->update(['tgl_lahir' => '2025-01-10']);

        $hasil = $this->impor([$this->baris('2025-01-10', '2025-02-01', '3,25', 50, 'Terlentang', '', '', 'Ya')]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, DataAnak::count());
        $this->assertNull($this->anak->fresh()->air_bersih);   // field anak ikut batal
        $this->assertStringContainsString('gram', $this->teks($hasil));
    }

    public function test_bb_gram_dengan_titik_ribuan_ditolak_bukan_tersimpan_kg(): void
    {
        $this->anak->update(['tgl_lahir' => '2025-01-10']);

        $hasil = $this->impor([$this->baris('2025-01-10', '2025-02-01', '3.250', 50, 'Terlentang')]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, DataAnak::count());
    }

    public function test_tepat_dua_bulan_sudah_kg(): void
    {
        $this->anak->update(['tgl_lahir' => '2025-01-10']);

        $this->impor([$this->baris('2025-01-10', '2025-03-10', 5, 57, 'Terlentang')]);

        $this->assertEqualsWithDelta(5.0, (float) DataAnak::sole()->bb, 0.0001);
    }

    public function test_kunjungan_baru_tanpa_posisi_error(): void
    {
        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', 10, 85, '')]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(0, DataAnak::count());
        $this->assertStringContainsString('posisi', $this->teks($hasil));
    }

    public function test_kunjungan_yang_ada_ukurannya_tidak_diubah_tetapi_layanannya_diisi(): void
    {
        DataAnak::create([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => '2025-03-10', 'bln' => 26, 'posisi' => 'H',
            'bb' => 10.2, 'tb' => 85, 'lk' => 0, 'lla' => 0, 'id_user' => 1,
        ]);

        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', '10,5', 85, 'Berdiri', 'Ya')]);

        $k = DataAnak::sole();
        $this->assertEqualsWithDelta(10.2, (float) $k->bb, 0.0001);
        $this->assertSame(1, (int) $k->mbg);
        $this->assertSame(1, $hasil['success']);
        $this->assertStringContainsString('ukuran tidak diubah: bb di berkas 10,5, tersimpan 10,2', $this->teks($hasil));
    }

    public function test_kunjungan_yang_ada_dengan_ukuran_sama_tanpa_peringatan(): void
    {
        DataAnak::create([
            'id_anak' => $this->anak->id, 'tgl_kunjungan' => '2025-03-10', 'bln' => 26, 'posisi' => 'H',
            'bb' => 10.5, 'tb' => 85, 'lk' => 0, 'lla' => 0, 'id_user' => 1,
        ]);

        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', '10,5', 85, 'Berdiri', 'Ya')]);

        $this->assertStringNotContainsString('ukuran tidak diubah', $this->teks($hasil));
    }

    public function test_tgl_kunjungan_tak_terbaca_seluruh_baris_dilewati(): void
    {
        $hasil = $this->impor([$this->baris('2023-01-01', 'kemarin', 10, 85, 'Berdiri', '', '', 'Ya')]);

        $this->assertSame(1, $hasil['error_count']);
        $this->assertNull($this->anak->fresh()->air_bersih);
        $this->assertStringContainsString("tgl_kunjungan 'kemarin' tidak terbaca", $this->teks($hasil));
    }

    public function test_layanan_tak_sah_hanya_sel_itu_kunjungan_tetap_dibuat(): void
    {
        $hasil = $this->impor([$this->baris('2023-01-01', '2025-03-10', 10, 85, 'Berdiri', 'Ya', 'Puskesmas X')]);

        $k = DataAnak::sole();
        $this->assertSame(1, (int) $k->mbg);
        $this->assertNull($k->rujukan);
        $this->assertStringContainsString('Rujukan', $this->teks($hasil));
    }

    public function test_dua_kunjungan_anak_sama_satu_gagal_yang_lain_tersimpan(): void
    {
        $hasil = $this->impor([
            $this->baris('2023-01-01', '2025-03-10', 10, 85),
            $this->baris('2023-01-01', '2025-04-10', 'x', 86),
        ]);

        $this->assertSame(1, $hasil['success']);
        $this->assertSame(1, $hasil['error_count']);
        $this->assertSame(['2025-03-10'], DataAnak::pluck('tgl_kunjungan')->map(fn ($t) => substr((string) $t, 0, 10))->all());
        $this->assertStringContainsString('[ERROR] Baris 3', $this->teks($hasil));
    }
}
