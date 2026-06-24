<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Models\Anak;
use App\Models\DataAnak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ImunisasiAlasanChartDataTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(array $filters): array
    {
        $controller = app(AdminController::class);
        $m = new ReflectionMethod(AdminController::class, 'alasanTidakImunisasiData');
        $m->setAccessible(true);
        return $m->invoke($controller, $filters);
    }

    private function anakWithVisits(string $nik, array $visits): Anak
    {
        $anak = Anak::create([
            'nama' => 'Anak ' . $nik, 'nik' => $nik, 'jk' => 1,
            'tempat_lahir' => 'Bontang', 'tgl_lahir' => '2022-01-01', 'status' => 1,
        ]);
        foreach ($visits as $v) {
            DataAnak::create([
                'id_anak' => $anak->id, 'tgl_kunjungan' => $v['tgl'], 'bln' => 24, 'posisi' => 'berdiri',
                'tb' => 0, 'bb' => 0, 'lla' => 0, 'lk' => 0, 'id_user' => 1,
                'alasan_tidak_imunisasi' => $v['alasan'] ?? null,
            ]);
        }
        return $anak;
    }

    public function test_agregasi_kunjungan_terakhir_dan_bucket_lainnya(): void
    {
        // Anak 1: kunjungan lama 'Orang tua bekerja', kunjungan terakhir alasan dikenal.
        $this->anakWithVisits('3201000000000001', [
            ['tgl' => '2024-01-01', 'alasan' => 'Orang tua bekerja'],
            ['tgl' => '2024-06-01', 'alasan' => 'Anak sakit saat jadwal imunisasi'],
        ]);
        // Anak 2: teks bebas → Lainnya.
        $this->anakWithVisits('3201000000000002', [
            ['tgl' => '2024-05-01', 'alasan' => 'Pindah ke luar kota'],
        ]);
        // Anak 3: tanpa alasan → tak dihitung.
        $this->anakWithVisits('3201000000000003', [
            ['tgl' => '2024-05-01', 'alasan' => null],
        ]);

        $data = $this->invoke([]);

        $this->assertSame(1, $data['Anak sakit saat jadwal imunisasi'] ?? 0);
        $this->assertSame(1, $data['Lainnya'] ?? 0);
        // Alasan dari kunjungan LAMA tidak boleh ikut terhitung.
        $this->assertArrayNotHasKey('Orang tua bekerja', $data);
    }
}
