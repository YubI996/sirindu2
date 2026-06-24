<?php

namespace Tests\Feature;

use App\Imports\ImunisasiImport;
use App\Models\Anak;
use App\Models\DataAnak;
use App\Models\JenisVaksin;
use App\Models\User;
use Database\Seeders\JenisVaksinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ImunisasiImportWideTest extends TestCase
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
            'nama' => 'Budi Santoso',
            'nik' => '3201011501200001',
            'jk' => 1,
            'tempat_lahir' => 'Bontang',
            'tgl_lahir' => '2020-01-15',
            'status' => 1,
        ], $overrides));
    }

    /** Bangun Collection baris ala satu chunk: [header, ...data]. */
    private function rows(array $header, array ...$data): Collection
    {
        return collect(array_merge([$header], $data));
    }

    public function test_wide_upsert_vaksin_dengan_tanggal_dan_lewati_kosong(): void
    {
        $anak = $this->anak();
        $import = new ImunisasiImport($this->admin->id);

        $import->collection($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'BCG', 'MR1', 'alasan_tidak_imunisasi'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '2020-01-15', '2020-02-15', '', '']
        ));

        $hb0 = JenisVaksin::where('kode', 'HB0')->value('id');
        $bcg = JenisVaksin::where('kode', 'BCG')->value('id');
        $mr1 = JenisVaksin::where('kode', 'MR1')->value('id');

        $this->assertDatabaseHas('imunisasi', [
            'id_anak' => $anak->id, 'id_jenis_vaksin' => $hb0,
            'status' => 'sudah', 'tanggal_pemberian' => '2020-01-15',
        ]);
        $this->assertDatabaseHas('imunisasi', [
            'id_anak' => $anak->id, 'id_jenis_vaksin' => $bcg, 'status' => 'sudah',
        ]);
        $this->assertDatabaseMissing('imunisasi', [
            'id_anak' => $anak->id, 'id_jenis_vaksin' => $mr1,
        ]);
    }

    public function test_wide_tulis_alasan_ke_data_anak_kunjungan_terakhir(): void
    {
        $anak = $this->anak();
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-01-01', 'bln' => 48, 'posisi' => 'berdiri',
            'tb' => 90, 'bb' => 13, 'lla' => 14, 'lk' => 48, 'id_user' => $this->admin->id,
        ]);
        DataAnak::create([
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01', 'bln' => 53, 'posisi' => 'berdiri',
            'tb' => 95, 'bb' => 14, 'lla' => 15, 'lk' => 49, 'id_user' => $this->admin->id,
        ]);

        $import = new ImunisasiImport($this->admin->id);
        $import->collection($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'alasan_tidak_imunisasi'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '', 'Anak sakit saat jadwal imunisasi']
        ));

        $this->assertDatabaseHas('data_anak', [
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-06-01',
            'alasan_tidak_imunisasi' => 'Anak sakit saat jadwal imunisasi',
        ]);
        $this->assertDatabaseHas('data_anak', [
            'id_anak' => $anak->id, 'tgl_kunjungan' => '2024-01-01',
            'alasan_tidak_imunisasi' => null,
        ]);
    }

    public function test_wide_buat_baris_minimal_bila_anak_tanpa_data_anak(): void
    {
        $anak = $this->anak();
        $this->assertDatabaseCount('data_anak', 0);

        $import = new ImunisasiImport($this->admin->id);
        $import->collection($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'HB0', 'alasan_tidak_imunisasi'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', '', 'Orang tua bekerja']
        ));

        $this->assertDatabaseHas('data_anak', [
            'id_anak' => $anak->id, 'bb' => 0, 'tb' => 0,
            'alasan_tidak_imunisasi' => 'Orang tua bekerja', 'id_user' => $this->admin->id,
        ]);
    }

    public function test_long_format_lama_tetap_didukung(): void
    {
        $anak = $this->anak();
        $import = new ImunisasiImport($this->admin->id);
        $import->collection($this->rows(
            ['nik_anak', 'nama_anak', 'tgl_lahir_anak', 'kode_vaksin', 'dosis', 'tanggal_pemberian', 'tanggal_selanjutnya', 'batch_number', 'lokasi_pemberian', 'status', 'reaksi_kipi', 'catatan'],
            ['3201011501200001', 'Budi Santoso', '2020-01-15', 'HB0', '1', '2020-01-15', '', 'VX-1', 'Paha Kanan', 'sudah', '', 'catat']
        ));

        $hb0 = JenisVaksin::where('kode', 'HB0')->value('id');
        $this->assertDatabaseHas('imunisasi', [
            'id_anak' => $anak->id, 'id_jenis_vaksin' => $hb0, 'status' => 'sudah',
            'batch_number' => 'VX-1', 'lokasi_pemberian' => 'Paha Kanan',
        ]);
    }
}
