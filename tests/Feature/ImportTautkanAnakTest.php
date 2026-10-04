<?php

namespace Tests\Feature;

use App\Imports\AnakImport;
use App\Imports\KohortImport;
use App\Models\Anak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Importer yang membuat anak (Import Anak, Kohort) menautkan baris ke anak yang SUDAH ada lewat
 * nama + tgl lahir + jk (KK tak bertentangan), bukan hanya lewat NIK — 72% anak ganda di prod punya
 * NIK yang berbeda total, jadi pencocokan "NIK mirip" tak cukup.
 *
 * NIK mana yang bertahan ditentukan keasliannya (KeaslianNik): yang lebih real menggantikan,
 * seri -> NIK di DB dipertahankan. Baris Operasi Timbang tidak diganti NIK-nya (kunci import OT).
 */
class ImportTautkanAnakTest extends TestCase
{
    use RefreshDatabase;

    // Anak perempuan lahir 10-03-2021 -> bagian tanggal "500321" (10+40, 03, 21)
    private const NIK_ANAK   = '6474015003210001';   // cocok tgl lahir + jk = paling real
    private const NIK_IBU    = '6474016003870006';   // NIK ibu yang terselip (tgl lahir 1987)
    private const NIK_LAIN   = '6474017003880007';   // 16 digit lain yang juga tak cocok
    private const POTONGAN   = '647401500321000';    // 15 digit
    private const DUMMY      = '6474005003219001';   // digit ke-13 = 9

    private function anak(array $o = []): Anak
    {
        return Anak::create(array_merge([
            'nik' => self::NIK_IBU, 'nama' => 'ANI WIJAYA', 'jk' => 2,
            'tgl_lahir' => '2021-03-10', 'no' => 'REG-1', 'status' => 1, 'sumber' => 'manual',
        ], $o));
    }

    private function imporAnak(array $rows): array
    {
        $i = new AnakImport(1);
        $i->collection(collect($rows));

        return $i->getResults();
    }

    private function baris(string $nik, array $ekstra = []): array
    {
        return [['nik', 'nama', 'tgl_lahir', 'jk'], [$nik, 'ANI WIJAYA', '2021-03-10', 'P'] + $ekstra];
    }

    private function teks(array $hasil): string
    {
        return implode("\n", $hasil['failures']);
    }

    // ---- Import Anak ---------------------------------------------------------

    public function test_nik_berkas_yang_lebih_real_menggantikan_nik_lama(): void
    {
        $lama = $this->anak(['nik' => self::NIK_IBU]);   // NIK ibu di kolom anak

        $hasil = $this->imporAnak($this->baris(self::NIK_ANAK));

        $this->assertSame(1, Anak::count(), 'anak yang sama tidak digandakan walau NIK-nya berbeda total');
        $this->assertSame($lama->id, Anak::firstOrFail()->id);
        $this->assertSame(self::NIK_ANAK, Anak::firstOrFail()->nik, 'NIK yang cocok tgl lahir menggantikan NIK ibu');
        $this->assertStringContainsString('diganti', $this->teks($hasil));
    }

    public function test_nik_berkas_yang_kurang_real_tidak_menggantikan_nik_db(): void
    {
        $this->anak(['nik' => self::NIK_ANAK]);

        $hasil = $this->imporAnak($this->baris(self::NIK_IBU));

        $this->assertSame(1, Anak::count());
        $this->assertSame(self::NIK_ANAK, Anak::firstOrFail()->nik);
        $this->assertStringContainsString('[PERINGATAN]', $this->teks($hasil));
        $this->assertStringContainsString('kurang real', $this->teks($hasil));
    }

    public function test_nik_sama_kuat_nik_db_dipertahankan_dan_dilaporkan(): void
    {
        $this->anak(['nik' => self::NIK_IBU]);

        $hasil = $this->imporAnak($this->baris(self::NIK_LAIN));

        $this->assertSame(1, Anak::count());
        $this->assertSame(self::NIK_IBU, Anak::firstOrFail()->nik, 'seri: NIK di DB dipertahankan');
        $this->assertStringContainsString('[PERINGATAN]', $this->teks($hasil));
    }

    public function test_nik_15_digit_diganti_nik_16_digit_yang_sama(): void
    {
        $lama = $this->anak(['nik' => self::POTONGAN]);

        $this->imporAnak($this->baris(self::NIK_ANAK));

        $this->assertSame(1, Anak::count());
        $this->assertSame($lama->id, Anak::firstOrFail()->id);
        $this->assertSame(self::NIK_ANAK, Anak::firstOrFail()->nik);
    }

    public function test_nik_dummy_diganti_nik_asli(): void
    {
        $lama = $this->anak(['nik' => self::DUMMY]);

        $this->imporAnak($this->baris(self::NIK_ANAK));

        $this->assertSame(1, Anak::count());
        $this->assertSame($lama->id, Anak::firstOrFail()->id);
        $this->assertSame(self::NIK_ANAK, Anak::firstOrFail()->nik);
    }

    public function test_nik_baris_operasi_timbang_tidak_diganti_walau_berkas_lebih_real(): void
    {
        $ot = $this->anak(['nik' => self::NIK_IBU, 'sumber' => 'operasi_timbang']);

        $hasil = $this->imporAnak($this->baris(self::NIK_ANAK));

        $this->assertSame(1, Anak::count(), 'tetap tertaut, tidak dibuat anak kedua');
        $this->assertSame(self::NIK_IBU, $ot->fresh()->nik, 'import OT memakai NIK sebagai kunci; mengubahnya menggandakan OT saat import ulang');
        $this->assertStringContainsString('Operasi Timbang', $this->teks($hasil));
    }

    public function test_kk_berbeda_nik_berbeda_tetap_anak_baru(): void
    {
        $this->anak(['nik' => self::NIK_IBU, 'no_kk' => '3274000000000001']);

        $this->imporAnak([['nik', 'nama', 'tgl_lahir', 'jk', 'no_kk'],
            [self::NIK_ANAK, 'ANI WIJAYA', '2021-03-10', 'P', '3274000000000002']]);

        $this->assertSame(2, Anak::count(), 'KK berbeda = keluarga berbeda');
    }

    public function test_dua_kandidat_campuran_asli_dan_dummy_dilaporkan_ambigu(): void
    {
        $this->anak(['nik' => self::NIK_IBU]);
        $this->anak(['nik' => self::DUMMY, 'no' => 'REG-D']);

        $hasil = $this->imporAnak($this->baris(self::NIK_ANAK));

        $this->assertSame(2, Anak::count(), 'ambigu: jangan menebak, jangan membuat anak ketiga');
        $this->assertSame(1, $hasil['error_count']);
        $this->assertStringContainsString('Ditemukan 2 anak', $this->teks($hasil));
    }

    public function test_tanpa_nik_dengan_kembar_asli_dan_dummy_juga_ambigu(): void
    {
        $this->anak(['nik' => self::NIK_IBU]);
        $this->anak(['nik' => self::DUMMY, 'no' => 'REG-D']);

        $hasil = $this->imporAnak($this->baris(''));

        $this->assertSame(2, Anak::count());
        $this->assertSame(1, $hasil['error_count']);
    }

    public function test_nik_valid_yang_sudah_ada_di_db_tetap_jalur_nik(): void
    {
        $this->anak(['nik' => self::NIK_ANAK, 'alamat' => 'LAMA']);
        $this->anak(['nik' => self::DUMMY, 'no' => 'REG-D']);   // kembar ganda yang sudah ada

        $this->imporAnak($this->baris(self::NIK_ANAK, [4 => 'BARU']));

        $this->assertSame(2, Anak::count(), 'NIK ketemu persis: tidak menyentuh kembarannya');
    }

    // ---- Kohort --------------------------------------------------------------

    private function imporKohort(string $nik): array
    {
        $i = new KohortImport(1);
        // Maatwebsite memberi tiap baris sebagai Collection (KohortImport::detectColumns meminta itu).
        $i->collection(collect([
            collect(['No', 'NIK', 'Nama', 'Tgl lahir', 'JK', 'No KK']),
            collect([1, $nik, 'ANI WIJAYA', '2021-03-10', 'P', '']),
        ]));

        return $i->getResults();
    }

    public function test_kohort_nik_lebih_real_menggantikan_dan_tidak_menggandakan(): void
    {
        $lama = $this->anak(['nik' => self::NIK_IBU]);

        $this->imporKohort(self::NIK_ANAK);

        $this->assertSame(1, Anak::count());
        $this->assertSame($lama->id, Anak::firstOrFail()->id);
        $this->assertSame(self::NIK_ANAK, Anak::firstOrFail()->nik);
    }

    public function test_kohort_tanpa_nik_memakai_anak_ber_nik_asli(): void
    {
        $asli = $this->anak(['nik' => self::NIK_ANAK]);

        $this->imporKohort('');

        $this->assertSame(1, Anak::count(), 'dulu findExisting hanya melihat NIK dummy');
        $this->assertSame(self::NIK_ANAK, $asli->fresh()->nik);
    }

    public function test_kohort_nik_baris_operasi_timbang_tidak_diganti(): void
    {
        $ot = $this->anak(['nik' => self::NIK_IBU, 'sumber' => 'operasi_timbang']);

        $this->imporKohort(self::NIK_ANAK);

        $this->assertSame(1, Anak::count());
        $this->assertSame(self::NIK_IBU, $ot->fresh()->nik);
    }
}
