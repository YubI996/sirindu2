<?php

namespace Tests\Feature\Spm;

use App\Models\SpmCapaian;
use App\Models\SpmKategori;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpmModelTest extends TestCase
{
    use RefreshDatabase;

    private function kategori(string $nama = 'Pelayanan Kesehatan Balita'): SpmKategori
    {
        return SpmKategori::create(['nama' => $nama, 'satuan' => 'anak']);
    }

    public function test_triwulan_yang_belum_dilaporkan_tersimpan_null(): void
    {
        $kategori = $this->kategori();

        SpmCapaian::create([
            'id_kategori' => $kategori->id,
            'tahun'       => 2026,
            'sasaran'     => 1000,
            'tw1'         => 200,
        ]);

        $baris = SpmCapaian::first();

        $this->assertSame(200.0, $baris->tw1);
        $this->assertNull($baris->tw2, 'TW II yang tidak dikirim harus NULL, bukan 0');
        $this->assertNull($baris->tw3);
        $this->assertNull($baris->tw4);
    }

    public function test_nol_berbeda_dari_belum_dilaporkan(): void
    {
        $kategori = $this->kategori();

        SpmCapaian::create([
            'id_kategori' => $kategori->id,
            'tahun'       => 2026,
            'sasaran'     => 1000,
            'tw1'         => 0,
        ]);

        $baris = SpmCapaian::first();

        $this->assertSame(0.0, $baris->tw1, 'capaian nol harus tetap 0, bukan NULL');
        $this->assertNull($baris->tw2);
    }

    public function test_satu_kategori_satu_baris_per_tahun(): void
    {
        $kategori = $this->kategori();
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2026, 'sasaran' => 1000]);

        $this->expectException(QueryException::class);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2026, 'sasaran' => 2000]);
    }

    public function test_kategori_yang_sama_boleh_punya_beberapa_tahun(): void
    {
        $kategori = $this->kategori();
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2025, 'sasaran' => 900]);
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2026, 'sasaran' => 1000]);

        $this->assertCount(2, $kategori->fresh()->capaian);
    }

    public function test_soft_delete_kategori_tidak_menghapus_angka_tahun_lalu(): void
    {
        $kategori = $this->kategori();
        SpmCapaian::create(['id_kategori' => $kategori->id, 'tahun' => 2025, 'sasaran' => 900, 'tw1' => 100]);

        $kategori->delete();

        $this->assertSoftDeleted('spm_kategori', ['id' => $kategori->id]);
        $this->assertDatabaseHas('spm_capaian', ['id_kategori' => $kategori->id, 'tahun' => 2025]);

        $kategori->restore();
        $this->assertCount(1, $kategori->fresh()->capaian);
    }

    public function test_sasaran_besar_dan_desimal_tersimpan_utuh(): void
    {
        $kategori = $this->kategori();

        SpmCapaian::create([
            'id_kategori' => $kategori->id,
            'tahun'       => 2026,
            'sasaran'     => 1234567.89,
            'tw1'         => 0.5,
        ]);

        $baris = SpmCapaian::first();

        $this->assertSame(1234567.89, $baris->sasaran);
        $this->assertSame(0.5, $baris->tw1);
    }
}
