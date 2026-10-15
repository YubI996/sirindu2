<?php

namespace Tests\Feature\ImportKesmas;

use App\Exports\TemplateKesmas;
use App\Imports\KesmasImport;
use App\Models\Anak;
use App\Services\PenautanAnakKesmas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Temuan reviu akhir branch import Kesmas (15 Okt 2026): salah anak tanpa pesan lewat NIK kembar,
 * model anak tersimpan dengan nilai baris yang gagal, dan berat lahir gram tersimpan sebagai kg.
 */
class TemuanReviuImportKesmasTest extends TestCase
{
    use RefreshDatabase;

    private const ID = ['nik_anak', 'nama_anak', 'jk', 'tgl_lahir', 'nama_ortu'];

    private function anak(array $isi): Anak
    {
        return Anak::create(array_merge(['jk' => 1, 'status' => 1], $isi));
    }

    private function kembar(): array
    {
        return [
            $this->anak(['nama' => 'Muhammad Zayyan Al Fatih', 'nik' => '6474011204260002', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']),
            $this->anak(['nama' => 'Muhammad Rayyan Al Fatih', 'nik' => '6474011204260001', 'tgl_lahir' => '2026-04-12', 'nama_ibu' => 'ALFIANA']),
        ];
    }

    private function impor(array $header, array $baris): array
    {
        $i = (new KesmasImport(1))->setFormatTanggal('auto');
        $i->collection(collect(array_merge([$header], $baris)));

        return $i->getResults();
    }

    public function test_nik_kembaran_tidak_menempelkan_data_ke_kembaran_yang_salah(): void
    {
        [$zayyan] = $this->kembar();

        // Baris untuk Rayyan, tetapi NIK yang tertulis milik Zayyan (salah satu digit urut).
        $hasil = (new PenautanAnakKesmas())->cari('Muhammad Rayyan Al Fatih', 'L', '2026-04-12', 'ALFIANA', '6474011204260002');

        $this->assertNotSame($zayyan->id, $hasil['anak']?->id, 'NIK kembaran tidak boleh mengalahkan nama yang jelas berbeda');
        $this->assertContains('PERINGATAN', array_column($hasil['pesan'], 0));
    }

    public function test_nik_kembaran_dengan_nama_sama_persis_tetap_cocok(): void
    {
        [$zayyan] = $this->kembar();

        $hasil = (new PenautanAnakKesmas())->cari('MUHAMMAD ZAYYAN AL FATIH', 'L', '2026-04-12', 'ALFIANA', '6474011204260002');

        $this->assertSame($zayyan->id, $hasil['anak']->id);
        $this->assertSame([], $hasil['pesan']);
    }

    public function test_baris_gagal_tidak_mewariskan_nilai_ke_baris_berikutnya_anak_yang_sama(): void
    {
        $budi = $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti Aminah']);
        // Kegagalan simpan di tingkat DB untuk satu nilai tertentu (setara galat "out of range"/koneksi).
        Anak::saving(function (Anak $a) {
            if ($a->fktp_bpjs === 'GAGAL') {
                throw new \RuntimeException('simulasi galat simpan');
            }
        });

        $hasil = $this->impor([...self::ID, 'fktp_bpjs', 'air_bersih'], [
            ['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah', 'GAGAL', 'Ya'],
            ['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah', '', 'Tidak'],
        ]);

        $this->assertSame(1, $hasil['success'], implode("\n", $hasil['failures']));
        $this->assertSame(1, $hasil['error_count']);
        $budi->refresh();
        $this->assertSame(0, (int) $budi->air_bersih);
        $this->assertNull($budi->fktp_bpjs);
        $this->assertStringNotContainsString('berbeda dengan baris 2', implode("\n", $hasil['failures']));
    }

    public function test_berat_lahir_dalam_gram_ditolak_bukan_tersimpan_sebagai_kg(): void
    {
        $budi = $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti Aminah']);

        $hasil = $this->impor([...self::ID, 'bbl', 'air_bersih'], [
            ['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah', '3200', 'Ya'],
        ]);

        $this->assertNull($budi->fresh()->bbl);
        $this->assertSame(1, (int) $budi->fresh()->air_bersih);
        $this->assertStringContainsString('Berat lahir', implode("\n", $hasil['failures']));
        $this->assertStringContainsString('kg', implode("\n", $hasil['failures']));
    }

    public function test_berat_lahir_kg_diterima(): void
    {
        $budi = $this->anak(['nama' => 'Budi Santoso', 'nik' => '6474010101230001', 'tgl_lahir' => '2023-01-01', 'nama_ibu' => 'Siti Aminah']);

        $this->impor([...self::ID, 'bbl'], [['6474010101230001', 'Budi Santoso', 'L', '2023-01-01', 'Siti Aminah', '3,2']]);

        $this->assertEqualsWithDelta(3.2, (float) $budi->fresh()->bbl, 0.001);
    }

    public function test_petunjuk_template_menyebut_satuan_riwayat_lahir(): void
    {
        $ket = array_column(TemplateKesmas::kolom(), 'keterangan', 'kolom');

        $this->assertStringContainsString('kg', $ket['bbl']);
        $this->assertStringContainsString('cm', $ket['pbl']);
        $this->assertStringContainsString('cm', $ket['lk_lahir']);
    }
}
