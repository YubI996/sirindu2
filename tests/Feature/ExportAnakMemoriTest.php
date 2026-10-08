<?php

namespace Tests\Feature;

use App\Exports\AnakExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tombol "Export" di Export Data Anak dulu memakai Maatwebsite FromQuery + ShouldAutoSize, yang menumpuk
 * seluruh buku di PhpSpreadsheet (±182 MB untuk 10 rb baris × 32 kolom). Di prod (memory_limit 128 MB)
 * halamannya mati dengan "This page isn't working" tanpa satu baris pun di laravel.log, sementara di dev
 * (puluhan anak) tak pernah ketahuan.
 *
 * Tes ini mengunci bahwa memori puncak penulisan TIDAK tumbuh sebanding jumlah baris, termasuk jalur
 * "sertakan anak tanpa kunjungan". Ia gagal kalau seseorang mengembalikan FromQuery/ShouldAutoSize.
 */
class ExportAnakMemoriTest extends TestCase
{
    use RefreshDatabase;

    private const ANAK_BERKUNJUNG = 1500;

    private const ANAK_TANPA_KUNJUNGAN = 500;

    private const BATAS_MB = 16;

    public function test_penulisan_export_tidak_menumpuk_seluruh_baris_di_memori(): void
    {
        $this->seed2000();

        $this->assertSame(self::ANAK_BERKUNJUNG, DB::table('alldata')->count(), 'tanpa baris di VIEW alldata tes ini tak menguji apa pun');

        $path = tempnam(sys_get_temp_dir(), 'anak');
        try {
            gc_collect_cycles();
            $awal = memory_get_usage();

            (new AnakExport(new Request(['sertakan_tanpa_kunjungan' => '1'])))->simpan($path);

            $kenaikanMb = (memory_get_peak_usage() - $awal) / 1048576;
            $this->assertStringStartsWith('PK', (string) file_get_contents($path, false, null, 0, 2), 'harus berkas xlsx yang benar');
        } finally {
            @unlink($path);
        }

        $this->assertLessThan(
            self::BATAS_MB,
            $kenaikanMb,
            sprintf('Memori puncak naik %.1f MB untuk %d baris — export masih menumpuk seluruh baris.', $kenaikanMb, self::ANAK_BERKUNJUNG + self::ANAK_TANPA_KUNJUNGAN)
        );
    }

    public function test_semua_anak_ikut_dan_tidak_ada_yang_terlewat_atau_ganda_antar_potongan(): void
    {
        $this->seed2000();

        $jumlah = 0;
        $nik = [];
        foreach ((new AnakExport(new Request(['sertakan_tanpa_kunjungan' => '1'])))->baris() as $baris) {
            $jumlah++;
            $nik[$baris[1]] = true;
        }

        $this->assertSame(self::ANAK_BERKUNJUNG + self::ANAK_TANPA_KUNJUNGAN, $jumlah);
        $this->assertCount($jumlah, $nik, 'ada anak yang tercetak ganda / terlewat di batas potongan');
    }

    private function seed2000(): void
    {
        $now = now()->toDateTimeString();
        $total = self::ANAK_BERKUNJUNG + self::ANAK_TANPA_KUNJUNGAN;

        foreach (array_chunk(range(1, $total), 200) as $potongan) {
            DB::table('anak')->insert(array_map(fn ($i) => [
                'nama' => 'Anak Massal ' . $i,
                'nik' => '3' . str_pad((string) $i, 15, '0', STR_PAD_LEFT),
                'no_kk' => '3' . str_pad((string) $i, 15, '0', STR_PAD_LEFT),
                'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2023-05-15', 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ], $potongan));
        }

        // Hanya ANAK_BERKUNJUNG anak pertama yang punya kunjungan; sisanya tanpa kunjungan.
        foreach (array_chunk(DB::table('anak')->orderBy('id')->limit(self::ANAK_BERKUNJUNG)->pluck('id')->all(), 200) as $potongan) {
            DB::table('data_anak')->insert(array_map(fn ($id) => [
                'id_anak' => $id, 'tgl_kunjungan' => '2025-03-15', 'bln' => 22, 'posisi' => 'L',
                'tb' => 80, 'bb' => 10, 'lla' => 14, 'lk' => 45, 'id_user' => 1, 'sumber' => 'manual',
                'created_at' => $now, 'updated_at' => $now,
            ], $potongan));
        }
    }
}
