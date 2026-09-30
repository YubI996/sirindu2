<?php

namespace Tests\Feature;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `/admin/exportAllExcel` dulu memanggil `AllData::all()`.
 *
 * VIEW `alldata` berisi satu baris per KUNJUNGAN (anak × data_anak), jadi
 * jumlah barisnya berkali lipat jumlah anak, dan `all()` menghidrasi semuanya
 * sekaligus jadi model Eloquent 38 kolom. Di dev hal itu tak pernah ketahuan —
 * ratusan baris dengan memory_limit 512 MB selalu lolos; di prod (puluhan ribu
 * baris, memory_limit jauh lebih ketat) ia menabrak batas memori. Pola yang
 * sama persis sudah pernah menjatuhkan dasbor imunisasi pada 16 Sep 2026.
 *
 * Tes ini mengunci bahwa memori puncak export TIDAK tumbuh sebanding jumlah
 * baris. Ia gagal kalau seseorang mengembalikan `all()`/`get()`.
 */
class ExportAllDataMemoriTest extends TestCase
{
    use RefreshDatabase;

    private const JUMLAH_ANAK = 400;

    private const KUNJUNGAN_PER_ANAK = 5; // 2.000 baris di VIEW alldata

    /** Batas kenaikan memori puncak (MB) untuk mengalirkan seluruh baris. */
    private const BATAS_MB = 16;

    public function test_export_semua_data_tidak_memuat_seluruh_baris_ke_memori(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $kec = Kecamatan::create(['name' => 'Bontang Utara']);
        $kel = Kelurahan::create(['name' => 'API-API', 'id_kecamatan' => $kec->id]);

        $this->seedAnakDanKunjungan($kec->id, $kel->id);

        $barisView = DB::table('alldata')->count();
        $this->assertSame(
            self::JUMLAH_ANAK * self::KUNJUNGAN_PER_ANAK,
            $barisView,
            'VIEW alldata harus berisi satu baris per kunjungan — kalau tidak, tes ini tak menguji apa pun'
        );

        gc_collect_cycles();
        $memoriAwal = memory_get_usage();

        $response = $this->actingAs($admin)->get(route('admin.exportAllExcel'));

        // FastExcel memulangkan StreamedResponse: callback ekspornya baru jalan
        // saat isinya dikirim. Tanpa streamedContent() di bawah, generator tak
        // pernah dikonsumsi dan tes ini mengukur request kosong — lulus
        // sekalipun jalur streaming-nya rusak. Pengukuran sengaja melingkupi
        // KEDUANYA: hidrasi yang terlanjur eager (pola `all()` lama, yang
        // memang sudah habis dievaluasi sebelum respons dibuat) maupun
        // penumpukan tak terbatas di dalam stream.
        $isi = $response->streamedContent();

        $kenaikanMb = (memory_get_peak_usage() - $memoriAwal) / 1048576;

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'all-data-anak.xlsx',
            $response->headers->get('content-disposition') ?? '',
            'harus dikirim sebagai unduhan xlsx'
        );
        // Berkas xlsx adalah arsip ZIP — dua huruf pertamanya "PK".
        $this->assertStringStartsWith('PK', $isi, 'isi respons harus berkas xlsx yang benar-benar terbentuk');

        $this->assertLessThan(
            self::BATAS_MB,
            $kenaikanMb,
            sprintf(
                'Memori puncak naik %.1f MB untuk %d baris — export masih memuat seluruh baris sekaligus.',
                $kenaikanMb,
                $barisView
            )
        );
    }

    /** Insert massal supaya tesnya cepat; isinya cuma perlu ada, bukan masuk akal secara klinis. */
    private function seedAnakDanKunjungan(int $idKec, int $idKel): void
    {
        $now = now()->toDateTimeString();

        foreach (array_chunk(range(1, self::JUMLAH_ANAK), 200) as $potongan) {
            $anak = [];
            foreach ($potongan as $i) {
                $anak[] = [
                    'nama' => 'Anak Massal ' . $i,
                    'nik' => '3' . str_pad((string) $i, 15, '0', STR_PAD_LEFT),
                    'no_kk' => '3' . str_pad((string) $i, 15, '0', STR_PAD_LEFT),
                    'jk' => 1,
                    'tempat_lahir' => 'Bontang',
                    'tgl_lahir' => '2023-05-15',
                    'status' => 1,
                    'id_kec' => $idKec,
                    'id_kel' => $idKel,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('anak')->insert($anak);
        }

        $idAnak = DB::table('anak')->pluck('id');
        foreach ($idAnak->chunk(200) as $potongan) {
            $kunjungan = [];
            foreach ($potongan as $id) {
                for ($k = 1; $k <= self::KUNJUNGAN_PER_ANAK; $k++) {
                    $kunjungan[] = [
                        'id_anak' => $id,
                        'tgl_kunjungan' => sprintf('2025-%02d-15', $k),
                        'bln' => $k,
                        'posisi' => 'L',
                        'tb' => 80,
                        'bb' => 10,
                        'lla' => 14,
                        'lk' => 45,
                        'id_user' => 1,
                        'sumber' => 'manual',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            DB::table('data_anak')->insert($kunjungan);
        }
    }
}
