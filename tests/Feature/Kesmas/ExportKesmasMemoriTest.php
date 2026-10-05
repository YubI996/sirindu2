<?php

namespace Tests\Feature\Kesmas;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Export Kesmas tanpa filter (seluruh kota) dulu memakai Maatwebsite biasa: seluruh buku Excel
 * (dua sheet) ditumpuk di memori sebelum ditulis. Terukur ±38 KB per anak ber-2-kunjungan di skrip
 * (±440 MB diekstrapolasi untuk 10 rb anak) dan 60,8 MB untuk 800 anak di tes ini — di prod
 * hasilnya "This page isn't working", sementara dengan filter satu kelurahan berhasil. Di dev
 * (puluhan anak) tak pernah ketahuan.
 *
 * Tes ini mengunci bahwa memori puncak TIDAK tumbuh sebanding jumlah baris. Ia gagal bila
 * seseorang mengembalikan Maatwebsite/PhpSpreadsheet atau memuat seluruh baris dengan get().
 * Pola yang sama: ExportAllDataMemoriTest.
 */
class ExportKesmasMemoriTest extends TestCase
{
    use RefreshDatabase;

    private const JUMLAH_ANAK = 800;

    private const KUNJUNGAN_PER_ANAK = 3;

    /** Cara lama: 60,8 MB untuk 800 anak; streaming: ±5 MB (datar, tak bergantung jumlah anak). */
    private const BATAS_MB = 12;

    public function test_export_seluruh_kota_tidak_menumpuk_seluruh_baris_di_memori(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'API-API', 'id_kecamatan' => $kec->id]);
        $this->isi($kec->id, $kel->id);

        $this->assertSame(self::JUMLAH_ANAK, DB::table('anak')->count());
        $this->assertSame(self::JUMLAH_ANAK * self::KUNJUNGAN_PER_ANAK, DB::table('data_anak')->count());

        gc_collect_cycles();
        $awal = memory_get_usage();

        $r = $this->actingAs($admin)->get(route('admin.export.kesmas.download'));
        // StreamedResponse: tanpa streamedContent() generator tak pernah dikonsumsi dan tes ini
        // mengukur request kosong — lulus walau jalur streaming-nya rusak.
        $isi = $r->streamedContent();

        $kenaikanMb = (memory_get_peak_usage() - $awal) / 1048576;

        $r->assertOk();
        $this->assertStringStartsWith('PK', $isi, 'isi respons harus berkas xlsx yang benar-benar terbentuk');
        $this->assertLessThan(
            self::BATAS_MB,
            $kenaikanMb,
            sprintf('Memori puncak naik %.1f MB untuk %d anak — export menumpuk seluruh baris.', $kenaikanMb, self::JUMLAH_ANAK)
        );

        // Paginasi berlapis (halaman anak, kelompok kunjungan): tak boleh ada baris terlewat/ganda
        // di batas potongan. Dibaca SETELAH pengukuran memori.
        $path = tempnam(sys_get_temp_dir(), 'kesmas');
        try {
            file_put_contents($path, $isi);
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
            $nik = fn (string $sheet) => array_filter(array_column($book->getSheetByName($sheet)->toArray(null, false, false, false), 0));

            $perAnak = $nik('Per Anak');
            $perKunjungan = $nik('Per Kunjungan');
            $this->assertCount(self::JUMLAH_ANAK + 1, $perAnak, 'judul + satu baris per anak');
            $this->assertCount(self::JUMLAH_ANAK, array_unique(array_slice($perAnak, 1)), 'tak ada anak ganda');
            $this->assertCount(self::JUMLAH_ANAK * self::KUNJUNGAN_PER_ANAK + 1, $perKunjungan, 'judul + satu baris per kunjungan');
        } finally {
            unlink($path);
        }
    }

    private function isi(int $idKec, int $idKel): void
    {
        $now = now()->toDateTimeString();

        foreach (array_chunk(range(1, self::JUMLAH_ANAK), 200) as $potongan) {
            $anak = [];
            foreach ($potongan as $i) {
                $anak[] = [
                    'nama' => 'Anak Massal ' . $i, 'nik' => '3' . str_pad((string) $i, 15, '0', STR_PAD_LEFT),
                    'jk' => 1, 'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2024-03-15', 'status' => 1,
                    'sasaran_balita_kesmas' => 1, 'tgl_hbig' => '2024-03-16', 'id_kec' => $idKec, 'id_kel' => $idKel,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('anak')->insert($anak);
        }

        foreach (DB::table('anak')->pluck('id')->chunk(200) as $potongan) {
            $kunjungan = [];
            foreach ($potongan as $id) {
                for ($k = 1; $k <= self::KUNJUNGAN_PER_ANAK; $k++) {
                    $kunjungan[] = [
                        'id_anak' => $id, 'tgl_kunjungan' => sprintf('2025-%02d-15', $k), 'bln' => $k, 'posisi' => 'L',
                        'tb' => 80, 'bb' => 10, 'lla' => 14, 'lk' => 45, 'id_user' => 1, 'sumber' => 'manual',
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            DB::table('data_anak')->insert($kunjungan);
        }
    }
}
